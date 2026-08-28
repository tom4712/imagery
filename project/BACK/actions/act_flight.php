<?php
if (!defined('_GNUBOARD_')) exit;

function inspect_completed_eo_name($dir, $filename) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $suffix = $ext ? '.' . $ext : '';
    $base_name = $stem . '_검수완료';
    $candidate = $base_name . $suffix;
    $idx = 2;

    while (file_exists(img_resolve_existing_path($dir . '\\' . $candidate))) {
        $candidate = $base_name . '_' . $idx . $suffix;
        $idx++;
    }

    return $candidate;
}

function inspect_existing_eo_path($eo_dir, $filename) {
    $filename = basename(trim((string)$filename));
    if ($filename === '') return '';

    $direct_path = $eo_dir . '\\' . $filename;
    $resolved_path = img_resolve_existing_path($direct_path);
    if ($resolved_path && file_exists($resolved_path)) return $resolved_path;

    $dir_path = img_resolve_existing_path($eo_dir);
    if (!$dir_path || !is_dir($dir_path)) return '';

    $files = @scandir($dir_path);
    if (!$files) return '';

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $utf_name = preg_match('//u', $file) ? $file : iconv('CP949', 'UTF-8//IGNORE', $file);
        if ($utf_name === $filename || basename($utf_name) === $filename) {
            return $dir_path . DIRECTORY_SEPARATOR . $file;
        }
    }

    return '';
}

function inspect_copy_eo_file($source_path, $eo_dir, $new_eo_name) {
    $target_utf8 = $eo_dir . '\\' . $new_eo_name;
    $target_dir = dirname($target_utf8);

    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }

    if (@copy($source_path, $target_utf8)) return $target_utf8;

    $target_cp949 = iconv('UTF-8', 'CP949//IGNORE', $target_utf8);
    $target_dir_cp949 = dirname($target_cp949);
    if (!is_dir($target_dir_cp949)) {
        @mkdir($target_dir_cp949, 0777, true);
    }

    if (@copy($source_path, $target_cp949)) return $target_cp949;

    return '';
}

function inspect_eo_records($file_path) {
    $file_path = img_resolve_existing_path($file_path);
    if (!$file_path || !file_exists($file_path) || is_dir($file_path)) return [];

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $records = [];
    $seen = [];

    $add_record = function($id, $row_no) use (&$records, &$seen) {
        $id = trim((string)$id);
        if ($id === '' || isset($seen[$id])) return;

        $course_no = img_eo_course_no_from_id($id);
        if ($course_no <= 0) return;

        $seen[$id] = true;
        $records[] = [
            'id' => $id,
            'row_no' => (int)$row_no,
            'course_no' => $course_no
        ];
    };

    if (in_array($ext, ['txt', 'csv', 'tsv', 'dat'])) {
        $lines = @file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $idx => $line) {
                $parts = preg_split('/[\t,\s]+/', trim($line));
                if (count($parts) < 8) continue;
                $add_record($parts[0], $idx + 1);
            }
        }
    } else if ($ext === 'xlsx' && class_exists('ZipArchive') && function_exists('simplexml_load_string')) {
        $zip = new ZipArchive;
        if ($zip->open($file_path) !== TRUE) return [];

        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $shared_strings = img_xlsx_shared_strings($zip);
        $zip->close();

        if (!$sheet_xml) return [];

        $sx = @simplexml_load_string($sheet_xml);
        if (!$sx || !isset($sx->sheetData->row)) return [];

        foreach ($sx->sheetData->row as $row) {
            $id = '';
            $max_col = 0;
            $row_no = (int)$row['r'];

            foreach ($row->c as $cell) {
                $col_idx = img_xlsx_col_index((string)$cell['r']);
                if ($col_idx > $max_col) $max_col = $col_idx;
                if ($col_idx === 1) $id = img_xlsx_cell_value($cell, $shared_strings);
            }

            if ($max_col < 8) continue;
            $add_record($id, $row_no);
        }
    }

    return $records;
}

function inspect_mark_map($records, $inspection_items) {
    $id_type = [];
    $id_reason = [];
    foreach ($inspection_items as $item) {
        $id = trim((string)($item['id'] ?? ''));
        $reason = trim((string)($item['reason'] ?? ''));
        $type = (($item['type'] ?? '') === 'duplicate' || mb_strpos($reason, '미사용') !== false) ? 'duplicate' : 'reshoot';
        if ($id === '') continue;

        $key = strtoupper($id);
        $id_type[$key] = $type;
        $id_reason[$key] = $reason;
    }

    $marks = [];
    $course_groups = [];
    foreach ($records as $idx => $rec) {
        $id = $rec['id'];
        $key = strtoupper($id);
        if (isset($id_type[$key])) {
            $marks[$id] = $id_type[$key] === 'duplicate' ? 'duplicate' : 'reshoot';
        }
        $course_no = $rec['course_no'];
        if (!isset($course_groups[$course_no])) $course_groups[$course_no] = [];
        $course_groups[$course_no][] = $idx;
    }

    foreach ($course_groups as $group_indexes) {
        $group_pos_count = count($group_indexes);
        for ($pos = 0; $pos < $group_pos_count; $pos++) {
            $rec = $records[$group_indexes[$pos]];
            if (($marks[$rec['id']] ?? '') !== 'reshoot') continue;

            for ($offset = -3; $offset <= 3; $offset++) {
                if ($offset === 0) continue;
                $buf_pos = $pos + $offset;
                if ($buf_pos < 0 || $buf_pos >= $group_pos_count) continue;

                $buf_id = $records[$group_indexes[$buf_pos]]['id'];
                if (isset($marks[$buf_id])) continue;
                $marks[$buf_id] = 'buffer';
            }
        }
    }

    return [$marks, $id_type, $id_reason];
}

function inspect_xlsx_add_font_style($styles_xml, $rgb) {
    if (!class_exists('DOMDocument')) return [false, $styles_xml];

    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    if (!@$dom->loadXML($styles_xml)) return [false, $styles_xml];

    $root = $dom->documentElement;
    $ns = $root->namespaceURI;
    $fonts = $dom->getElementsByTagName('fonts')->item(0);
    $cell_xfs = $dom->getElementsByTagName('cellXfs')->item(0);
    if (!$fonts || !$cell_xfs) return [false, $styles_xml];

    $font = $dom->createElementNS($ns, 'font');
    $sz = $dom->createElementNS($ns, 'sz');
    $sz->setAttribute('val', '11');
    $font->appendChild($sz);
    $color = $dom->createElementNS($ns, 'color');
    $color->setAttribute('rgb', $rgb);
    $font->appendChild($color);
    $name = $dom->createElementNS($ns, 'name');
    $name->setAttribute('val', 'Calibri');
    $font->appendChild($name);

    $font_id = $fonts->getElementsByTagName('font')->length;
    $fonts->appendChild($font);
    $fonts->setAttribute('count', (string)($font_id + 1));

    $xf = $dom->createElementNS($ns, 'xf');
    $xf->setAttribute('numFmtId', '0');
    $xf->setAttribute('fontId', (string)$font_id);
    $xf->setAttribute('fillId', '0');
    $xf->setAttribute('borderId', '0');
    $xf->setAttribute('xfId', '0');
    $xf->setAttribute('applyFont', '1');

    $style_id = $cell_xfs->getElementsByTagName('xf')->length;
    $cell_xfs->appendChild($xf);
    $cell_xfs->setAttribute('count', (string)($style_id + 1));

    return [$style_id, $dom->saveXML()];
}

// 1-based 컬럼 인덱스를 엑셀 컬럼 문자로 변환 (9 -> "I")
function inspect_col_letter($idx) {
    $letters = '';
    while ($idx > 0) {
        $rem = ($idx - 1) % 26;
        $letters = chr(65 + $rem) . $letters;
        $idx = intdiv($idx - 1, 26);
    }
    return $letters;
}

// 검수결과(사유)를 기록할 컬럼 인덱스: EO 표준 헤더의 Lon(deg) 오른쪽 컬럼(K)을 기본값으로 사용합니다.
define('INSPECT_RESULT_COL_IDX', 11);
define('INSPECT_LEGACY_RESULT_COL_IDX', 9);

function inspect_xlsx_result_col_idx($dom, $shared_strings = []) {
    $default_idx = INSPECT_RESULT_COL_IDX;
    foreach ($dom->getElementsByTagName('row') as $row) {
        if ((int)$row->getAttribute('r') !== 1) continue;

        $lon_idx = 0;
        foreach ($row->getElementsByTagName('c') as $cell) {
            $col_idx = img_xlsx_col_index((string)$cell->getAttribute('r'));
            $value = strtolower(trim((string)img_xlsx_cell_value(simplexml_import_dom($cell), $shared_strings)));

            if ($value === '검수결과' || $value === 'inspection' || str_contains($value, 'inspect')) {
                return $col_idx;
            }

            if ($value === 'lon(deg)' || $value === 'lon' || str_contains($value, 'longitude')) {
                $lon_idx = $col_idx;
            }
        }

        if ($lon_idx > 0) return $lon_idx + 1;
        break;
    }

    return $default_idx;
}

function inspect_color_xlsx_rows($file_path, $records, $marks, $id_reason = [], $clear_unmarked = true) {
    $file_path = img_resolve_existing_path($file_path);
    if (!class_exists('ZipArchive') || !class_exists('DOMDocument') || !$file_path || !file_exists($file_path)) return false;

    $zip = new ZipArchive;
    if ($zip->open($file_path) !== TRUE) return false;

    $styles_xml = $zip->getFromName('xl/styles.xml');
    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$styles_xml || !$sheet_xml) {
        $zip->close();
        return false;
    }

    list($reshoot_style, $styles_xml) = inspect_xlsx_add_font_style($styles_xml, 'FFFF0000');
    list($buffer_style, $styles_xml) = inspect_xlsx_add_font_style($styles_xml, 'FF00B0F0');
    list($duplicate_style, $styles_xml) = inspect_xlsx_add_font_style($styles_xml, 'FF00B050');
    if ($reshoot_style === false || $buffer_style === false || $duplicate_style === false) {
        $zip->close();
        return false;
    }

    $row_styles = [];
    foreach ($records as $rec) {
        $mark = $marks[$rec['id']] ?? '';
        if ($mark === 'reshoot') $row_styles[(int)$rec['row_no']] = $reshoot_style;
        else if ($mark === 'buffer') $row_styles[(int)$rec['row_no']] = $buffer_style;
        else if ($mark === 'duplicate') $row_styles[(int)$rec['row_no']] = $duplicate_style;
    }

    // 행 번호별로 기록할 검수결과 텍스트 계산 ("재촬영: 사유" / "중복미사용: 사유" / buffer,none 이면 빈 값)
    $row_result_text = [];
    foreach ($records as $rec) {
        $row_no = (int)$rec['row_no'];
        $mark = $marks[$rec['id']] ?? '';
        if ($mark === 'reshoot') {
            $reason = trim((string)($id_reason[strtoupper($rec['id'])] ?? ''));
            $row_result_text[$row_no] = $reason !== '' ? ('재촬영: ' . $reason) : '재촬영';
        } else if ($mark === 'duplicate') {
            $reason = trim((string)($id_reason[strtoupper($rec['id'])] ?? ''));
            $row_result_text[$row_no] = $reason !== '' ? ('중복미사용: ' . $reason) : '중복미사용';
        } else if ($clear_unmarked) {
            // buffer 이거나 마크가 없는 행: 이전 검수 라운드에서 남아있을 수 있는 값을 지워줌
            $row_result_text[$row_no] = '';
        }
    }

    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    if (!@$dom->loadXML($sheet_xml)) {
        $zip->close();
        return false;
    }

    $ns = $dom->documentElement->namespaceURI;
    $shared_strings = img_xlsx_shared_strings($zip);
    $result_col_idx = inspect_xlsx_result_col_idx($dom, $shared_strings);
    $result_col_letter = inspect_col_letter($result_col_idx);

    // 검수결과(사유) 셀 값을 해당 행에 기록(없으면 새로 생성, 있으면 갱신)하는 헬퍼
    $write_result_cell = function($row, $row_no) use ($dom, $ns, $shared_strings, $result_col_idx, $result_col_letter, &$row_result_text, &$row_styles) {
        if (!isset($row_result_text[$row_no])) return;
        $text = $row_result_text[$row_no];

        $existing_cell = null;
        $legacy_cell = null;
        foreach ($row->getElementsByTagName('c') as $cell) {
            $col_idx = img_xlsx_col_index((string)$cell->getAttribute('r'));
            if ($col_idx === $result_col_idx) {
                $existing_cell = $cell;
            } else if ($col_idx === INSPECT_LEGACY_RESULT_COL_IDX) {
                $legacy_cell = $cell;
            }
        }

        if ($legacy_cell && $result_col_idx !== INSPECT_LEGACY_RESULT_COL_IDX) {
            $legacy_value = trim((string)img_xlsx_cell_value(simplexml_import_dom($legacy_cell), $shared_strings));
            if (str_starts_with($legacy_value, '재촬영') || str_starts_with($legacy_value, '중복미사용')) {
                $row->removeChild($legacy_cell);
            }
        }

        if ($text === '') {
            if ($existing_cell) $row->removeChild($existing_cell);
            return;
        }

        $style_id = $row_styles[$row_no] ?? null;

        if ($existing_cell) {
            // 기존 셀 내용을 인라인 문자열로 교체
            foreach (iterator_to_array($existing_cell->childNodes) as $child) {
                $existing_cell->removeChild($child);
            }
            $existing_cell->removeAttribute('t');
            $existing_cell->setAttribute('t', 'inlineStr');
            if ($style_id !== null) $existing_cell->setAttribute('s', (string)$style_id);
            $is = $dom->createElementNS($ns, 'is');
            $t = $dom->createElementNS($ns, 't');
            $t->appendChild($dom->createTextNode($text));
            $is->appendChild($t);
            $existing_cell->appendChild($is);
        } else {
            $new_cell = $dom->createElementNS($ns, 'c');
            $new_cell->setAttribute('r', $result_col_letter . $row_no);
            if ($style_id !== null) $new_cell->setAttribute('s', (string)$style_id);
            $new_cell->setAttribute('t', 'inlineStr');
            $is = $dom->createElementNS($ns, 'is');
            $t = $dom->createElementNS($ns, 't');
            $t->appendChild($dom->createTextNode($text));
            $is->appendChild($t);
            $new_cell->appendChild($is);
            $row->appendChild($new_cell); // 검수결과 컬럼은 항상 최우측이므로 맨 뒤에 추가
        }
    };

    foreach ($dom->getElementsByTagName('row') as $row) {
        $row_no = (int)$row->getAttribute('r');
        if (isset($row_styles[$row_no])) {
            foreach ($row->getElementsByTagName('c') as $cell) {
                $cell->setAttribute('s', (string)$row_styles[$row_no]);
            }
        }
        $write_result_cell($row, $row_no);
    }

    // 1행에 "검수결과" 헤더가 없으면 추가
    $header_written = false;
    foreach ($dom->getElementsByTagName('row') as $row) {
        if ((int)$row->getAttribute('r') !== 1) continue;
        foreach ($row->getElementsByTagName('c') as $cell) {
            if (img_xlsx_col_index((string)$cell->getAttribute('r')) === $result_col_idx) {
                $header_written = true;
                break;
            }
        }
        if (!$header_written) {
            $header_cell = $dom->createElementNS($ns, 'c');
            $header_cell->setAttribute('r', $result_col_letter . '1');
            $header_cell->setAttribute('t', 'inlineStr');
            $is = $dom->createElementNS($ns, 'is');
            $t = $dom->createElementNS($ns, 't');
            $t->appendChild($dom->createTextNode('검수결과'));
            $is->appendChild($t);
            $header_cell->appendChild($is);
            $row->appendChild($header_cell);
        }
        break;
    }

    $dimension = $dom->getElementsByTagName('dimension')->item(0);
    if ($dimension && preg_match('/^([A-Z]+\d+):([A-Z]+)(\d+)$/i', (string)$dimension->getAttribute('ref'), $m)) {
        $dimension->setAttribute('ref', $m[1] . ':' . $result_col_letter . $m[3]);
    }

    $zip->addFromString('xl/styles.xml', $styles_xml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $dom->saveXML());
    $zip->close();

    return true;
}

// 1. 신규 촬영일 등록
if ($action === 'add_flight_date') {
    $flight_name    = isset($_POST['flight_name']) ? trim($_POST['flight_name']) : '';
    $flight_date    = isset($_POST['flight_date']) ? trim($_POST['flight_date']) : '';
    $sensor_name    = isset($_POST['sensor_name']) ? trim($_POST['sensor_name']) : '';
    $parsed_shots   = isset($_POST['parsed_shots']) ? (int)$_POST['parsed_shots'] : 0;
    $matched_blocks = isset($_POST['matched_blocks_str']) ? trim($_POST['matched_blocks_str']) : '';

    if (!$flight_date) {
        action_error_toast($prj_id, 'tab-flight', '촬영일자를 입력해주세요.');
    }

    $target_dir = $base_dir . '\\date\\' . $flight_date;
    $enc_target = iconv('UTF-8', 'CP949//IGNORE', $target_dir);
    $eo_dir     = $target_dir . '\\EO';
    $enc_eo_dir = iconv('UTF-8', 'CP949//IGNORE', $eo_dir);

    if (!is_dir($enc_target)) {
        @mkdir($enc_target, 0777, true);
        create_sub_dirs($target_dir);
    }

    $uploaded_eo_name = '';
    if (isset($_FILES['eo_file']) && $_FILES['eo_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['eo_file']['tmp_name'];
        $orig_name = $_FILES['eo_file']['name'];
        $uploaded_eo_name = $orig_name;

        $dest_file = $eo_dir . '\\' . $orig_name;
        $enc_dest_file = iconv('UTF-8', 'CP949//IGNORE', $dest_file);
        @move_uploaded_file($file_tmp, $enc_dest_file);
    }

    $sql = " INSERT INTO IMG_FLIGHT_DATE 
             SET prj_id = {$prj_id},
                 flight_name = '".sql_real_escape_string($flight_name)."',
                 flight_date = '".sql_real_escape_string($flight_date)."',
                 sensor_name = '".sql_real_escape_string($sensor_name)."',
                 eo_file_name = '".sql_real_escape_string($uploaded_eo_name)."',
                 total_shots = {$parsed_shots},
                 used_shots = {$parsed_shots},
                 unused_shots = 0,
                 reshoot_shots = 0,
                 matched_blocks = '".sql_real_escape_string($matched_blocks)."',
                 mb_id = '".sql_real_escape_string($member['mb_id'])."',
                 mb_name = '".sql_real_escape_string($current_user_name)."',
                 status = 'ACTIVE',
                 created_at = NOW() ";
    sql_query($sql);

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=flight_date_ok&val='.$flight_date);
}

// 2. 검수내역 업데이트
if ($action === 'update_flight_inspect') {
    $date_id       = isset($_POST['date_id']) ? (int)$_POST['date_id'] : 0;
    $used_shots    = isset($_POST['used_shots']) ? (int)$_POST['used_shots'] : 0;
    $unused_shots  = isset($_POST['unused_shots']) ? (int)$_POST['unused_shots'] : 0;
    $reshoot_shots = isset($_POST['reshoot_shots']) ? (int)$_POST['reshoot_shots'] : 0;

    if (!$date_id) {
        action_error_toast($prj_id, 'tab-flight', '수정할 촬영일 정보를 찾을 수 없습니다.');
    }

    sql_query(" UPDATE IMG_FLIGHT_DATE 
                SET used_shots = {$used_shots},
                    unused_shots = {$unused_shots},
                    reshoot_shots = {$reshoot_shots}
                WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=inspect_ok');
}

if ($action === 'apply_flight_inspection') {
    $date_id = isset($_POST['date_id']) ? (int)$_POST['date_id'] : 0;
    $inspection_payload_raw = isset($_POST['inspection_json']) ? trim($_POST['inspection_json']) : '';

    // 클라이언트에서 Base64로 인코딩해 전송한 JSON을 우선 디코딩 시도.
    // (그누보드 공통 필터가 원본 JSON의 따옴표 등을 손상시키는 문제를 회피하기 위함)
    $inspection_items = [];
    if ($inspection_payload_raw !== '') {
        $decoded_json = base64_decode($inspection_payload_raw, true);
        if ($decoded_json !== false) {
            $parsed = json_decode($decoded_json, true);
            if (is_array($parsed)) $inspection_items = $parsed;
        }
        // Base64 디코딩에 실패했거나 결과가 비어있다면, 혹시 모를 구버전 클라이언트 호환을 위해 원본을 그대로 JSON 파싱 시도
        if (empty($inspection_items)) {
            $parsed_fallback = json_decode($inspection_payload_raw, true);
            if (is_array($parsed_fallback)) $inspection_items = $parsed_fallback;
        }
    }

    if (!is_array($inspection_items)) {
        $inspection_items = [];
    }

    if (empty($inspection_items) && !empty($_POST['inspect_selected_ids']) && is_array($_POST['inspect_selected_ids'])) {
        $select_reason = isset($_POST['inspect_reason_select']) ? trim((string)$_POST['inspect_reason_select']) : '';
        $custom_reason = isset($_POST['inspect_custom_reason']) ? trim((string)$_POST['inspect_custom_reason']) : '';
        $manual_reason = isset($_POST['manual_reason']) ? trim((string)$_POST['manual_reason']) : '';

        $reason = $manual_reason;
        if ($reason === '') {
            $reason = ($select_reason === '직접입력') ? $custom_reason : $select_reason;
        }

        foreach ($_POST['inspect_selected_ids'] as $selected_id) {
            $selected_id = trim((string)$selected_id);
            if ($selected_id === '' || $reason === '') continue;

            $inspection_items[] = [
                'id' => $selected_id,
                'reason' => $reason,
                'type' => (mb_strpos($reason, '미사용') !== false) ? 'duplicate' : 'reshoot'
            ];
        }
    }

    if (!$date_id || empty($inspection_items)) {
        action_error_toast($prj_id, 'tab-flight', '저장할 검수내역을 찾을 수 없습니다.');
    }

    $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    if (!$flight || !trim((string)$flight['eo_file_name'])) {
        action_error_toast($prj_id, 'tab-flight', '활성 EO 성과 파일이 없습니다.');
    }

    $current_eo_name = trim(explode(',', (string)$flight['eo_file_name'])[0]);
    $current_eo_name = basename($current_eo_name);
    $eo_dir = $base_dir . '\\date\\' . trim($flight['flight_date']) . '\\EO';
    $source_fs_path = inspect_existing_eo_path($eo_dir, $current_eo_name);

    if (!$source_fs_path || !file_exists($source_fs_path)) {
        action_error_toast($prj_id, 'tab-flight', '현재 활성 EO 파일을 찾을 수 없습니다.');
    }

    $new_eo_name = inspect_completed_eo_name($eo_dir, $current_eo_name);
    $new_path_fs = inspect_copy_eo_file($source_fs_path, $eo_dir, $new_eo_name);
    if (!$new_path_fs || !file_exists($new_path_fs)) {
        action_error_toast($prj_id, 'tab-flight', '검수완료 EO 파일을 생성하지 못했습니다. EO 폴더 쓰기 권한을 확인해주세요.');
    }

    $records = inspect_eo_records($new_path_fs);
    list($marks, $id_type, $id_reason) = inspect_mark_map($records, $inspection_items);

    $ext = strtolower(pathinfo($new_eo_name, PATHINFO_EXTENSION));
    if ($ext === 'xlsx' && !empty($records)) {
        inspect_color_xlsx_rows($new_path_fs, $records, $marks, $id_reason);
    }

    $total_shots = empty($records) ? (int)$flight['total_shots'] : count($records);
    $reshoot_shots = 0;
    $duplicate_shots = 0;
    foreach ($id_type as $type) {
        if ($type === 'duplicate') $duplicate_shots++;
        else $reshoot_shots++;
    }

    $used_shots = $total_shots - $reshoot_shots - $duplicate_shots;
    if ($used_shots < 0) $used_shots = 0;

    sql_query(" UPDATE IMG_FLIGHT_DATE 
                SET eo_file_name = '".sql_real_escape_string($new_eo_name)."',
                    total_shots = {$total_shots},
                    used_shots = {$used_shots},
                    unused_shots = {$duplicate_shots},
                    reshoot_shots = {$reshoot_shots},
                    mb_id = '".sql_real_escape_string($member['mb_id'])."',
                    mb_name = '".sql_real_escape_string($current_user_name)."'
                WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=inspect_ok&val='.urlencode($new_eo_name));
}

function inspect_eo_count_summary($file_path) {
    $rows = img_eo_rows_from_file($file_path);
    $inspect_col_idx = img_eo_inspect_result_col_index($rows);
    $seen = [];
    $total = 0;
    $reshoot = 0;
    $duplicate = 0;

    foreach ($rows as $cols) {
        if (!is_array($cols) || count($cols) < 8) continue;

        $id = trim((string)($cols[0] ?? ''));
        if ($id === '' || stripos($id, 'id') !== false || stripos($id, 'photo') !== false) continue;
        if (img_eo_course_no_from_id($id) <= 0) continue;

        $key = strtoupper($id);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $total++;

        $inspect_result = img_eo_inspect_result_value($cols, $inspect_col_idx);
        if (str_starts_with($inspect_result, '중복미사용') || str_starts_with($inspect_result, '미사용')) $duplicate++;
        else if (str_starts_with($inspect_result, '재촬영')) $reshoot++;
    }

    $used = $total - $reshoot - $duplicate;
    if ($used < 0) $used = 0;

    return [
        'total' => $total,
        'used' => $used,
        'duplicate' => $duplicate,
        'reshoot' => $reshoot
    ];
}

if ($action === 'apply_duplicate_video_selection') {
    $selection_payload_raw = isset($_POST['selection_json']) ? trim((string)$_POST['selection_json']) : '';
    $keep_by_id = [];

    if ($selection_payload_raw !== '') {
        $decoded_json = base64_decode($selection_payload_raw, true);
        if ($decoded_json !== false) {
            $parsed = json_decode($decoded_json, true);
            if (is_array($parsed)) $keep_by_id = $parsed;
        }
        if (empty($keep_by_id)) {
            $parsed_fallback = json_decode($selection_payload_raw, true);
            if (is_array($parsed_fallback)) $keep_by_id = $parsed_fallback;
        }
    }

    if (empty($keep_by_id) || !is_array($keep_by_id)) {
        action_error_toast($prj_id, 'tab-flight', '반영할 중복영상 선택값이 없습니다.');
    }

    $groups = img_flight_duplicate_video_groups($prj_id, $prj['prj_name']);
    $duplicate_items_by_date = [];

    foreach ($groups as $group) {
        $id = strtoupper(trim((string)($group['id'] ?? '')));
        $keep_date_id = isset($keep_by_id[$id]) ? (int)$keep_by_id[$id] : 0;
        if ($id === '' || $keep_date_id <= 0) continue;

        $valid_date_ids = [];
        foreach ($group['items'] as $item) {
            $valid_date_ids[(int)$item['date_id']] = $item;
        }
        if (!isset($valid_date_ids[$keep_date_id])) continue;

        $keep_date = $valid_date_ids[$keep_date_id]['flight_date'] ?? '';
        foreach ($valid_date_ids as $date_id => $item) {
            if ($date_id === $keep_date_id) continue;
            if (!isset($duplicate_items_by_date[$date_id])) $duplicate_items_by_date[$date_id] = [];
            $duplicate_items_by_date[$date_id][] = [
                'id' => $id,
                'reason' => '중복영상 ' . $keep_date . ' 사용',
                'type' => 'duplicate'
            ];
        }
    }

    if (empty($duplicate_items_by_date)) {
        action_error_toast($prj_id, 'tab-flight', '처리할 중복영상이 없습니다.');
    }

    $updated_files = 0;
    foreach ($duplicate_items_by_date as $date_id => $inspection_items) {
        $date_id = (int)$date_id;
        $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
        if (!$flight || !trim((string)$flight['eo_file_name'])) continue;

        $current_eo_name = trim(explode(',', (string)$flight['eo_file_name'])[0]);
        $current_eo_name = basename($current_eo_name);
        $eo_dir = $base_dir . '\\date\\' . trim($flight['flight_date']) . '\\EO';
        $source_fs_path = inspect_existing_eo_path($eo_dir, $current_eo_name);
        if (!$source_fs_path || !file_exists($source_fs_path)) continue;

        $ext = strtolower(pathinfo($current_eo_name, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            action_error_toast($prj_id, 'tab-flight', '중복영상 반영은 xlsx EO 파일만 지원합니다: ' . $current_eo_name);
        }

        $new_eo_name = inspect_completed_eo_name($eo_dir, $current_eo_name);
        $new_path_fs = inspect_copy_eo_file($source_fs_path, $eo_dir, $new_eo_name);
        if (!$new_path_fs || !file_exists($new_path_fs)) continue;

        $records = inspect_eo_records($new_path_fs);
        list($marks, $id_type, $id_reason) = inspect_mark_map($records, $inspection_items);
        inspect_color_xlsx_rows($new_path_fs, $records, $marks, $id_reason, false);

        $summary = inspect_eo_count_summary($new_path_fs);
        sql_query(" UPDATE IMG_FLIGHT_DATE 
                    SET eo_file_name = '".sql_real_escape_string($new_eo_name)."',
                        total_shots = {$summary['total']},
                        used_shots = {$summary['used']},
                        unused_shots = {$summary['duplicate']},
                        reshoot_shots = {$summary['reshoot']},
                        mb_id = '".sql_real_escape_string($member['mb_id'])."',
                        mb_name = '".sql_real_escape_string($current_user_name)."'
                    WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
        $updated_files++;
    }

    if ($updated_files <= 0) {
        action_error_toast($prj_id, 'tab-flight', '중복영상 반영에 실패했습니다. EO 파일을 확인해주세요.');
    }

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=inspect_ok&val='.urlencode('중복영상 '.$updated_files.'개 EO 반영'));
}

// 3. 상태 토글
if ($action === 'toggle_flight_status') {
    $date_id = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
    if (!$date_id) {
        action_error_toast($prj_id, 'tab-flight', '촬영일 정보를 찾을 수 없습니다.');
    }

    $row = sql_fetch(" SELECT flight_date, status FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    if ($row) {
        $next_status = ($row['status'] === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';
        sql_query(" UPDATE IMG_FLIGHT_DATE SET status = '{$next_status}' WHERE date_id = {$date_id} ");

        $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
        if ($vol_row) {
            sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
        }

        $toast_status = ($next_status === 'ACTIVE') ? 'status_active' : 'status_inactive';
        action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast='.$toast_status.'&val='.$row['flight_date']);
    } else {
        action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight');
    }
}

// 4. 촬영일 선택 삭제 (DB + E드라이브 폴더)
if ($action === 'delete_flight_dates') {
    $flight_ids = isset($_POST['flight_ids']) ? (array)$_POST['flight_ids'] : [];
    if (empty($flight_ids)) {
        action_error_toast($prj_id, 'tab-flight', '삭제할 촬영일을 1개 이상 선택해주세요.');
    }

    $deleted_cnt = 0;
    foreach ($flight_ids as $fid) {
        $fid = (int)$fid;
        if ($fid <= 0) continue;

        $row = sql_fetch(" SELECT flight_date FROM IMG_FLIGHT_DATE WHERE date_id = {$fid} AND prj_id = {$prj_id} ");
        if ($row && !empty($row['flight_date'])) {
            $target_dir = $base_dir . '\\date\\' . trim($row['flight_date']);
            rrmdir($target_dir);

            sql_query(" DELETE FROM IMG_FLIGHT_DATE WHERE date_id = {$fid} AND prj_id = {$prj_id} ");
            $deleted_cnt++;
        }
    }

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    $new_volume = $vol_row ? (int)$vol_row['total_vol'] : 0;
    sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$new_volume} WHERE prj_id = {$prj_id} ");

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=flight_delete_ok&cnt='.$deleted_cnt);
}

// 5. 💡 [추가] 템플릿 기반 신규 문서(촬영기록부/코스별검사표) 생성 액션
if ($action === 'create_doc_from_template') {
    header('Content-Type: application/json; charset=utf-8');
    
    $date_id     = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
    $doc_type    = isset($_GET['doc_type']) ? trim($_GET['doc_type']) : 'flight_log';
    $filename    = isset($_GET['filename']) ? basename(trim($_GET['filename'])) : '';
    $flight_date = isset($_GET['flight_date']) ? trim($_GET['flight_date']) : '';
    $prj_name    = isset($_GET['prj_name']) ? trim($_GET['prj_name']) : $prj['prj_name'];

    if (empty($flight_date) && $date_id > 0) {
        $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} OR id = {$date_id} ");
        $flight_date = trim($flight['flight_date'] ?? '');
    }

    if (empty($prj_name) || empty($flight_date) || empty($filename)) {
        echo json_encode(['success' => false, 'message' => '파라미터가 유효하지 않습니다.']);
        exit;
    }

    // 원본 템플릿 파일 선택 (project/base/ 폴더 내 존재해야 함)
    $tpl_filename = ($doc_type === 'course_inspect') ? 'course_inspect.xlsx' : 'flight_log.xlsx';
    $tpl_path = G5_PATH . '/project/base/' . $tpl_filename;

    if (!file_exists($tpl_path)) {
        echo json_encode(['success' => false, 'message' => '기본 템플릿 파일(' . $tpl_filename . ')이 서버에 없습니다.']);
        exit;
    }

    $doc_dir = img_doc_dir($prj_name, $flight_date);
    $enc_dir = img_fs_path($doc_dir);

    if (!is_dir($enc_dir)) {
        @mkdir($enc_dir, 0777, true);
    }

    $safe_filename = img_doc_filename($filename);
    if (!$safe_filename) {
        echo json_encode(['success' => false, 'message' => '파일명은 .xlsx 확장자를 포함해야 합니다.']);
        exit;
    }
    $dest_path = $enc_dir . '\\' . img_fs_path($safe_filename);

    if (file_exists($dest_path)) {
        echo json_encode(['success' => false, 'message' => '이미 동일한 이름의 문서 파일이 존재합니다.']);
        exit;
    }

    if (@copy($tpl_path, $dest_path)) {
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        $dest_path_utf8 = $doc_dir . '\\' . $safe_filename;
        if (@copy($tpl_path, $dest_path_utf8)) {
            echo json_encode(['success' => true, 'filename' => $filename]);
        } else {
            echo json_encode(['success' => false, 'message' => '서버 파일 쓰기 권한 오류로 생성에 실패했습니다.']);
        }
    }
    exit;
}
?>
