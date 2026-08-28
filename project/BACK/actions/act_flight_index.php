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
                        $col_idx = function_exists('img_xlsx_col_index') ? img_xlsx_col_index((string)$c['r']) : (count($row_data) + 1);
                        if ($col_idx <= 0) $col_idx = count($row_data) + 1;

                        if (function_exists('img_xlsx_cell_value')) {
                            $row_data[$col_idx - 1] = img_xlsx_cell_value($c, $shared_strings);
                        } else {
                            $val = (string)$c->v;
                            $type = (string)$c['t'];
                            $row_data[$col_idx - 1] = ($type === 's' && isset($shared_strings[(int)$val])) ? $shared_strings[(int)$val] : $val;
                        }
                    }
                    if (!empty($row_data)) {
                        ksort($row_data);
                        $rows[] = $row_data;
                    }
                }
            }
        }
        $zip->close();
    }
    return $rows;
}

function eo_inspect_result_col_index($rows) {
    if (!empty($rows[0]) && is_array($rows[0])) {
        $lon_idx = -1;
        foreach ($rows[0] as $idx => $header) {
            $value = strtolower(trim((string)$header));
            if ($value === '검수결과' || $value === 'inspection' || str_contains($value, 'inspect')) return (int)$idx;
            if ($value === 'lon(deg)' || $value === 'lon' || str_contains($value, 'longitude')) $lon_idx = (int)$idx;
        }
        if ($lon_idx >= 0) return $lon_idx + 1;
    }

    return 10;
}

function eo_inspect_result_value($cols, $inspect_col_idx) {
    $primary = isset($cols[$inspect_col_idx]) ? trim((string)$cols[$inspect_col_idx]) : '';
    if ($primary !== '') return $primary;

    for ($i = 8; $i < count($cols); $i++) {
        $candidate = trim((string)($cols[$i] ?? ''));
        if (str_starts_with($candidate, '재촬영') || str_starts_with($candidate, '중복미사용') || str_starts_with($candidate, '미사용')) {
            return $candidate;
        }
    }

    return '';
}

function eo_target_file_path($base_dir, $flight) {
    $date_str = trim($flight['flight_date']);
    $eo_dir = $base_dir . '\\date\\' . $date_str . '\\EO';
    $eo_target_file = '';

    $eo_names = array_filter(array_map('trim', explode(',', (string)($flight['eo_file_name'] ?? ''))));
    foreach ($eo_names as $eo_name) {
        $try_file = $eo_dir . '\\' . basename($eo_name);
        if (file_exists($try_file)) {
            $eo_target_file = $try_file;
            break;
        }
    }

    if (!$eo_target_file && is_dir($eo_dir)) {
        $scanned = array_diff(scandir($eo_dir), ['.', '..']);
        foreach ($scanned as $file_name) {
            $try_file = $eo_dir . '\\' . $file_name;
            if (is_file($try_file)) {
                $eo_target_file = $try_file;
                break;
            }
        }
    }

    return $eo_target_file;
}

function eo_raw_rows_from_file($eo_target_file) {
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

    return $raw_rows;
}

function eo_detect_columns($raw_rows) {
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

    return ['idx' => $idx_col, 'x' => $x_col, 'y' => $y_col, 'z' => $z_col];
}

function eo_distance($a, $b) {
    return sqrt(pow($a['x'] - $b['x'], 2) + pow($a['y'] - $b['y'], 2));
}

function eo_median_value($values, $fallback) {
    $values = array_values(array_filter(array_map('floatval', (array)$values), function($v) {
        return $v > 0.0001;
    }));
    if (empty($values)) return $fallback;
    sort($values, SORT_NUMERIC);
    $mid = (int)floor(count($values) / 2);
    if (count($values) % 2) return $values[$mid];
    return ($values[$mid - 1] + $values[$mid]) / 2;
}

function eo_rectangle_polyline($center, $ux, $uy, $vx, $vy, $half_w, $half_h) {
    return [
        ['x' => $center['x'] - $ux * $half_w - $vx * $half_h, 'y' => $center['y'] - $uy * $half_w - $vy * $half_h],
        ['x' => $center['x'] + $ux * $half_w - $vx * $half_h, 'y' => $center['y'] + $uy * $half_w - $vy * $half_h],
        ['x' => $center['x'] + $ux * $half_w + $vx * $half_h, 'y' => $center['y'] + $uy * $half_w + $vy * $half_h],
        ['x' => $center['x'] - $ux * $half_w + $vx * $half_h, 'y' => $center['y'] - $uy * $half_w + $vy * $half_h],
    ];
}

function eo_layer_prefix_from_date($flight_date) {
    if (preg_match('/^\s*(\d{4})[-_\.](\d{2})[-_\.](\d{2})\s*$/', (string)$flight_date, $m)) {
        return $m[2] . $m[3];
    }

    $prefix = preg_replace('/[^A-Za-z0-9_]/', '_', trim((string)$flight_date));
    return $prefix !== '' ? $prefix : 'EO';
}

/**
 * CP949 인코딩 변환 헬퍼 (병합 DXF 출력용)
 */
function to_cp949($utf8_str) {
    $r = @iconv('UTF-8', 'CP949//IGNORE', (string)$utf8_str);
    return ($r !== false) ? $r : $utf8_str;
}

/**
 * 5만 도엽 Base DXF 파서
 */
function extract_base_map_sheets($crs_type, $eo_min_x, $eo_max_x, $eo_min_y, $eo_max_y, &$debug_info = []) {
    $crs_map = [
        'EPSG:5186' => 'mid_50k.dxf',
        'EPSG:5187' => 'east_50k.dxf',
        'EPSG:5185' => 'west_50k.dxf'
    ];
    $base_filename = $crs_map[$crs_type] ?? 'mid_50k.dxf';

    $try_paths = [
        'C:\xampp\htdocs\imagery\project\base\\' . $base_filename,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . $base_filename,
        dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . $base_filename,
        'C:\xampp\htdocs\imagery\base\\' . $base_filename
    ];

    $base_filepath = '';
    foreach ($try_paths as $tp) {
        if (file_exists($tp) && is_readable($tp)) { $base_filepath = $tp; break; }
    }

    $debug_info['target_base_file'] = $base_filename;
    $debug_info['found_base_path']  = $base_filepath ?: 'NOT_FOUND';

    $sheets = [];
    if (!$base_filepath) return $sheets;

    $lines = file($base_filepath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return $sheets;
    $total_lines = count($lines);

    $current_section = '';
    $polygons = [];
    $sheet_numbers = [];
    $sheet_names = [];

    $cur_entity = '';
    $cur_layer  = '';
    $cur_text   = '';
    $x10 = null; $y20 = null;
    $x11 = null; $y21 = null;
    $cur_height = null;
    $cur_rot    = null;
    $poly_pts = [];

    $flush = function() use (
        &$cur_entity, &$cur_layer, &$cur_text, &$x10, &$y20, &$x11, &$y21,
        &$cur_height, &$cur_rot, &$poly_pts, &$polygons, &$sheet_numbers, &$sheet_names
    ) {
        if ($cur_entity === 'LWPOLYLINE' && $cur_layer === 'G50' && !empty($poly_pts)) {
            $xs = array_column($poly_pts, 0);
            $ys = array_column($poly_pts, 1);
            $polygons[] = [
                'min_x' => min($xs), 'max_x' => max($xs),
                'min_y' => min($ys), 'max_y' => max($ys),
                'points' => $poly_pts
            ];
        } else if ($cur_entity === 'TEXT' && $cur_text !== '') {
            $tx = ($x11 !== null && $x11 != 0) ? $x11 : $x10;
            $ty = ($y21 !== null && $y21 != 0) ? $y21 : $y20;
            if ($tx !== null && $ty !== null) {
                $rec = [
                    'x' => $tx, 'y' => $ty, 'text' => $cur_text,
                    'height'   => $cur_height ?? 1000.0,
                    'rotation' => $cur_rot ?? 0.0
                ];
                if ($cur_layer === 'N50000' || (!$cur_layer && preg_match('/^\d{5}$/', $cur_text))) {
                    $sheet_numbers[] = $rec;
                } else if ($cur_layer === 'T50000' || preg_match('/[가-힣]/u', $cur_text)) {
                    $sheet_names[] = $rec;
                }
            }
        }
    };

    for ($i = 0; $i < $total_lines; $i += 2) {
        if (!isset($lines[$i])) break;
        $code = trim($lines[$i]);
        $val  = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';

        if ($code === '2' && $current_section === 'SECTION_INIT') {
            $current_section = strtoupper($val);
            continue;
        }

        if ($code === '0') {
            if ($current_section === 'ENTITIES') $flush();

            if ($val === 'SECTION') {
                $current_section = 'SECTION_INIT';
            } else if ($val === 'ENDSEC') {
                if ($current_section === 'ENTITIES') break;
                $current_section = '';
            }

            $cur_entity = $val;
            $cur_layer  = '';
            $cur_text   = '';
            $x10 = null; $y20 = null;
            $x11 = null; $y21 = null;
            $cur_height = null; $cur_rot = null;
            $poly_pts = [];
            continue;
        }

        if ($current_section !== 'ENTITIES') continue;

        if ($code === '8') {
            $cur_layer = strtoupper($val);
        } else if ($code === '10') {
            if ($cur_entity === 'LWPOLYLINE') {
                $poly_pts[] = [(float)$val, null];
            } else {
                $x10 = (float)$val;
            }
        } else if ($code === '20') {
            if ($cur_entity === 'LWPOLYLINE') {
                $last = count($poly_pts) - 1;
                if ($last >= 0) $poly_pts[$last][1] = (float)$val;
            } else {
                $y20 = (float)$val;
            }
        } else if ($code === '11') {
            $x11 = (float)$val;
        } else if ($code === '21') {
            $y21 = (float)$val;
        } else if ($code === '40') {
            $cur_height = (float)$val;
        } else if ($code === '50') {
            $cur_rot = (float)$val;
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

    foreach ($polygons as $p_idx => $poly) {
        $sheet_no = ''; $sheet_nm = '';
        $no_pos = null; $nm_pos = null;

        // 💡 [수정] 폴리곤 중심점에 가장 가까운 도엽번호 및 도엽명 정확 매칭 (오버랩 범위 및 엄정 덮어쓰기 방지)
        $poly_center_x = ($poly['min_x'] + $poly['max_x']) / 2;
        $poly_center_y = ($poly['min_y'] + $poly['max_y']) / 2;

        foreach ($sheet_numbers as $sn) {
            if ($sn['x'] >= $poly['min_x'] && $sn['x'] <= $poly['max_x'] &&
                $sn['y'] >= $poly['min_y'] && $sn['y'] <= $poly['max_y']) {
                $sheet_no = $sn['text'];
                $no_pos = ['x' => $sn['x'], 'y' => $sn['y'], 'height' => $sn['height'], 'rotation' => $sn['rotation']];
                break; // 매칭 성공 시 즉시 탈출
            }
        }

        foreach ($sheet_names as $sm) {
            if ($sm['x'] >= $poly['min_x'] && $sm['x'] <= $poly['max_x'] &&
                $sm['y'] >= $poly['min_y'] && $sm['y'] <= $poly['max_y']) {
                $sheet_nm = $sm['text'];
                $nm_pos = ['x' => $sm['x'], 'y' => $sm['y'], 'height' => $sm['height'], 'rotation' => $sm['rotation']];
                break; // 매칭 성공 시 즉시 탈출
            }
        }

        $final_no = $sheet_no ?: ("50K_" . ($p_idx + 1));
        $final_nm = $sheet_nm ?: $final_no;

        $is_direct = !($poly['max_x'] < $eo_min_x || $poly['min_x'] > $eo_max_x ||
                       $poly['max_y'] < $eo_min_y || $poly['min_y'] > $eo_max_y);

        $sheets[] = [
            'sheet_no'    => $final_no,
            'sheet_name'  => $final_nm,
            'is_direct'   => $is_direct,
            'bounds'      => ['min_x' => $poly['min_x'], 'max_x' => $poly['max_x'], 'min_y' => $poly['min_y'], 'max_y' => $poly['max_y']],
            'poly_points' => array_map(function ($p) { return ['x' => $p[0], 'y' => $p[1]]; }, $poly['points']),
            'no_pos'      => $no_pos,
            'name_pos'    => $nm_pos
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
    $eo_target_file = eo_target_file_path($base_dir, $flight);

    if (!$eo_target_file || !file_exists($eo_target_file)) {
        return ['success' => false, 'message' => "EO 성과 파일을 찾을 수 없습니다. 경로: {$eo_dir}"];
    }

    $raw_rows = eo_raw_rows_from_file($eo_target_file);
    $cols_map = eo_detect_columns($raw_rows);
    $idx_col = $cols_map['idx'];
    $x_col = $cols_map['x'];
    $y_col = $cols_map['y'];
    $z_col = $cols_map['z'];

    $inspect_col_idx = eo_inspect_result_col_index($raw_rows);
    $reshoot_window_marks = function_exists('img_eo_reshoot_window_marks_from_rows')
        ? img_eo_reshoot_window_marks_from_rows($raw_rows, $idx_col)
        : ['overlap' => [], 'actual' => []];
    $entities = []; $main_count = 0; $reshoot_count = 0;
    $min_x = 999999999; $max_x = -999999999;
    $min_y = 999999999; $max_y = -999999999;

    foreach ($raw_rows as $cols) {
        if (empty($cols) || count($cols) <= max($x_col, $y_col, $idx_col)) continue;

        $id = trim((string)$cols[$idx_col]);
        if (!$id || preg_match('/^(id|photo|name|image|file|no|point)$/i', $id)) continue;
        if (stripos($id, 'gpstime') !== false || stripos($id, 'easting') !== false) continue;

        $shot_parts = function_exists('img_eo_shot_parts') ? img_eo_shot_parts($id) : null;
        $is_original_overlap = false;
        $is_original_actual_reshoot = false;
        $has_a_actual_window = false;
        if ($shot_parts && $shot_parts['suffix'] === '') {
            $is_original_overlap = isset($reshoot_window_marks['overlap'][$shot_parts['course_no']][$shot_parts['shot_no']]);
            $is_original_actual_reshoot = isset($reshoot_window_marks['actual'][$shot_parts['course_no']][$shot_parts['shot_no']]);
            $has_a_actual_window = !empty($reshoot_window_marks['actual'][$shot_parts['course_no']]);
        }

        // Lon(deg) 오른쪽 검수결과 컬럼에 재촬영/중복미사용으로 마킹된 사진은 인덱스 생성에서 제외
        $inspect_result = eo_inspect_result_value($cols, $inspect_col_idx);
        $is_reshoot_marked = str_starts_with($inspect_result, '재촬영');
        $is_unused_marked = str_starts_with($inspect_result, '중복미사용') || str_starts_with($inspect_result, '미사용');
        $exclude_reshoot = false;
        if ($is_reshoot_marked && $shot_parts && $shot_parts['suffix'] === '') {
            $exclude_reshoot = $has_a_actual_window ? $is_original_actual_reshoot : !$is_original_overlap;
        }
        if ($exclude_reshoot || $is_unused_marked) {
            continue;
        }

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
                    'layer_prefix' => eo_layer_prefix_from_date($date_str),
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
        'reshoot_zones' => extract_eo_reshoot_zones($base_dir, $flight),
        'entities' => $entities,
        'bbox' => ['min_x' => $min_x, 'max_x' => $max_x, 'min_y' => $min_y, 'max_y' => $max_y]
    ];
}

function extract_eo_reshoot_zones($base_dir, $flight, $course_map = null) {
    $eo_target_file = eo_target_file_path($base_dir, $flight);
    if (!$eo_target_file || !file_exists($eo_target_file)) return [];

    $raw_rows = eo_raw_rows_from_file($eo_target_file);
    if (empty($raw_rows)) return [];

    $cols_map = eo_detect_columns($raw_rows);
    $idx_col = $cols_map['idx'];
    $x_col = $cols_map['x'];
    $y_col = $cols_map['y'];
    if ($x_col === -1 || $y_col === -1) return [];

    $inspect_col_idx = eo_inspect_result_col_index($raw_rows);
    $reshoot_window_marks = function_exists('img_eo_reshoot_window_marks_from_rows')
        ? img_eo_reshoot_window_marks_from_rows($raw_rows, $idx_col)
        : ['overlap' => [], 'actual' => []];
    $by_course = [];
    $reshoot_shots = [];

    foreach ($raw_rows as $cols) {
        if (empty($cols) || count($cols) <= max($x_col, $y_col, $idx_col)) continue;

        $id = trim((string)$cols[$idx_col]);
        if (!$id || preg_match('/^(id|photo|name|image|file|no|point)$/i', $id)) continue;
        if (stripos($id, 'gpstime') !== false || stripos($id, 'easting') !== false) continue;

        $parts = function_exists('img_eo_shot_parts') ? img_eo_shot_parts($id) : null;
        if (!$parts || $parts['course_no'] <= 0 || $parts['shot_no'] <= 0) continue;
        if (is_array($course_map) && !isset($course_map[$parts['course_no']])) continue;

        $x_raw = str_replace(',', '', trim((string)$cols[$x_col]));
        $y_raw = str_replace(',', '', trim((string)$cols[$y_col]));
        if (!is_numeric($x_raw) || !is_numeric($y_raw)) continue;

        $x_val = (float)$x_raw;
        $y_val = (float)$y_raw;
        if (abs($x_val) <= 1.0 || abs($y_val) <= 1.0) continue;

        $course_no = $parts['course_no'];
        $shot_no = $parts['shot_no'];
        $suffix = strtoupper((string)$parts['suffix']);
        $inspect_result = eo_inspect_result_value($cols, $inspect_col_idx);

        if ($suffix === '') {
            $by_course[$course_no][$shot_no] = ['x' => $x_val, 'y' => $y_val, 'shot_no' => $shot_no];
            $has_a_actual_window = !empty($reshoot_window_marks['actual'][$course_no]);
            $is_actual = isset($reshoot_window_marks['actual'][$course_no][$shot_no]);
            $is_overlap = isset($reshoot_window_marks['overlap'][$course_no][$shot_no]);
            if (str_starts_with($inspect_result, '재촬영') && ($has_a_actual_window ? ($is_actual || $is_overlap) : true)) {
                $reshoot_shots[$course_no][$shot_no] = true;
            }
        }
    }

    $course_centers = [];
    foreach ($by_course as $course_no => $points) {
        $sx = 0; $sy = 0; $cnt = 0;
        foreach ($points as $p) {
            $sx += $p['x'];
            $sy += $p['y'];
            $cnt++;
        }
        if ($cnt > 0) $course_centers[$course_no] = ['x' => $sx / $cnt, 'y' => $sy / $cnt];
        ksort($by_course[$course_no], SORT_NUMERIC);
    }

    $course_gaps = [];
    $course_keys = array_keys($course_centers);
    sort($course_keys, SORT_NUMERIC);
    for ($i = 1; $i < count($course_keys); $i++) {
        $course_gaps[] = eo_distance($course_centers[$course_keys[$i - 1]], $course_centers[$course_keys[$i]]);
    }

    $zones = [];
    foreach ($reshoot_shots as $course_no => $shots_map) {
        if (empty($by_course[$course_no])) continue;
        $points = $by_course[$course_no];
        $shots = array_keys($shots_map);
        sort($shots, SORT_NUMERIC);

        $ranges = [];
        foreach ($shots as $shot_no) {
            if (empty($ranges) || $shot_no > $ranges[count($ranges) - 1]['end'] + 1) {
                $ranges[] = ['start' => $shot_no, 'end' => $shot_no];
            } else {
                $ranges[count($ranges) - 1]['end'] = $shot_no;
            }
        }

        $expanded_ranges = [];
        foreach ($ranges as $range) {
            $expanded = [
                'start' => max(1, $range['start'] - 3),
                'end' => $range['end'] + 3,
                'actual_start' => $range['start'],
                'actual_end' => $range['end']
            ];

            $last_idx = count($expanded_ranges) - 1;
            if ($last_idx < 0 || $expanded['start'] > $expanded_ranges[$last_idx]['end'] + 1) {
                $expanded_ranges[] = $expanded;
            } else {
                $expanded_ranges[$last_idx]['end'] = max($expanded_ranges[$last_idx]['end'], $expanded['end']);
                $expanded_ranges[$last_idx]['actual_end'] = max($expanded_ranges[$last_idx]['actual_end'], $expanded['actual_end']);
            }
        }
        $ranges = $expanded_ranges;

        $shot_gaps = [];
        $point_keys = array_keys($points);
        sort($point_keys, SORT_NUMERIC);
        for ($i = 1; $i < count($point_keys); $i++) {
            $shot_gaps[] = eo_distance($points[$point_keys[$i - 1]], $points[$point_keys[$i]]);
        }
        $along_gap = eo_median_value($shot_gaps, 200.0);
        $cross_gap = eo_median_value($course_gaps, $along_gap * 2.0);

        foreach ($ranges as $range) {
            $range_pts = [];
            foreach ($points as $shot_no => $p) {
                if ($shot_no >= $range['start'] && $shot_no <= $range['end']) $range_pts[] = $p;
            }
            if (empty($range_pts)) continue;

            usort($range_pts, function($a, $b) { return $a['shot_no'] <=> $b['shot_no']; });
            $first = $range_pts[0];
            $last = $range_pts[count($range_pts) - 1];
            $dx = $last['x'] - $first['x'];
            $dy = $last['y'] - $first['y'];
            $len = sqrt($dx * $dx + $dy * $dy);

            if ($len < 0.0001) {
                $prev = $points[$range['start'] - 1] ?? null;
                $next = $points[$range['end'] + 1] ?? null;
                if ($prev && $next) { $dx = $next['x'] - $prev['x']; $dy = $next['y'] - $prev['y']; }
                else if ($prev) { $dx = $first['x'] - $prev['x']; $dy = $first['y'] - $prev['y']; }
                else if ($next) { $dx = $next['x'] - $first['x']; $dy = $next['y'] - $first['y']; }
                $len = sqrt($dx * $dx + $dy * $dy);
            }

            if ($len < 0.0001) { $dx = 1.0; $dy = 0.0; $len = 1.0; }
            $ux = $dx / $len;
            $uy = $dy / $len;
            $vx = -$uy;
            $vy = $ux;

            $center = ['x' => ($first['x'] + $last['x']) / 2, 'y' => ($first['y'] + $last['y']) / 2];
            $half_w = max($along_gap * 0.65, ($len / 2) + ($along_gap * 0.65));
            $half_h = max($cross_gap * 0.38, 80.0);

            $zones[] = [
                'course_no' => $course_no,
                'start_shot' => $range['start'],
                'end_shot' => $range['end'],
                'actual_start_shot' => $range['actual_start'] ?? $range['start'],
                'actual_end_shot' => $range['actual_end'] ?? $range['end'],
                'points' => eo_rectangle_polyline($center, $ux, $uy, $vx, $vy, $half_w, $half_h)
            ];
        }
    }

    return $zones;
}

function extract_block_eo_point_data($base_dir, $block) {
    global $prj_id;

    $course_map = array_flip(img_block_course_numbers($block['line_list'] ?? ''));
    if (empty($course_map)) {
        return ['success' => false, 'message' => '블럭에 등록된 코스가 없습니다.'];
    }

    $entities = [];
    $reshoot_zones = [];
    $seen = [];
    $main_count = 0;
    $reshoot_count = 0;
    $min_x = 999999999; $max_x = -999999999;
    $min_y = 999999999; $max_y = -999999999;

    $flight_res = sql_query(" SELECT * FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' AND eo_file_name != '' ORDER BY flight_date ASC ");
    if (!$flight_res) return ['success' => false, 'message' => '활성 EO가 없습니다.'];

    while ($flight = sql_fetch_array($flight_res)) {
        $parsed = extract_eo_point_data($base_dir, $flight);
        if (!$parsed['success'] || empty($parsed['entities'])) continue;

        foreach (extract_eo_reshoot_zones($base_dir, $flight, $course_map) as $zone) {
            $zone['flight_date'] = trim((string)$flight['flight_date']);
            $reshoot_zones[] = $zone;
        }

        foreach ($parsed['entities'] as $pt) {
            $course_no = img_eo_course_no_from_id($pt['id']);
            if (!isset($course_map[$course_no])) continue;

            $key = strtoupper($pt['id']);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $pt['flight_date'] = trim((string)$flight['flight_date']);
            $entities[] = $pt;

            if (!empty($pt['is_reshoot'])) $reshoot_count++;
            else $main_count++;

            $min_x = min($min_x, $pt['x']);
            $max_x = max($max_x, $pt['x']);
            $min_y = min($min_y, $pt['y']);
            $max_y = max($max_y, $pt['y']);
        }
    }

    if (empty($entities)) {
        return ['success' => false, 'message' => '블럭 코스에 해당하는 정상 EO 주점을 찾지 못했습니다.'];
    }

    return [
        'success' => true,
        'block_name' => $block['block_name'],
        'total' => count($entities),
        'main_count' => $main_count,
        'reshoot_count' => $reshoot_count,
        'reshoot_zones' => $reshoot_zones,
        'entities' => $entities,
        'bbox' => ['min_x' => $min_x, 'max_x' => $max_x, 'min_y' => $min_y, 'max_y' => $max_y]
    ];
}

function build_index_dxf_from_entities($parsed, $crs_type, $selected_sheets_str, $layer_prefix) {
    $dxf = "0\nSECTION\n2\nHEADER\n9\n\$ACADVER\n1\nAC1009\n0\nENDSEC\n";
    $reshoot_zone_layer = to_cp949('재촬영구간');

    $layer_prefixes = [];
    foreach (($parsed['entities'] ?? []) as $pt) {
        $pt_prefix = trim((string)($pt['layer_prefix'] ?? ''));
        $layer_prefixes[$pt_prefix !== '' ? $pt_prefix : $layer_prefix] = true;
    }
    if (empty($layer_prefixes)) $layer_prefixes[$layer_prefix] = true;

    $layer_count = 5 + (count($layer_prefixes) * 4);
    $dxf .= "0\nSECTION\n2\nTABLES\n0\nTABLE\n2\nLAYER\n70\n{$layer_count}\n";
    $dxf .= "0\nLAYER\n2\n0\n70\n0\n62\n7\n6\nCONTINUOUS\n";
    foreach (array_keys($layer_prefixes) as $prefix) {
        $dxf .= "0\nLAYER\n2\n{$prefix}_PP\n70\n0\n62\n1\n6\nCONTINUOUS\n";
        $dxf .= "0\nLAYER\n2\n{$prefix}_TT\n70\n0\n62\n7\n6\nCONTINUOUS\n";
        $dxf .= "0\nLAYER\n2\n{$prefix}_PP_A\n70\n0\n62\n2\n6\nCONTINUOUS\n";
        $dxf .= "0\nLAYER\n2\n{$prefix}_TT_A\n70\n0\n62\n2\n6\nCONTINUOUS\n";
    }
    $dxf .= "0\nLAYER\n2\n{$reshoot_zone_layer}\n70\n0\n62\n2\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\nG50\n70\n0\n62\n7\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\nN50000\n70\n0\n62\n7\n6\nCONTINUOUS\n";
    $dxf .= "0\nLAYER\n2\nT50000\n70\n0\n62\n250\n6\nCONTINUOUS\n";
    $dxf .= "0\nENDTAB\n0\nENDSEC\n";
    $dxf .= "0\nSECTION\n2\nENTITIES\n";

    if ($selected_sheets_str) {
        $bb = $parsed['bbox'];
        $debug_dummy = [];
        $all_sheets = extract_base_map_sheets($crs_type, $bb['min_x'], $bb['max_x'], $bb['min_y'], $bb['max_y'], $debug_dummy);
        $sel_arr = array_map('trim', explode(',', $selected_sheets_str));

        foreach ($all_sheets as $sheet) {
            if (!in_array($sheet['sheet_no'], $sel_arr, true)) continue;

            if (!empty($sheet['poly_points'])) {
                $dxf .= "0\nPOLYLINE\n8\nG50\n66\n1\n70\n1\n";
                foreach ($sheet['poly_points'] as $p) {
                    $dxf .= "0\nVERTEX\n8\nG50\n10\n" . sprintf('%.4f', $p['x']) . "\n20\n" . sprintf('%.4f', $p['y']) . "\n";
                }
                $dxf .= "0\nSEQEND\n8\nG50\n";
            }

            if (!empty($sheet['no_pos'])) {
                $np = $sheet['no_pos'];
                $dxf .= "0\nTEXT\n8\nN50000\n10\n" . sprintf('%.4f', $np['x']) . "\n20\n" . sprintf('%.4f', $np['y'])
                      . "\n30\n0.0\n40\n" . sprintf('%.4f', $np['height']) . "\n1\n" . to_cp949($sheet['sheet_no'])
                      . "\n50\n" . sprintf('%.4f', $np['rotation']) . "\n7\nSTANDARD\n";
            }

            if (!empty($sheet['name_pos'])) {
                $mp = $sheet['name_pos'];
                $dxf .= "0\nTEXT\n8\nT50000\n10\n" . sprintf('%.4f', $mp['x']) . "\n20\n" . sprintf('%.4f', $mp['y'])
                      . "\n30\n0.0\n40\n" . sprintf('%.4f', $mp['height']) . "\n1\n" . to_cp949($sheet['sheet_name'])
                      . "\n50\n" . sprintf('%.4f', $mp['rotation']) . "\n7\nSTANDARD\n";
            }
        }
    }

    foreach (($parsed['reshoot_zones'] ?? []) as $zone) {
        if (empty($zone['points']) || count($zone['points']) < 4) continue;

        $dxf .= "0\nPOLYLINE\n8\n{$reshoot_zone_layer}\n62\n2\n66\n1\n70\n1\n";
        foreach ($zone['points'] as $p) {
            $dxf .= "0\nVERTEX\n8\n{$reshoot_zone_layer}\n62\n2\n10\n" . sprintf('%.4f', $p['x']) . "\n20\n" . sprintf('%.4f', $p['y']) . "\n";
        }
        $dxf .= "0\nSEQEND\n8\n{$reshoot_zone_layer}\n";
    }

    foreach ($parsed['entities'] as $pt) {
        $id = $pt['id'];
        $x = sprintf("%.4f", $pt['x']);
        $y = sprintf("%.4f", $pt['y']);
        $is_reshoot = $pt['is_reshoot'];
        $pt_prefix = trim((string)($pt['layer_prefix'] ?? ''));
        $pt_prefix = $pt_prefix !== '' ? $pt_prefix : $layer_prefix;

        $layer_pp = $is_reshoot ? "{$pt_prefix}_PP_A" : "{$pt_prefix}_PP";
        $layer_tt = $is_reshoot ? "{$pt_prefix}_TT_A" : "{$pt_prefix}_TT";
        $c_val = $is_reshoot ? 2 : 1;
        $t_val = $is_reshoot ? 2 : 7;

        $dxf .= "0\nCIRCLE\n8\n{$layer_pp}\n62\n{$c_val}\n10\n{$x}\n20\n{$y}\n30\n0.0\n40\n50.0\n";
        $dxf .= "0\nTEXT\n8\n{$layer_tt}\n62\n{$t_val}\n10\n{$x}\n20\n{$y}\n30\n0.0\n40\n150.0\n1\n{$id}\n50\n45.0\n7\nSTANDARD\n";
    }

    return $dxf . "0\nENDSEC\n0\nEOF\n";
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

if ($action === 'preview_block_index_data') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    try {
        $block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $crs_type = isset($_GET['crs_type']) ? trim($_GET['crs_type']) : 'EPSG:5186';

        $block = sql_fetch(" SELECT * FROM IMG_BLOCK WHERE block_id = {$block_id} AND prj_id = {$prj_id} ");
        if (!$block) {
            echo json_encode(['success' => false, 'message' => '블럭 정보를 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = extract_block_eo_point_data($base_dir, $block);
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
    $date_id             = isset($_POST['date_id']) ? (int)$_POST['date_id'] : 0;
    $index_name          = isset($_POST['index_name']) ? trim($_POST['index_name']) : '';
    $crs_type            = isset($_POST['crs_type']) ? trim($_POST['crs_type']) : 'EPSG:5186';
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

    $dxf = build_index_dxf_from_entities($parsed, $crs_type, $selected_sheets_str, $date_compact);

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

if ($action === 'generate_block_index_dwg') {
    $block_id            = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
    $index_name          = isset($_POST['index_name']) ? trim($_POST['index_name']) : '';
    $crs_type            = isset($_POST['crs_type']) ? trim($_POST['crs_type']) : 'EPSG:5186';
    $selected_sheets_str = isset($_POST['selected_sheets']) ? trim($_POST['selected_sheets']) : '';

    if (!$block_id || !$index_name) {
        action_error_toast($prj_id, 'tab-block', '필수 파라미터가 누락되었습니다.');
    }

    if (!preg_match('/\.dxf$/i', $index_name)) $index_name = preg_replace('/\.[^.]+$/', '', $index_name) . '.dxf';

    $block = sql_fetch(" SELECT * FROM IMG_BLOCK WHERE block_id = {$block_id} AND prj_id = {$prj_id} ");
    if (!$block) action_error_toast($prj_id, 'tab-block', '블럭 정보를 찾을 수 없습니다.');

    $parsed = extract_block_eo_point_data($base_dir, $block);
    if (!$parsed['success'] || empty($parsed['entities'])) {
        action_error_toast($prj_id, 'tab-block', $parsed['message'] ?? '좌표 추출 실패');
    }

    $safe_block_name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', '', $block['block_name']);
    $layer_prefix = preg_replace('/[^A-Za-z0-9_]/', '_', strtoupper($safe_block_name));
    $dxf = build_index_dxf_from_entities($parsed, $crs_type, $selected_sheets_str, $layer_prefix);

    $index_save_dir = $base_dir . '\\block\\' . $safe_block_name . '\\INDEX';
    if (!is_dir($index_save_dir)) @mkdir($index_save_dir, 0777, true);

    $save_full_path = $index_save_dir . '\\' . $index_name;
    $write_res = @file_put_contents($save_full_path, $dxf);
    if ($write_res === false || !file_exists($save_full_path)) {
        action_error_toast($prj_id, 'tab-block', '블럭 INDEX 파일 저장 실패');
    }

    action_goto_url(G5_URL.'/project/index_view.php?prj_id='.$prj_id.'&block_id='.$block_id.'&active_file='.urlencode($index_name).'&toast=block_index_ok');
}

if ($action === 'delete_index_file') {
    $filename = isset($_REQUEST['filename']) ? trim($_REQUEST['filename']) : '';
    $date_id = isset($_REQUEST['date_id']) ? (int)$_REQUEST['date_id'] : 0;
    $block_id = isset($_REQUEST['block_id']) ? (int)$_REQUEST['block_id'] : 0;
    
    // 상위 디렉터리 탐색 방지 (보안)
    $filename = basename($filename);

    if (!$filename) {
        action_error_toast($prj_id, 'tab-flight', '삭제할 파일명이 올바르지 않습니다.');
    }

    if ($block_id > 0) {
        $block = sql_fetch(" SELECT * FROM IMG_BLOCK WHERE block_id = {$block_id} AND prj_id = {$prj_id} ");
        if (!$block) {
            action_error_toast($prj_id, 'tab-block', '블럭 정보를 찾을 수 없습니다.');
        }
        $safe_block_name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', '', $block['block_name']);
        $index_dir = $base_dir . '\\block\\' . $safe_block_name . '\\INDEX';
        $redirect_url = G5_URL . '/project/index_view.php?prj_id=' . $prj_id . '&block_id=' . $block_id;
    } else {
        $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
        if (!$flight) {
            action_error_toast($prj_id, 'tab-flight', '촬영일 정보를 찾을 수 없습니다.');
        }
        $index_dir = $base_dir . '\\date\\' . trim($flight['flight_date']) . '\\INDEX';
        $redirect_url = G5_URL . '/project/index_view.php?prj_id=' . $prj_id . '&date_id=' . $date_id;
    }

    $target_file = $index_dir . '\\' . $filename;
    $delete_candidates = array_values(array_unique(array_filter([
        function_exists('img_fs_path') ? img_fs_path($target_file) : $target_file,
        $target_file,
        @iconv('UTF-8', 'CP949//IGNORE', $target_file)
    ])));

    // 1. 실제 물리 파일 삭제
    $deleted = false;
    $found_file = false;
    foreach ($delete_candidates as $path) {
        if (!$path || !file_exists($path) || !is_file($path)) continue;
        $found_file = true;
        @chmod($path, 0777);
        if (@unlink($path)) {
            $deleted = true;
            break;
        }
    }

    if (!$deleted && $found_file) {
        alert('파일 권한 또는 사용 중인 상태 때문에 INDEX 파일을 삭제하지 못했습니다.', $redirect_url);
        exit;
    }

    // 2. DB 동기화 (해당 인덱스 레코드 삭제)
    if ($block_id <= 0) {
        sql_query(" DELETE FROM IMG_FLIGHT_INDEX WHERE prj_id = {$prj_id} AND date_id = {$date_id} AND file_name = '" . sql_real_escape_string($filename) . "' ");
    }

    // 3. 리다이렉트
    action_goto_url($redirect_url);
}
