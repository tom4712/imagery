<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * 엑셀(.xlsx) 파일 내부 시트 데이터 추출 파서
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
                        if ($type === 's' && isset($shared_strings[(int)$val])) {
                            $row_data[] = $shared_strings[(int)$val];
                        } else {
                            $row_data[] = $val;
                        }
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
 * 공통 EO 파일 파싱 함수 (ID, X, Y, Z, 구분 추출)
 */
function extract_eo_point_data($base_dir, $flight) {
    $date_str = trim($flight['flight_date']);
    $eo_dir = $base_dir . '\\date\\' . $date_str . '\\EO';
    $eo_target_file = '';

    if (!empty($flight['eo_file_name'])) {
        $try_file = $eo_dir . '\\' . $flight['eo_file_name'];
        if (file_exists($try_file)) {
            $eo_target_file = $try_file;
        }
    }

    if (!$eo_target_file && is_dir($eo_dir)) {
        $scanned = array_diff(scandir($eo_dir), ['.', '..']);
        if (!empty($scanned)) {
            $eo_target_file = $eo_dir . '\\' . reset($scanned);
        }
    }

    if (!$eo_target_file || !file_exists($eo_target_file)) {
        return ['success' => false, 'message' => 'EO 성과 파일이 존재하지 않습니다.'];
    }

    $ext = strtolower(pathinfo($eo_target_file, PATHINFO_EXTENSION));
    $raw_rows = [];

    if ($ext === 'xlsx') {
        $raw_rows = parse_xlsx_rows($eo_target_file);
    } else {
        $raw_content = file_get_contents($eo_target_file);
        $raw_content = preg_replace('/^\xEF\xBB\xBF/', '', $raw_content); // UTF-8 BOM 제거
        
        if (!mb_detect_encoding($raw_content, 'UTF-8', true)) {
            $raw_content = iconv('CP949', 'UTF-8//IGNORE', $raw_content);
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw_content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || str_starts_with($line, '#') || str_starts_with($line, '//')) continue;
            
            if (str_contains($line, "\t")) {
                $parts = explode("\t", $line);
            } else if (str_contains($line, ",")) {
                $parts = str_getcsv($line);
            } else {
                $parts = preg_split('/\s+/', $line);
            }
            $parts = array_values(array_filter(array_map('trim', $parts), function($v) { return $v !== ''; }));
            if (!empty($parts)) $raw_rows[] = $parts;
        }
    }

    $idx_col = 0; $x_col = -1; $y_col = -1; $z_col = -1;

    // 헤더 자동 탐색
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

    // 기본 인덱스 매핑 (ID, GPSTime, Easting, Northing, MSLHt)
    if ($x_col === -1 || $y_col === -1) {
        $sample = !empty($raw_rows[1]) ? $raw_rows[1] : (!empty($raw_rows[0]) ? $raw_rows[0] : []);
        if (count($sample) >= 5) {
            $x_col = 2; $y_col = 3; $z_col = 4;
        } else if (count($sample) >= 4) {
            $x_col = 1; $y_col = 2; $z_col = 3;
        }
    }

    $entities = [];
    $main_count = 0;
    $reshoot_count = 0;

    foreach ($raw_rows as $cols) {
        if (empty($cols) || count($cols) <= max($x_col, $y_col, $idx_col)) continue;

        $id = trim((string)$cols[$idx_col]);
        if (!$id || preg_match('/^(id|photo|name|image|file|no|point)$/i', $id)) continue;
        if (stripos($id, 'gpstime') !== false || stripos($id, 'easting') !== false) continue;

        $x_raw = str_replace(',', '', trim((string)$cols[$x_col]));
        $y_raw = str_replace(',', '', trim((string)$cols[$y_col]));
        $z_raw = ($z_col !== -1 && isset($cols[$z_col])) ? str_replace(',', '', trim((string)$cols[$z_col])) : '0.000';

        if (is_numeric($x_raw) && is_numeric($y_raw)) {
            $x_val = (float)$x_raw;
            $y_val = (float)$y_raw;
            $z_val = is_numeric($z_raw) ? (float)$z_raw : 0.0;

            if (abs($x_val) > 1.0 && abs($y_val) > 1.0) {
                $is_reshoot = preg_match('/[a-zA-Z]/', $id);
                if ($is_reshoot) $reshoot_count++;
                else $main_count++;

                $entities[] = [
                    'id'   => $id,
                    'x'    => $x_val,
                    'y'    => $y_val,
                    'z'    => $z_val,
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
        'entities' => $entities
    ];
}

// -------------------------------------------------------------------------
// [AJAX] 1. 인덱스 생성 전 ID/X/Y/Z/구분 프리뷰 데이터 반환
// -------------------------------------------------------------------------
if ($action === 'preview_index_data') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $date_id = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
    $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    
    if (!$flight) {
        echo json_encode(['success' => false, 'message' => '촬영일 정보를 찾을 수 없습니다.']);
        exit;
    }

    $res = extract_eo_point_data($base_dir, $flight);
    echo json_encode($res);
    exit;
}

// -------------------------------------------------------------------------
// 2. 최종 확인 후 DXF 도면 생성 및 물리 파일 쓰기 (PHP 8.2 직접 저장)
// -------------------------------------------------------------------------
if ($action === 'generate_index_dwg') {
    $date_id     = isset($_POST['date_id']) ? (int)$_POST['date_id'] : 0;
    $index_name  = isset($_POST['index_name']) ? trim($_POST['index_name']) : '';
    $crs_type    = isset($_POST['crs_type']) ? trim($_POST['crs_type']) : 'EPSG:5186';

    if (!$date_id || !$index_name) {
        goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&err_msg='.urlencode('필수 파라미터가 누락되었습니다.'));
        exit;
    }

    if (!preg_match('/\.dxf$/i', $index_name)) {
        $index_name = preg_replace('/\.[^.]+$/', '', $index_name) . '.dxf';
    }

    $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    if (!$flight) {
        goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&err_msg='.urlencode('촬영일 정보를 찾을 수 없습니다.'));
        exit;
    }

    $parsed = extract_eo_point_data($base_dir, $flight);
    if (!$parsed['success'] || empty($parsed['entities'])) {
        goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&err_msg='.urlencode($parsed['message'] ?? '좌표 추출 실패'));
        exit;
    }

    $date_str = trim($flight['flight_date']);
    $date_compact = str_replace('-', '', substr($date_str, 5)); // '0826'

    // DXF R12 스트림 빌드
    $dxf = "0\nSECTION\n2\nHEADER\n9\n\$ACADVER\n1\nAC1009\n0\nENDSEC\n";
    $dxf .= "0\nSECTION\n2\nTABLES\n0\nTABLE\n2\nLAYER\n70\n4\n";
    $dxf .= "0\nLAYER\n2\n{$date_compact}_PP\n70\n0\n62\n1\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\n{$date_compact}_TT\n70\n0\n62\n7\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\n{$date_compact}_PP_A\n70\n0\n62\n2\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\n{$date_compact}_TT_A\n70\n0\n62\n2\n6\nCONTINUOUS\n";
    $dxf .= "0\nENDTAB\n0\nENDSEC\n";
    $dxf .= "0\nSECTION\n2\nENTITIES\n";

    foreach ($parsed['entities'] as $pt) {
        $id = $pt['id'];
        $x = sprintf("%.4f", $pt['x']);
        $y = sprintf("%.4f", $pt['y']);
        $is_reshoot = $pt['is_reshoot'];

        if ($is_reshoot) {
            $layer_pp = "{$date_compact}_PP_A";
            $layer_tt = "{$date_compact}_TT_A";
            $color_pp = 2; // 노랑
            $color_tt = 2; // 노랑
        } else {
            $layer_pp = "{$date_compact}_PP";
            $layer_tt = "{$date_compact}_TT";
            $color_pp = 1; // 빨강
            $color_tt = 7; // 흰색
        }

        // CIRCLE (반지름 50)
        $dxf .= "0\nCIRCLE\n8\n{$layer_pp}\n62\n{$color_pp}\n10\n{$x}\n20\n{$y}\n30\n0.0\n40\n50.0\n";
        // TEXT (크기 150, 회전 45, STANDARD)
        $dxf .= "0\nTEXT\n8\n{$layer_tt}\n62\n{$color_tt}\n10\n{$x}\n20\n{$y}\n30\n0.0\n40\n150.0\n1\n{$id}\n50\n45.0\n7\nSTANDARD\n";
    }
    $dxf .= "0\nENDSEC\n0\nEOF\n";

    // 1. 디렉토리 생성 (PHP 8.2 네이티브 UTF-8)
    $index_save_dir = $base_dir . '\\date\\' . $date_str . '\\INDEX';
    if (!is_dir($index_save_dir)) {
        @mkdir($index_save_dir, 0777, true);
    }

    // 2. 물리 파일 저장
    $save_full_path = $index_save_dir . '\\' . $index_name;
    $write_res = @file_put_contents($save_full_path, $dxf);

    if ($write_res === false || !file_exists($save_full_path)) {
        $err = error_get_last();
        $err_msg = '디스크 파일 저장 실패: ' . ($err['message'] ?? '권한 또는 경로 오류');
        goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&err_msg='.urlencode($err_msg));
        exit;
    }

    // 3. DB 등록 및 대표 활성화
    sql_query(" UPDATE IMG_FLIGHT_INDEX SET is_active = 0 WHERE prj_id = {$prj_id} AND date_id = {$date_id} ");
    
    $current_user = $member['mb_name'] ? $member['mb_name'] : $member['mb_nick'];
    sql_query(" INSERT INTO IMG_FLIGHT_INDEX 
                SET prj_id = {$prj_id},
                    date_id = {$date_id},
                    idx_name = '".sql_real_escape_string($index_name)."',
                    file_name = '".sql_real_escape_string($index_name)."',
                    file_path = '".sql_real_escape_string($save_full_path)."',
                    is_active = 1,
                    photo_count = ".$parsed['total'].",
                    mb_id = '".sql_real_escape_string($member['mb_id'])."',
                    mb_name = '".sql_real_escape_string($current_user)."',
                    created_at = NOW() ");

    goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&date_id='.$date_id.'&active_file='.urlencode($index_name).'&toast=index_ok');
}