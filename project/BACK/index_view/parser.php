<?php
if (!defined('_GNUBOARD_')) exit;

$base_dir = function_exists('img_project_path')
    ? img_project_path($prj['prj_name'])
    : 'E:\#KYS_IMAGERY_SERVER\\' . trim($prj['prj_name']);
if (($index_mode ?? 'date') === 'block') {
    $safe_block_name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', '', trim($block['block_name'] ?? ''));
    $index_dir = $base_dir . '\\block\\' . $safe_block_name . '\\INDEX';
} else {
    $index_dir = $base_dir . '\\date\\' . trim($flight['flight_date']) . '\\INDEX';
}

$dwg_files = [];
$active_dwg_file = isset($_GET['active_file']) ? basename(trim($_GET['active_file'])) : '';

// 1. 디렉터리 스캔
if (is_dir($index_dir)) {
    $files = scandir($index_dir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;

        $full_fpath = $index_dir . '\\' . $f;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));

        if (in_array($ext, ['dwg', 'dxf', 'json', 'geojson']) && is_file($full_fpath)) {
            $dwg_files[] = [
                'filename'  => $f,
                'path'      => $full_fpath,
                'mtime_raw' => @filemtime($full_fpath) ?: 0,
                'mtime'     => date('Y-m-d H:i:s', @filemtime($full_fpath) ?: time()),
                'size'      => round((@filesize($full_fpath) ?: 0) / 1024, 1) . ' KB'
            ];
        }
    }

    usort($dwg_files, function($a, $b) {
        return $b['mtime_raw'] - $a['mtime_raw'];
    });
}

// 2. 활성화 파일 결정
if ($active_dwg_file && !file_exists($index_dir . '\\' . $active_dwg_file)) {
    $active_dwg_file = '';
}

if (!$active_dwg_file && ($index_mode ?? 'date') === 'date') {
    $active_idx_row = sql_fetch(" SELECT * FROM IMG_FLIGHT_INDEX WHERE prj_id = {$prj_id} AND date_id = {$date_id} AND is_active = 1 ");
    if ($active_idx_row && file_exists($index_dir . '\\' . $active_idx_row['file_name'])) {
        $active_dwg_file = $active_idx_row['file_name'];
    } else if (!empty($dwg_files)) {
        $active_dwg_file = $dwg_files[0]['filename'];
    }
} else if (!$active_dwg_file && !empty($dwg_files)) {
    $active_dwg_file = $dwg_files[0]['filename'];
}

// 3. DXF 표준 파서
$layers = [];
$entities = [];

$aci_colors = [
    1 => '#ef4444', // Red (PP)
    2 => '#eab308', // Yellow (PP_A)
    3 => '#22c55e', // Green
    4 => '#06b6d4', // Cyan
    5 => '#60a5fa', // Blue (INDEX_50K)
    6 => '#d946ef', // Magenta
    7 => '#ffffff', // White (TT)
    8 => '#94a3b8', // Gray (G50)
    9 => '#cbd5e1'
];

if ($active_dwg_file) {
    $target_dxf_path = $index_dir . '\\' . $active_dwg_file;
    if (file_exists($target_dxf_path)) {
        $lines = file($target_dxf_path, FILE_IGNORE_NEW_LINES);
        $total_lines = count($lines);

        $to_utf8 = function($value) {
            $value = (string)$value;
            if ($value === '' || mb_detect_encoding($value, 'UTF-8', true)) return $value;
            $converted = @iconv('CP949', 'UTF-8//IGNORE', $value);
            return ($converted !== false) ? $converted : $value;
        };

        $current_section = '';
        $current_table   = '';

        $cur_entity_type = '';
        $cur_layer       = '0';
        $cur_color       = null;
        $cur_text        = '';
        $cur_radius      = 50.0;
        $cur_height      = 150.0;
        $cur_rotation    = 0.0;
        $cur_align_h     = 0;
        $cur_align_v     = 0;
        $x10 = null; $y20 = null;
        $x11 = null; $y21 = null;
        $poly_pts = [];

        $cur_layer_name  = '';
        $cur_layer_color = 7;
        $cur_layer_flags = 0;

        $in_old_poly    = false;
        $old_poly_layer = '';
        $old_poly_color = null;
        $old_poly_pts   = [];

        $save_entity = function() use (
            &$cur_entity_type, &$cur_layer, &$cur_color, &$x10, &$y20, &$x11, &$y21, 
            &$cur_text, &$cur_radius, &$cur_height, &$cur_rotation, &$cur_align_h, &$cur_align_v, 
            &$poly_pts, &$entities
        ) {
            if (!$cur_entity_type) return;

            $item = [
                'type'     => $cur_entity_type,
                'layer'    => $cur_layer ?: '0',
                'color'    => $cur_color
            ];

            if ($cur_entity_type === 'LINE' && $x10 !== null && $x11 !== null) {
                $item['x1'] = $x10; $item['y1'] = $y20;
                $item['x2'] = $x11; $item['y2'] = $y21;
                $entities[] = $item;
            } else if ($cur_entity_type === 'LWPOLYLINE' && !empty($poly_pts)) {
                $item['points'] = $poly_pts;
                $entities[] = $item;
            } else if ($cur_entity_type === 'CIRCLE' && $x10 !== null) {
                $item['x']      = $x10; 
                $item['y']      = $y20;
                $item['radius'] = $cur_radius ?: 50.0;
                $entities[] = $item;
            } else if (($cur_entity_type === 'TEXT' || $cur_entity_type === 'MTEXT') && $cur_text !== '') {
                $item['x']          = $x10 ?? 0;
                $item['y']          = $y20 ?? 0;
                $item['align_x']    = $x11;
                $item['align_y']    = $y21;
                $item['height']     = $cur_height ?: 150.0;
                $item['rotation']   = $cur_rotation ?: 0.0;
                $item['align_h']    = $cur_align_h;
                $item['align_v']    = $cur_align_v;
                $item['text']       = $cur_text;
                $item['is_reshoot'] = (strpos($cur_layer, '_A') !== false || preg_match('/[a-zA-Z]$/', $cur_text));
                $entities[] = $item;
            }
        };

        $save_layer = function() use (&$cur_layer_name, &$cur_layer_color, &$cur_layer_flags, &$layers, &$aci_colors) {
            if (!$cur_layer_name) return;
            $is_off    = ($cur_layer_color < 0);
            $is_frozen = (($cur_layer_flags & 1) === 1);
            $color_idx = abs($cur_layer_color);
            $color_hex = $aci_colors[$color_idx] ?? '#ffffff';

            $layers[$cur_layer_name] = [
                'name'      => $cur_layer_name,
                'color_idx' => $color_idx,
                'color_hex' => $color_hex,
                'visible'   => !$is_off,
                'frozen'    => $is_frozen
            ];
        };

        for ($i = 0; $i < $total_lines; $i += 2) {
            if (!isset($lines[$i])) break;
            $code = trim($lines[$i]);
            $val  = isset($lines[$i + 1]) ? trim($to_utf8($lines[$i + 1])) : '';

            if ($code === '2') {
                if ($current_section === 'SECTION_INIT') {
                    $current_section = strtoupper($val);
                    continue;
                }
                if ($current_section === 'TABLES' && $current_table === 'TABLE_INIT') {
                    $current_table = strtoupper($val);
                    continue;
                }
            }

            if ($code === '0') {
                if ($current_section === 'ENTITIES') {
                    if ($cur_entity_type === 'POLYLINE') {
                        $old_poly_layer = $cur_layer ?: '0';
                        $old_poly_color = $cur_color;
                    } else if ($cur_entity_type === 'VERTEX' && $in_old_poly) {
                        if ($x10 !== null && $y20 !== null) {
                            $old_poly_pts[] = ['x' => $x10, 'y' => $y20];
                        }
                    } else {
                        $save_entity();
                    }
                } else if ($current_section === 'TABLES' && $current_table === 'LAYER') {
                    $save_layer();
                }

                if ($val === 'POLYLINE') {
                    $in_old_poly    = true;
                    $old_poly_layer = '';
                    $old_poly_color = null;
                    $old_poly_pts   = [];
                } else if ($val === 'SEQEND' && $in_old_poly) {
                    if (!empty($old_poly_pts)) {
                        $entities[] = [
                            'type'   => 'LWPOLYLINE',
                            'layer'  => $old_poly_layer ?: '0',
                            'color'  => $old_poly_color,
                            'points' => $old_poly_pts
                        ];
                    }
                    $in_old_poly = false;
                }

                if ($val === 'SECTION') {
                    $current_section = 'SECTION_INIT';
                    $current_table = '';
                } else if ($val === 'ENDSEC') {
                    $current_section = '';
                    $current_table = '';
                } else if ($val === 'TABLE') {
                    $current_table = 'TABLE_INIT';
                } else if ($val === 'ENDTAB') {
                    $current_table = '';
                }

                $cur_entity_type = $val;
                $cur_layer       = '0';
                $cur_color       = null;
                $cur_text        = '';
                $cur_radius      = 50.0;
                $cur_height      = 150.0;
                $cur_rotation    = 0.0;
                $cur_align_h     = 0;
                $cur_align_v     = 0;
                $x10 = null; $y20 = null;
                $x11 = null; $y21 = null;
                $poly_pts = [];

                $cur_layer_name  = '';
                $cur_layer_color = 7;
                $cur_layer_flags = 0;
                continue;
            }

            // LAYER 테이블
            if ($current_section === 'TABLES' && $current_table === 'LAYER') {
                if ($code === '2')  $cur_layer_name = $val;
                if ($code === '62') $cur_layer_color = (int)$val;
                if ($code === '70') $cur_layer_flags = (int)$val;
            }

            // ENTITIES 데이터
            if ($current_section === 'ENTITIES') {
                if ($code === '8')  $cur_layer = $val;
                if ($code === '62') $cur_color = (int)$val;
                if ($code === '40') {
                    $cur_radius = (float)$val;
                    $cur_height = (float)$val;
                }
                if ($code === '50') $cur_rotation = (float)$val;
                if ($code === '72') $cur_align_h  = (int)$val;
                if ($code === '73') $cur_align_v  = (int)$val;

                if ($code === '10') {
                    $x10 = (float)$val;
                }
                if ($code === '20') {
                    $y20 = (float)$val;
                    if ($cur_entity_type === 'LWPOLYLINE' && $x10 !== null && $y20 !== null) {
                        $poly_pts[] = ['x' => $x10, 'y' => $y20];
                    }
                }
                if ($code === '11') $x11 = (float)$val;
                if ($code === '21') $y21 = (float)$val;
                if ($code === '1') {
                    if (!mb_detect_encoding($val, 'UTF-8', true)) {
                        $cur_text = trim(iconv('CP949', 'UTF-8//IGNORE', $val));
                    } else {
                        $cur_text = trim($val);
                    }
                }
            }
        }

        if ($current_section === 'ENTITIES') $save_entity();
        if ($current_section === 'TABLES' && $current_table === 'LAYER') $save_layer();
    }
}

// 4. 엔티티별 개별 지정 색상 및 기본 레이어 색상 맵핑
foreach ($entities as $e) {
    $ln = $e['layer'];
    if (!isset($layers[$ln])) {
        $c_hex = '#ffffff';
        if ($e['color'] && isset($aci_colors[$e['color']])) {
            $c_hex = $aci_colors[$e['color']];
        } else if (str_contains($ln, 'INDEX') || str_contains($ln, '50000')) {
            $c_hex = '#60a5fa'; // Blue
        } else if (str_contains($ln, '_A')) {
            $c_hex = '#eab308'; // Yellow
        } else if (str_contains($ln, '_PP') || str_contains($ln, '_TT')) {
            $c_hex = '#ef4444'; // Red
        } else if ($ln === 'G50') {
            $c_hex = '#94a3b8'; // Gray
        }

        $layers[$ln] = [
            'name'      => $ln,
            'color_idx' => $e['color'] ?: 7,
            'color_hex' => $c_hex,
            'visible'   => true,
            'frozen'    => false
        ];
    }
}

$layers_json   = json_encode($layers, JSON_UNESCAPED_UNICODE);
$entities_json = json_encode($entities, JSON_UNESCAPED_UNICODE);
