<?php
if (!defined('_GNUBOARD_')) exit;

header('Content-Type: application/json; charset=utf-8');

$date_id = isset($_REQUEST['date_id']) ? (int)$_REQUEST['date_id'] : 0;
$doc_type = isset($_REQUEST['doc_type']) ? trim($_REQUEST['doc_type']) : 'flight_log';

$flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
if (!$flight) {
    echo json_encode(['success' => false, 'message' => '촬영일 정보를 찾을 수 없습니다.']);
    exit;
}

$doc_dir = img_doc_dir($prj['prj_name'], $flight['flight_date']);
$enc_doc_dir = img_fs_path($doc_dir);

if (!is_dir($enc_doc_dir) && !@mkdir($enc_doc_dir, 0777, true) && !is_dir($enc_doc_dir)) {
    echo json_encode(['success' => false, 'message' => '문서 저장 폴더를 만들 수 없습니다: ' . $doc_dir]);
    exit;
}

// 1. 문서 목록 반환
if ($action === 'get_doc_file_list') {
    $files = [];
    if (is_dir($enc_doc_dir)) {
        $scan = scandir($enc_doc_dir);
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..') continue;
            $utf_filename = mb_detect_encoding($f, 'UTF-8', true) ? $f : iconv('CP949', 'UTF-8//IGNORE', $f);
            $full_path = $enc_doc_dir . DIRECTORY_SEPARATOR . $f;
            
            if (is_file($full_path) && strtolower(pathinfo($utf_filename, PATHINFO_EXTENSION)) === 'xlsx') {
                $files[] = [
                    'filename' => $utf_filename,
                    'mtime'    => date('Y-m-d H:i', filemtime($full_path)),
                    'size'     => round(filesize($full_path) / 1024, 1) . ' KB'
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}

// 2. Base 템플릿 복사하여 새 문서 생성
if ($action === 'create_doc_from_template') {
    $filename = isset($_REQUEST['filename']) ? img_doc_filename($_REQUEST['filename']) : '';
    if (!$filename) {
        echo json_encode(['success' => false, 'message' => '올바른 파일명을 입력해주세요.']);
        exit;
    }

    $templates = [
        'flight_log' => 'flight_log.xlsx',
        'course_inspect' => 'course_inspect.xlsx',
    ];
    $template_name = $templates[$doc_type] ?? '';
    $template_file = $template_name ? __DIR__ . '/../base/' . $template_name : '';
    if (!file_exists($template_file)) {
        echo json_encode(['success' => false, 'message' => '선택한 문서 양식 템플릿(base/' . ($template_name ?: 'unknown') . ')을 찾을 수 없습니다.']);
        exit;
    }

    $target_file = $doc_dir . '\\' . $filename;
    $enc_target = img_fs_path($target_file);

    if (file_exists($enc_target)) {
        echo json_encode(['success' => false, 'message' => '이미 동일한 이름의 문서 파일이 존재합니다.']);
        exit;
    }

    if (!@copy($template_file, $enc_target)) {
        $last_error = error_get_last();
        $detail = $last_error['message'] ?? '원인을 알 수 없습니다.';
        echo json_encode(['success' => false, 'message' => '템플릿을 문서 폴더에 복사하지 못했습니다. ' . $detail]);
        exit;
    }

    echo json_encode(['success' => true, 'filename' => $filename]);
    exit;
}
