<?php
// 상위 루트의 그누보드 코어 common.php 직접 로드
include_once('../common.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 문서/EO/INDEX 원본 저장소의 기준 경로입니다.
// 이전 서버 경로를 그대로 쓰되, 그누보드 이식 환경에서는 config.php 등에서
// IMG_STORAGE_ROOT 상수를 먼저 정의해 새 저장소 위치로 바꿀 수 있습니다.
if (!defined('IMG_STORAGE_ROOT')) {
    define('IMG_STORAGE_ROOT', 'E:\\#KYS_IMAGERY_SERVER');
}

function img_project_path($project_name) {
    return rtrim(IMG_STORAGE_ROOT, '\\\\/') . '\\' . trim($project_name);
}

function img_doc_dir($project_name, $flight_date) {
    return img_project_path($project_name) . '\\date\\' . trim($flight_date) . '\\문서';
}

function img_fs_path($utf8_path) {
    // PHP 8 on Windows/XAMPP handles filesystem paths as UTF-8.  Converting
    // the complete path to CP949 corrupts Korean project and document names,
    // which makes copy(), file_put_contents() and scandir() address a path
    // different from the actual E: drive folder.
    return $utf8_path;
}

function img_doc_filename($filename) {
    $filename = basename(trim((string)$filename));
    if ($filename === '' || !preg_match('/\.xlsx$/i', $filename)) return '';
    return $filename;
}

// 안전 리다이렉트 헬퍼
function action_goto_url($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    echo '<script>location.replace(' . json_encode($url) . ');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    exit;
}

// 에러 토스트 리다이렉트 헬퍼
function action_error_toast($prj_id, $tab, $msg) {
    $url = G5_URL . '/project/view.php?id=' . $prj_id . '&tab=' . $tab . '&toast=error&err_msg=' . urlencode($msg);
    action_goto_url($url);
}

// 서브폴더 일괄 생성 (EO, INDEX, 문서)
function create_sub_dirs($target_path) {
    $sub_folders = ['EO', 'INDEX', '문서'];
    foreach ($sub_folders as $sub) {
        $path = $target_path . '\\' . $sub;
        $enc_path = iconv('UTF-8', 'CP949//IGNORE', $path);
        if (!is_dir($enc_path)) {
            @mkdir($enc_path, 0777, true);
        }
    }
}

// 폴더 및 내부 파일 완전 삭제 (Windows 호환)
function rrmdir($dir) {
    if (!$dir) return;
    
    // UTF-8 -> CP949 변환 (Windows 파일 시스템 경로 호환)
    $enc_dir = (mb_detect_encoding($dir, 'UTF-8', true)) ? iconv('UTF-8', 'CP949//IGNORE', $dir) : $dir;
    if (!file_exists($enc_dir)) return;

    if (is_file($enc_dir) || is_link($enc_dir)) {
        @chmod($enc_dir, 0777);
        @unlink($enc_dir);
        return;
    }

    $files = @scandir($enc_dir);
    if (is_array($files)) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $enc_dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                rrmdir($filePath);
            } else {
                @chmod($filePath, 0777);
                @unlink($filePath);
            }
        }
    }
    
    @chmod($enc_dir, 0777);
    @rmdir($enc_dir);

    // PHP 기본 함수로 삭제되지 않을 경우 Windows 셸 명령으로 강제 삭제(rd /s /q)
    if (file_exists($enc_dir) && is_dir($enc_dir)) {
        @exec('rd /s /q "' . $enc_dir . '"');
    }
}

// 코스 배열 포맷 변환 (예: 1~12코스)
function format_course_range($courses) {
    sort($courses, SORT_NUMERIC);
    $courses = array_values(array_unique($courses));
    if (empty($courses)) return '-';
    
    $min = min($courses);
    $max = max($courses);
    if (count($courses) === ($max - $min + 1)) {
        return ($min === $max) ? "{$min}코스" : "{$min} ~ {$max}코스";
    } else {
        return implode(', ', $courses) . '코스';
    }
}

function img_resolve_existing_path($utf8_path) {
    if ($utf8_path && file_exists($utf8_path)) return $utf8_path;

    $cp949_path = $utf8_path ? iconv('UTF-8', 'CP949//IGNORE', $utf8_path) : '';
    if ($cp949_path && file_exists($cp949_path)) return $cp949_path;

    return $utf8_path;
}

function img_eo_course_no_from_id($id) {
    $id = trim((string)$id);
    if ($id === '') return 0;

    if (stripos($id, 'id') !== false || stripos($id, 'photo') !== false) return 0;

    if (strpos($id, '_') !== false) {
        $course_no = (int)trim(explode('_', $id, 2)[0]);
    } else if (strlen($id) >= 8) {
        $course_no = (int)substr($id, 0, 4);
    } else {
        $course_no = 0;
    }

    return $course_no > 0 ? $course_no : 0;
}

function img_xlsx_shared_strings($zip) {
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if (!$xml) return [];
    if (!function_exists('simplexml_load_string')) return [];

    $strings = [];
    $sx = @simplexml_load_string($xml);
    if (!$sx) return $strings;

    foreach ($sx->si as $si) {
        $text = '';
        if (isset($si->t)) {
            $text = (string)$si->t;
        } else if (isset($si->r)) {
            foreach ($si->r as $run) {
                $text .= (string)$run->t;
            }
        }
        $strings[] = $text;
    }

    return $strings;
}

function img_xlsx_cell_value($cell, $shared_strings) {
    $type = (string)$cell['t'];

    if ($type === 's') {
        $idx = (int)$cell->v;
        return isset($shared_strings[$idx]) ? $shared_strings[$idx] : '';
    }

    if ($type === 'inlineStr') {
        return isset($cell->is->t) ? (string)$cell->is->t : '';
    }

    return isset($cell->v) ? (string)$cell->v : '';
}

function img_xlsx_col_index($cell_ref) {
    if (!preg_match('/^([A-Z]+)/i', (string)$cell_ref, $m)) return 0;

    $letters = strtoupper($m[1]);
    $idx = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $idx = ($idx * 26) + (ord($letters[$i]) - 64);
    }

    return $idx;
}

function img_eo_rows_from_file($file_path) {
    $file_path = img_resolve_existing_path($file_path);
    if (!$file_path || !file_exists($file_path) || is_dir($file_path)) return [];

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $rows = [];

    if (in_array($ext, ['txt', 'csv', 'tsv', 'dat'])) {
        $raw_content = @file_get_contents($file_path);
        if ($raw_content === false) return [];

        $raw_content = preg_replace('/^\xEF\xBB\xBF/', '', $raw_content);
        if (!mb_detect_encoding($raw_content, 'UTF-8', true)) {
            $raw_content = iconv('CP949', 'UTF-8//IGNORE', $raw_content);
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw_content);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) continue;

            if (str_contains($line, "\t")) $parts = explode("\t", $line);
            else if (str_contains($line, ',')) $parts = str_getcsv($line);
            else $parts = preg_split('/\s+/', $line);

            $parts = array_values(array_filter(array_map('trim', $parts), function($v) { return $v !== ''; }));
            if (!empty($parts)) $rows[] = $parts;
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
            $row_data = [];
            foreach ($row->c as $cell) {
                $col_idx = img_xlsx_col_index((string)$cell['r']);
                if ($col_idx <= 0) $col_idx = count($row_data) + 1;
                $row_data[$col_idx - 1] = img_xlsx_cell_value($cell, $shared_strings);
            }
            if (!empty($row_data)) {
                ksort($row_data);
                $rows[] = $row_data;
            }
        }
    }

    return $rows;
}

function img_eo_inspect_result_col_index($rows) {
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

function img_eo_inspect_result_value($cols, $inspect_col_idx) {
    $primary = isset($cols[$inspect_col_idx]) ? trim((string)$cols[$inspect_col_idx]) : '';
    if ($primary !== '') return $primary;

    for ($i = 8; $i < count($cols); $i++) {
        $candidate = trim((string)($cols[$i] ?? ''));
        if (str_starts_with($candidate, '재촬영') || str_starts_with($candidate, '중복미사용')) {
            return $candidate;
        }
    }

    return '';
}

function img_eo_usable_ids_from_file($file_path) {
    $rows = img_eo_rows_from_file($file_path);
    if (empty($rows)) return [];

    $inspect_col_idx = img_eo_inspect_result_col_index($rows);
    $ids = [];

    foreach ($rows as $cols) {
        if (!is_array($cols) || count($cols) < 8) continue;

        $id = trim((string)($cols[0] ?? ''));
        if ($id === '' || stripos($id, 'id') !== false || stripos($id, 'photo') !== false) continue;
        if (img_eo_course_no_from_id($id) <= 0) continue;

        $inspect_result = img_eo_inspect_result_value($cols, $inspect_col_idx);
        if (str_starts_with($inspect_result, '재촬영') || str_starts_with($inspect_result, '중복미사용')) continue;

        $ids[strtoupper($id)] = true;
    }

    return $ids;
}

function img_flight_duplicate_video_counts($prj_id, $project_name) {
    $prj_id = (int)$prj_id;
    if ($prj_id <= 0) return [];

    $base_dir = img_project_path($project_name);
    $counts = [];
    $ids_by_date = [];
    $dates_by_id = [];

    $flight_res = sql_query(" SELECT date_id, flight_date, eo_file_name FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND eo_file_name != '' ");
    if (!$flight_res) return [];

    while ($flight = sql_fetch_array($flight_res)) {
        $date_id = (int)$flight['date_id'];
        $flight_date = trim((string)$flight['flight_date']);
        $eo_names = array_filter(array_map('trim', explode(',', (string)$flight['eo_file_name'])));
        $ids_by_date[$date_id] = [];
        $counts[$date_id] = 0;

        foreach ($eo_names as $eo_name) {
            $eo_name = basename($eo_name);
            if ($flight_date === '' || $eo_name === '') continue;

            $eo_path = $base_dir . '\\date\\' . $flight_date . '\\EO\\' . $eo_name;
            $ids = img_eo_usable_ids_from_file($eo_path);

            foreach ($ids as $id => $_) {
                $ids_by_date[$date_id][$id] = true;
                if (!isset($dates_by_id[$id])) $dates_by_id[$id] = [];
                $dates_by_id[$id][$date_id] = true;
            }
        }
    }

    foreach ($ids_by_date as $date_id => $ids) {
        foreach ($ids as $id => $_) {
            if (isset($dates_by_id[$id]) && count($dates_by_id[$id]) > 1) {
                $counts[$date_id]++;
            }
        }
    }

    return $counts;
}

function img_eo_course_counts_from_file($file_path) {
    $file_path = img_resolve_existing_path($file_path);
    if (!$file_path || !file_exists($file_path) || is_dir($file_path)) return [];

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $course_counts = [];
    $seen_ids = [];

    $add_id = function($id) use (&$course_counts, &$seen_ids) {
        $id = trim((string)$id);
        if ($id === '' || isset($seen_ids[$id])) return;

        $course_no = img_eo_course_no_from_id($id);
        if ($course_no <= 0) return;

        $seen_ids[$id] = true;
        if (!isset($course_counts[$course_no])) $course_counts[$course_no] = 0;
        $course_counts[$course_no]++;
    };

    if (in_array($ext, ['txt', 'csv', 'tsv', 'dat'])) {
        $lines = @file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $parts = preg_split('/[\t,\s]+/', $line);
            if (count($parts) < 8) continue;

            $add_id($parts[0]);
        }
    } else if ($ext === 'xlsx' && class_exists('ZipArchive') && function_exists('simplexml_load_string')) {
        $zip = new ZipArchive;
        if ($zip->open($file_path) !== TRUE) return [];

        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheet_xml) {
            $zip->close();
            return [];
        }

        $shared_strings = img_xlsx_shared_strings($zip);
        $zip->close();

        $sx = @simplexml_load_string($sheet_xml);
        if (!$sx || !isset($sx->sheetData->row)) return [];

        foreach ($sx->sheetData->row as $row) {
            $id = '';
            $max_col = 0;

            foreach ($row->c as $cell) {
                $col_idx = img_xlsx_col_index((string)$cell['r']);
                if ($col_idx > $max_col) $max_col = $col_idx;
                if ($col_idx === 1) {
                    $id = img_xlsx_cell_value($cell, $shared_strings);
                }
            }

            if ($max_col < 8) continue;
            $add_id($id);
        }
    }

    return $course_counts;
}

function img_block_photo_counts($prj_id, $project_name) {
    $prj_id = (int)$prj_id;
    if ($prj_id <= 0) return [];

    $block_counts = [];
    $course_block_map = [];

    $block_res = sql_query(" SELECT block_id, line_list FROM IMG_BLOCK WHERE prj_id = {$prj_id} ");
    if ($block_res) {
        while ($block = sql_fetch_array($block_res)) {
            $block_id = (int)$block['block_id'];
            $block_counts[$block_id] = 0;

            $courses = array_filter(array_map('trim', explode(',', (string)$block['line_list'])));
            foreach ($courses as $course) {
                $course_no = (int)$course;
                if ($course_no <= 0) continue;
                if (!isset($course_block_map[$course_no])) $course_block_map[$course_no] = [];
                $course_block_map[$course_no][] = $block_id;
            }
        }
    }

    if (empty($course_block_map)) return $block_counts;

    $base_dir = img_project_path($project_name);
    $flight_res = sql_query(" SELECT flight_date, eo_file_name FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' AND eo_file_name != '' ");
    if ($flight_res) {
        while ($flight = sql_fetch_array($flight_res)) {
            $flight_date = trim((string)$flight['flight_date']);
            $eo_names = array_filter(array_map('trim', explode(',', (string)$flight['eo_file_name'])));

            foreach ($eo_names as $eo_name) {
                $eo_name = basename($eo_name);
                if ($flight_date === '' || $eo_name === '') continue;

                $eo_path = $base_dir . '\\date\\' . $flight_date . '\\EO\\' . $eo_name;
                $course_counts = img_eo_course_counts_from_file($eo_path);

                foreach ($course_counts as $course_no => $photo_count) {
                    if (!isset($course_block_map[$course_no])) continue;

                    foreach ($course_block_map[$course_no] as $block_id) {
                        $block_counts[$block_id] += (int)$photo_count;
                    }
                }
            }
        }
    }

    return $block_counts;
}
?>
