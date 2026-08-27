<?php
if (!defined('_GNUBOARD_')) exit;

@ini_set('memory_limit', '512M');
@set_time_limit(60);

/**
 * 엑셀(.xlsx) 파일 시트 데이터 파서
 */
function parse_xlsx_rows($file_path) {
    $rows = [];
    if (!class_exists('ZipArchive')) return $rows;

    $zip = new ZipArchive;
    if ($zip->open($file_path) === TRUE) {
        $shared_strings = [];
        $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($strings_xml) {
            $sxml = @simplexml_load_string($strings_xml);
            if ($sxml && isset($sxml->si)) {
                foreach ($sxml->si as $si) {
                    $shared_strings[] = (string)($si->t ?? $si->r->t ?? '');
                }
            }
        }

        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet_xml) {
            $xml = @simplexml_load_string($sheet_xml);
            if ($xml && isset($xml->sheetData->row)) {
                foreach ($xml->sheetData->row as $r) {
                    $row_data = [];
                    foreach ($r->c as $c) {
                        $val = (string)$c->v;
                        $type = (string)$c['t'];
                        $row_data[] = ($type === 's' && isset($shared_strings[(int)$val])) ? $shared_strings[(int)$val] : $val;
                    }
                    if (!empty($row_data)) $rows[] = $row_data;
                }
            }
        }
        $zip->close();
    }
    return $rows;
}

/**
 * 5만 도엽 Base DXF 완전 무결 파서 (모든 도곽을 100% 로드하고 원본 텍스트 매핑)
 */
function extract_base_map_sheets($crs_type, $eo_min_x, $eo_max_x, $eo_min_y, $eo_max_y, &$debug_info = []) {
    $crs_map = [
        'EPSG:5186' => 'mid_50k.dxf',
        'EPSG:5187' => 'east_50k.dxf',
        'EPSG:5185' => 'west_50k.dxf'
    ];
    $base_filename = $crs_map[$crs_type] ?? 'mid_50k.dxf';
    
    // Windows 및 웹루트 다각도 경로 탐색
    $try_paths = [
        'C:\xampp\htdocs\imagery\project\base\\' . $base_filename,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . $base_filename,
        dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . $base_filename,
        'C:\xampp\htdocs\imagery\base\\' . $base_filename
    ];

    $base_filepath = '';
    foreach ($try_paths as $tp) {
        if (file_exists($tp) && is_readable($tp)) {
            $base_filepath = $tp;
            break;
        }
    }

    $debug_info['target_base_file'] = $base_filename;
    $debug_info['found_base_path']  = $base_filepath ?: 'NOT_FOUND';

    $sheets = [];
    if (!$base_filepath) {
        return $sheets;
    }

    // 파일 로드
    $lines = file($base_filepath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return $sheets;

    $total_lines = count($lines);
    $in_entities = false;
    
    $polygons = [];
    $sheet_numbers = [];
    $sheet_names = [];

    $cur_entity = '';
    $cur_layer = '';
    $cur_text = '';
    $x10 = null; $y20 = null;
    $x11 = null; $y21 = null;
    $poly_pts_x = [];
    $poly_pts_y = [];

    // 토큰 상태 머신 파싱
    for ($i = 0; $i < $total_lines; $i += 2) {
        if (!isset($lines[$i])) break;
        $code = trim($lines[$i]);
        $val  = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';

        if ($code === '0') {
            if ($in_entities) {
                if ($cur_entity === 'LWPOLYLINE' && !empty($poly_pts_x)) {
                    $polygons[] = [
                        'min_x' => min($poly_pts_x), 'max_x' => max($poly_pts_x),
                        'min_y' => min($poly_pts_y), 'max_y' => max($poly_pts_y)
                    ];
                } else if ($cur_entity === 'TEXT' && $cur_text !== '') {
                    $tx = ($x11 !== null && $x11 != 0) ? $x11 : $x10;
                    $ty = ($y21 !== null && $y21 != 0) ? $y21 : $y20;
                    
                    if ($tx !== null && $ty !== null) {
                        if (str_contains($cur_layer, 'N50000') || preg_match('/^\d{5}$/', $cur_text)) {
                            $sheet_numbers[] = ['x' => $tx, 'y' => $ty, 'text' => $cur_text];
                        } else if (str_contains($cur_layer, 'T50000') || preg_match('/[가-힣]/u', $cur_text)) {
                            $sheet_names[] = ['x' => $tx, 'y' => $ty, 'text' => $cur_text];
                        }
                    }
                }
            }

            if ($val === 'ENTITIES') { $in_entities = true; }
            if ($val === 'ENDSEC' && $in_entities) { break; }

            $cur_entity = $val;
            $cur_layer = '';
            $cur_text = '';
            $x10 = null; $y20 = null;
            $x11 = null; $y21 = null;
            $poly_pts_x = [];
            $poly_pts_y = [];
            continue;
        }

        if (!$in_entities) continue;

        if ($code === '8') {
            $cur_layer = strtoupper($val);
        } else if ($code === '10') {
            if ($cur_entity === 'LWPOLYLINE') $poly_pts_x[] = (float)$val;
            else $x10 = (float)$val;
        } else if ($code === '20') {
            if ($cur_entity === 'LWPOLYLINE') $poly_pts_y[] = (float)$val;
            else $y20 = (float)$val;
        } else if ($code === '11') {
            $x11 = (float)$val;
        } else if ($code === '21') {
            $y21 = (float)$val;
        } else if ($code === '1') {
            if (!mb_detect_encoding($val, 'UTF-8', true)) {
                $cur_text = trim(iconv('CP949', 'UTF-8//IGNORE', $val));
            } else {
                $cur_text = trim($val);
            }
        }
    }

    $debug_info['parsed_polygons'] = count($polygons);
    $debug_info['parsed_numbers']  = count($sheet_numbers);
    $debug_info['parsed_names']    = count($sheet_names);

    // [핵심] 필터링 없이 DXF에 존재하는 모든 도곽을 무조건 매핑
    foreach ($polygons as $p_idx => $poly) {
        $sheet_no = '';
        $sheet_nm = '';

        // N50000 매칭
        foreach ($sheet_numbers as $sn) {
            if ($sn['x'] >= $poly['min_x'] - 1000 && $sn['x'] <= $poly['max_x'] + 1000 && 
                $sn['y'] >= $poly['min_y'] - 1000 && $sn['y'] <= $poly['max_y'] + 1000) {
                $sheet_no = $sn['text'];
                break;
            }
        }

        // T50000 매칭
        foreach ($sheet_names as $sm) {
            if ($sm['x'] >= $poly['min_x'] - 2000 && $sm['x'] <= $poly['max_x'] + 2000 && 
                $sm['y'] >= $poly['min_y'] - 2000 && $sm['y'] <= $poly['max_y'] + 2000) {
                $sheet_nm = $sm['text'];
                break;
            }
        }

        // 번호나 이름이 누락된 도곽도 고유 식별자로 복구하여 무조건 노출
        $final_no = $sheet_no ?: ("50K_" . ($p_idx + 1));
        $final_nm = $sheet_nm ?: $final_no;

        // 주점 바운딩 박스와 실제 교차 여부 판별
        $is_direct = !($poly['max_x'] < $eo_min_x || $poly['min_x'] > $eo_max_x || 
                       $poly['max_y'] < $eo_min_y || $poly['min_y'] > $eo_max_y);

        $sheets[] = [
            'sheet_no'   => $final_no,
            'sheet_name' => $final_nm,
            'is_direct'  => $is_direct,
            'bounds'     => $poly
        ];
    }

    $debug_info['matched_sheets'] = count($sheets);
    return $sheets;
}

/**
 * EO 파일 파싱 함수
 */
function extract_eo_point_data($base_dir, $flight) {
    $date_str = trim($flight['flight_date']);
    $eo_dir = $base_dir . '\\date\\' . $date_str . '\\EO';
    $eo_target_file = '';

    if (!empty($flight['eo_file_name'])) {
        $try_file = $eo_dir . '\\' . $flight['eo_file_name'];
        if (file_exists($try_file)) $eo_target_file = $try_file;
    }

    if (!$eo_target_file && is_dir($eo_dir)) {
        $scanned = array_diff(scandir($eo_dir), ['.', '..']);
        if (!empty($scanned)) $eo_target_file = $eo_dir . '\\' . reset($scanned);
    }

    if (!$eo_target_file || !file_exists($eo_target_file)) {
        return ['success' => false, 'message' => "EO 성과 파일을 찾을 수 없습니다. 경로: {$eo_dir}"];
    }

    $ext = strtolower(pathinfo($eo_target_file, PATHINFO_EXTENSION));
    $raw_rows = [];

    if ($ext === 'xlsx') {
        $raw_rows = parse_xlsx_rows($eo_target_file);
    } else {
        $raw_content = file_get_contents($eo_target_file);
        $raw_content = preg_replace('/^\xEF\xBB\xBF/', '', $raw_content);
        if (!mb_detect_encoding($raw_content, 'UTF-8', true)) {
            $raw_content = iconv('CP949', 'UTF-8//IGNORE', $raw_content);
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw_content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || str_starts_with($line, '#') || str_starts_with($line, '//')) continue;
            
            if (str_contains($line, "\t")) $parts = explode("\t", $line);
            else if (str_contains($line, ",")) $parts = str_getcsv($line);
            else $parts = preg_split('/\s+/', $line);
            
            $parts = array_values(array_filter(array_map('trim', $parts), function($v) { return $v !== ''; }));
            if (!empty($parts)) $raw_rows[] = $parts;
        }
    }

    $idx_col = 0; $x_col = -1; $y_col = -1; $z_col = -1;
    foreach ($raw_rows as $cols) {
        foreach ($cols as $ci => $cval) {
            $val_lower = strtolower(trim((string)$cval));
            if (str_contains($val_lower, 'east') || $val_lower === 'x') $x_col = $ci;
            if (str_contains($val_lower, 'north') || $val_lower === 'y') $y_col = $ci;
            if (str_contains($val_lower, 'height') || str_contains($val_lower, 'msl') || $val_lower === 'z') $z_col = $ci;
            if (str_contains($val_lower, 'id') || str_contains($val_lower, 'photo')) $idx_col = $ci;
        }
        if ($x_col !== -1 && $y_col !== -1) break;
    }

    if ($x_col === -1 || $y_col === -1) {
        $sample = !empty($raw_rows[1]) ? $raw_rows[1] : (!empty($raw_rows[0]) ? $raw_rows[0] : []);
        if (count($sample) >= 5) { $x_col = 2; $y_col = 3; $z_col = 4; } 
        else if (count($sample) >= 4) { $x_col = 1; $y_col = 2; $z_col = 3; }
    }

    $entities = []; $main_count = 0; $reshoot_count = 0;
    $min_x = 999999999; $max_x = -999999999;
    $min_y = 999999999; $max_y = -999999999;

    foreach ($raw_rows as $cols) {
        if (empty($cols) || count($cols) <= max($x_col, $y_col, $idx_col)) continue;

        $id = trim((string)$cols[$idx_col]);
        if (!$id || preg_match('/^(id|photo|name|image|file|no|point)$/i', $id)) continue;
        if (stripos($id, 'gpstime') !== false || stripos($id, 'easting') !== false) continue;

        $x_raw = str_replace(',', '', trim((string)$cols[$x_col]));
        $y_raw = str_replace(',', '', trim((string)$cols[$y_col]));
        $z_raw = ($z_col !== -1 && isset($cols[$z_col])) ? str_replace(',', '', trim((string)$cols[$z_col])) : '0.000';

        if (is_numeric($x_raw) && is_numeric($y_raw)) {
            $x_val = (float)$x_raw; $y_val = (float)$y_raw; $z_val = is_numeric($z_raw) ? (float)$z_raw : 0.0;

            if (abs($x_val) > 1.0 && abs($y_val) > 1.0) {
                $is_reshoot = preg_match('/[a-zA-Z]/', $id);
                if ($is_reshoot) $reshoot_count++; else $main_count++;

                if ($x_val < $min_x) $min_x = $x_val;
                if ($x_val > $max_x) $max_x = $x_val;
                if ($y_val < $min_y) $min_y = $y_val;
                if ($y_val > $max_y) $max_y = $y_val;

                $entities[] = [
                    'id'   => $id, 'x' => $x_val, 'y' => $y_val, 'z' => $z_val,
                    'type' => $is_reshoot ? '재촬영' : '본촬영',
                    'is_reshoot' => (bool)$is_reshoot
                ];
            }
        }
    }

    return [
        'success' => true,
        'eo_filename' => basename($eo_target_file),
        'total' => count($entities),
        'main_count' => $main_count,
        'reshoot_count' => $reshoot_count,
        'entities' => $entities,
        'bbox' => ['min_x' => $min_x, 'max_x' => $max_x, 'min_y' => $min_y, 'max_y' => $max_y]
    ];
}

// -------------------------------------------------------------------------
// [AJAX] 1. 인덱스 생성 전 프리뷰 + 도곽 매핑
// -------------------------------------------------------------------------
if ($action === 'preview_index_data') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    try {
        $date_id = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
        $crs_type = isset($_GET['crs_type']) ? trim($_GET['crs_type']) : 'EPSG:5186';
        
        $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
        if (!$flight) {
            echo json_encode(['success' => false, 'message' => "촬영일 정보를 찾을 수 없습니다."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = extract_eo_point_data($base_dir, $flight);
        if (!$res['success']) {
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $debug_info = [];
        $bb = $res['bbox'];
        $res['map_sheets'] = extract_base_map_sheets($crs_type, $bb['min_x'], $bb['max_x'], $bb['min_y'], $bb['max_y'], $debug_info);
        $res['debug'] = $debug_info;

        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => 'PHP 오류: ' . $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// -------------------------------------------------------------------------
// 2. DXF 도면 생성
// -------------------------------------------------------------------------
if ($action === 'generate_index_dwg') {
    $date_id     = isset($_POST['date_id']) ? (int)$_POST['date_id'] : 0;
    $index_name  = isset($_POST['index_name']) ? trim($_POST['index_name']) : '';
    $crs_type    = isset($_POST['crs_type']) ? trim($_POST['crs_type']) : 'EPSG:5186';
    $selected_sheets_str = isset($_POST['selected_sheets']) ? trim($_POST['selected_sheets']) : '';

    if (!$date_id || !$index_name) {
        goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&err_msg='.urlencode('필수 파라미터가 누락되었습니다.'));
        exit;
    }

    if (!preg_match('/\.dxf$/i', $index_name)) $index_name = preg_replace('/\.[^.]+$/', '', $index_name) . '.dxf';

    $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    $parsed = extract_eo_point_data($base_dir, $flight);
    
    if (!$parsed['success'] || empty($parsed['entities'])) {
        goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&err_msg='.urlencode($parsed['message'] ?? '좌표 추출 실패'));
        exit;
    }

    $date_str = trim($flight['flight_date']);
    $date_compact = str_replace('-', '', substr($date_str, 5));

    $dxf = "0\nSECTION\n2\nHEADER\n9\n\$ACADVER\n1\nAC1009\n0\nENDSEC\n";
    $dxf .= "0\nSECTION\n2\nTABLES\n0\nTABLE\n2\nLAYER\n70\n6\n";
    $dxf .= "0\nLAYER\n2\n{$date_compact}_PP\n70\n0\n62\n1\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\n{$date_compact}_TT\n70\n0\n62\n7\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\n{$date_compact}_PP_A\n70\n0\n62\n2\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\n{$date_compact}_TT_A\n70\n0\n62\n2\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\nG50\n70\n0\n62\n7\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\nINDEX_50K\n70\n0\n62\n5\n6\nCONTINUOUS\n";
    $dxf .= "0\nENDTAB\n0\nENDSEC\n";
    $dxf .= "0\nSECTION\n2\nENTITIES\n";

    if ($selected_sheets_str) {
        $bb = $parsed['bbox'];
        $debug_dummy = [];
        $all_sheets = extract_base_map_sheets($crs_type, $bb['min_x'], $bb['max_x'], $bb['min_y'], $bb['max_y'], $debug_dummy);
        $sel_arr = explode(',', $selected_sheets_str);
        
        foreach ($all_sheets as $sheet) {
            if (in_array($sheet['sheet_no'], $sel_arr)) {
                $b = $sheet['bounds'];
                $dxf .= "0\nLINE\n8\nG50\n62\n7\n10\n{$b['min_x']}\n20\n{$b['min_y']}\n30\n0.0\n11\n{$b['max_x']}\n21\n{$b['min_y']}\n31\n0.0\n";
                $dxf .= "0\nLINE\n8\nG50\n62\n7\n10\n{$b['max_x']}\n20\n{$b['min_y']}\n30\n0.0\n11\n{$b['max_x']}\n21\n{$b['max_y']}\n31\n0.0\n";
                $dxf .= "0\nLINE\n8\nG50\n62\n7\n10\n{$b['max_x']}\n20\n{$b['max_y']}\n30\n0.0\n11\n{$b['min_x']}\n21\n{$b['max_y']}\n31\n0.0\n";
                $dxf .= "0\nLINE\n8\nG50\n62\n7\n10\n{$b['min_x']}\n20\n{$b['max_y']}\n30\n0.0\n11\n{$b['min_x']}\n21\n{$b['min_y']}\n31\n0.0\n";
                
                $cx = ($b['min_x'] + $b['max_x']) / 2;
                $cy = ($b['min_y'] + $b['max_y']) / 2;
                
                $dxf .= "0\nTEXT\n8\nINDEX_50K\n62\n5\n10\n{$cx}\n20\n".($cy + 800)."\n30\n0.0\n40\n1500.0\n1\n{$sheet['sheet_name']}\n50\n0.0\n7\nSTANDARD\n";
                $dxf .= "0\nTEXT\n8\nINDEX_50K\n62\n5\n10\n{$cx}\n20\n".($cy - 800)."\n30\n0.0\n40\n1200.0\n1\n{$sheet['sheet_no']}\n50\n0.0\n7\nSTANDARD\n";
            }
        }
    }

    foreach ($parsed['entities'] as $pt) {
        $id = $pt['id'];
        $x = sprintf("%.4f", $pt['x']); $y = sprintf("%.4f", $pt['y']);
        $is_reshoot = $pt['is_reshoot'];

        $layer_pp = $is_reshoot ? "{$date_compact}_PP_A" : "{$date_compact}_PP";
        $layer_tt = $is_reshoot ? "{$date_compact}_TT_A" : "{$date_compact}_TT";
        $c_val = $is_reshoot ? 2 : 1;
        $t_val = $is_reshoot ? 2 : 7;

        $dxf .= "0\nCIRCLE\n8\n{$layer_pp}\n62\n{$c_val}\n10\n{$x}\n20\n{$y}\n30\n0.0\n40\n50.0\n";
        $dxf .= "0\nTEXT\n8\n{$layer_tt}\n62\n{$t_val}\n10\n{$x}\n20\n{$y}\n30\n0.0\n40\n150.0\n1\n{$id}\n50\n45.0\n7\nSTANDARD\n";
    }
    $dxf .= "0\nENDSEC\n0\nEOF\n";

    $index_save_dir = $base_dir . '\\date\\' . $date_str . '\\INDEX';
    if (!is_dir($index_save_dir)) @mkdir($index_save_dir, 0777, true);

    $save_full_path = $index_save_dir . '\\' . $index_name;
    $write_res = @file_put_contents($save_full_path, $dxf);

    if ($write_res === false || !file_exists($save_full_path)) {
        goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&err_msg='.urlencode('디스크 파일 저장 실패'));
        exit;
    }

    sql_query(" UPDATE IMG_FLIGHT_INDEX SET is_active = 0 WHERE prj_id = {$prj_id} AND date_id = {$date_id} ");
    $current_user = $member['mb_name'] ? $member['mb_name'] : $member['mb_nick'];
    sql_query(" INSERT INTO IMG_FLIGHT_INDEX 
                SET prj_id = {$prj_id}, date_id = {$date_id}, idx_name = '".sql_real_escape_string($index_name)."',
                    file_name = '".sql_real_escape_string($index_name)."', file_path = '".sql_real_escape_string($save_full_path)."',
                    is_active = 1, photo_count = ".$parsed['total'].", mb_id = '".sql_real_escape_string($member['mb_id'])."',
                    mb_name = '".sql_real_escape_string($current_user)."', created_at = NOW() ");

    goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&active_file='.urlencode($index_name).'&toast=index_ok');
}