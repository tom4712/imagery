<?php
if (!defined('_GNUBOARD_')) exit;

$base_dir = 'E:\#KYS_IMAGERY_SERVER\\' . trim($prj['prj_name']);
$index_dir = $base_dir . '\\date\\' . trim($flight['flight_date']) . '\\INDEX';

$dwg_files = [];
$active_dwg_file = isset($_GET['active_file']) ? trim($_GET['active_file']) : '';

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

if (!$active_dwg_file) {
    $active_idx_row = sql_fetch(" SELECT * FROM IMG_FLIGHT_INDEX WHERE prj_id = {$prj_id} AND date_id = {$date_id} AND is_active = 1 ");
    if ($active_idx_row && file_exists($index_dir . '\\' . $active_idx_row['file_name'])) {
        $active_dwg_file = $active_idx_row['file_name'];
    } else if (!empty($dwg_files)) {
        $active_dwg_file = $dwg_files[0]['filename'];
    }
}

// 3. 안정적인 스트림 기반 DXF 파서
$layers = [];
$entities = [];

$aci_colors = [
    1 => '#ef4444', // Red
    2 => '#eab308', // Yellow
    3 => '#22c55e', // Green
    4 => '#06b6d4', // Cyan
    5 => '#60a5fa', // Blue
    6 => '#d946ef', // Magenta
    7 => '#ffffff', // White
    8 => '#94a3b8', // Gray
    9 => '#cbd5e1'
];

if ($active_dwg_file) {
    $target_dxf_path = $index_dir . '\\' . $active_dwg_file;
    if (file_exists($target_dxf_path)) {
        $handle = @fopen($target_dxf_path, 'r');
        if ($handle) {
            $in_tables = false;
            $in_layer_table = false;
            $in_entities = false;

            $cur_layer_name = '';
            $cur_layer_color = 7;
            $cur_layer_flags = 0;

            $cur_entity_type = '';
            $cur_layer = '0';
            $cur_color = null;
            $cur_text = '';
            $cur_radius = 50.0;
            $x10 = null; $y20 = null;
            $x11 = null; $y21 = null;
            $poly_pts = [];

            while (!feof($handle)) {
                $code_line = fgets($handle);
                if ($code_line === false) break;
                $code = trim($code_line);

                $val_line = fgets($handle);
                if ($val_line === false) break;
                $val = trim($val_line);

                if ($code === '0') {
                    // 이전 엔티티 수확
                    if ($in_entities && $cur_entity_type !== '') {
                        $item = [
                            'type'  => $cur_entity_type,
                            'layer' => $cur_layer ?: '0',
                            'color' => $cur_color
                        ];

                        if ($cur_entity_type === 'LINE' && $x10 !== null && $x11 !== null) {
                            $item['x1'] = $x10; $item['y1'] = $y20;
                            $item['x2'] = $x11; $item['y2'] = $y21;
                            $entities[] = $item;
                        } else if ($cur_entity_type === 'LWPOLYLINE' && !empty($poly_pts)) {
                            $item['points'] = $poly_pts;
                            $entities[] = $item;
                        } else if ($cur_entity_type === 'CIRCLE' && $x10 !== null) {
                            $item['x'] = $x10; $item['y'] = $y20;
                            $item['r'] = $cur_radius;
                            $entities[] = $item;
                        } else if ($cur_entity_type === 'TEXT' && $cur_text !== '') {
                            $tx = ($x11 !== null && $x11 != 0) ? $x11 : $x10;
                            $ty = ($y21 !== null && $y21 != 0) ? $y21 : $y20;
                            if ($tx !== null && $ty !== null) {
                                $item['x'] = $tx; $item['y'] = $ty;
                                $item['text'] = $cur_text;
                                $item['is_reshoot'] = (strpos($cur_layer, '_A') !== false || preg_match('/[a-zA-Z]$/', $cur_text));
                                $entities[] = $item;
                            }
                        }
                    }

                    // 이전 레이어 수확
                    if ($in_layer_table && $cur_layer_name !== '') {
                        $is_off = ($cur_layer_color < 0);
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
                    }

                    // 섹션 식별
                    if ($val === 'SECTION') { $in_tables = false; $in_layer_table = false; $in_entities = false; }
                    else if ($val === 'TABLES') { $in_tables = true; }
                    else if ($val === 'ENTITIES') { $in_entities = true; $in_tables = false; $in_layer_table = false; }
                    else if ($val === 'ENDSEC') { $in_tables = false; $in_layer_table = false; $in_entities = false; }

                    if ($in_tables && $val === 'LAYER') {
                        $in_layer_table = true;
                        $cur_layer_name = '';
                        $cur_layer_color = 7;
                        $cur_layer_flags = 0;
                    }

                    // 초기화
                    $cur_entity_type = $val;
                    $cur_layer = '0';
                    $cur_color = null;
                    $cur_text = '';
                    $cur_radius = 50.0;
                    $x10 = null; $y20 = null;
                    $x11 = null; $y21 = null;
                    $poly_pts = [];
                    continue;
                }

                // 레이어 테이블 상세
                if ($in_layer_table) {
                    if ($code === '2')  $cur_layer_name = $val;
                    if ($code === '62') $cur_layer_color = (int)$val;
                    if ($code === '70') $cur_layer_flags = (int)$val;
                }

                // 엔티티 속성 상세
                if ($in_entities) {
                    if ($code === '8')  $cur_layer = $val;
                    if ($code === '62') $cur_color = (int)$val;
                    if ($code === '40') $cur_radius = (float)$val;
                    if ($code === '10') {
                        if ($cur_entity_type === 'LWPOLYLINE') $x10 = (float)$val;
                        else $x10 = (float)$val;
                    }
                    if ($code === '20') {
                        if ($cur_entity_type === 'LWPOLYLINE') {
                            $y20 = (float)$val;
                            if ($x10 !== null && $y20 !== null) {
                                $poly_pts[] = ['x' => $x10, 'y' => $y20];
                            }
                        } else {
                            $y20 = (float)$val;
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
            fclose($handle);
        }
    }
}

// 엔티티에 사용된 레이어 색상 및 기본값 동기화
foreach ($entities as $e) {
    $ln = $e['layer'];
    if (!isset($layers[$ln])) {
        $c_hex = '#ffffff';
        if ($e['color'] && isset($aci_colors[$e['color']])) {
            $c_hex = $aci_colors[$e['color']];
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