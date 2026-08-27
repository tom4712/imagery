<?php
if (!defined('_GNUBOARD_')) exit;

header('Content-Type: application/json; charset=utf-8');

$date_id  = isset($_REQUEST['date_id']) ? (int)$_REQUEST['date_id'] : 0;
$doc_type = isset($_REQUEST['doc_type']) ? trim($_REQUEST['doc_type']) : 'flight_log';

$flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
if (!$flight) {
    echo json_encode(['success' => false, 'message' => '촬영일 정보를 찾을 수 없습니다.']);
    exit;
}

// 순수 UTF-8 경로 유지 및 디렉터리 재귀 생성 (Windows PHP 8.x 호환)
$doc_dir = 'E:\#KYS_IMAGERY_SERVER\\' . trim($prj['prj_name']) . '\\date\\' . trim($flight['flight_date']) . '\\문서';

if (!is_dir($doc_dir)) {
    @mkdir($doc_dir, 0777, true);
}

// 1. 문서 파일 목록 반환
if ($action === 'get_doc_file_list') {
    $files = [];
    if (is_dir($doc_dir)) {
        $scan = @scandir($doc_dir);
        if ($scan) {
            foreach ($scan as $f) {
                if ($f === '.' || $f === '..') continue;
                $full_path = $doc_dir . DIRECTORY_SEPARATOR . $f;
                
                if (is_file($full_path) && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'xlsx') {
                    $files[] = [
                        'filename' => $f,
                        'mtime'    => date('Y-m-d H:i', filemtime($full_path)),
                        'size'     => round(filesize($full_path) / 1024, 1) . ' KB'
                    ];
                }
            }
        }
    }
    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}

// 2. Base 템플릿 복사하여 새 문서 생성
if ($action === 'create_doc_from_template') {
    $filename = isset($_REQUEST['filename']) ? basename(trim($_REQUEST['filename'])) : '';
    if (!$filename) {
        echo json_encode(['success' => false, 'message' => '올바른 파일명을 입력해주세요.']);
        exit;
    }

    // 템플릿 파일 후보 탐색 (순수 UTF-8 경로)
    $candidate_paths = [
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . 'flight_log.xlsx',
        __DIR__ . '/../base/flight_log.xlsx'
    ];

    $template_file = '';
    foreach ($candidate_paths as $p) {
        if (file_exists($p) && is_file($p)) {
            $template_file = $p;
            break;
        }
    }

    if (!$template_file) {
        echo json_encode([
            'success' => false, 
            'message' => '양식 템플릿(base/flight_log.xlsx)을 찾을 수 없습니다.'
        ]);
        exit;
    }

    $target_file = $doc_dir . DIRECTORY_SEPARATOR . $filename;

    if (file_exists($target_file)) {
        echo json_encode(['success' => false, 'message' => '이미 동일한 이름의 문서 파일이 존재합니다.']);
        exit;
    }

    // 디렉터리 존재 여부 다시 확인 후 복사
    if (!is_dir($doc_dir)) {
        @mkdir($doc_dir, 0777, true);
    }

    if (!@copy($template_file, $target_file)) {
        $last_err = error_get_last();
        $err_detail = isset($last_err['message']) ? ' (' . $last_err['message'] . ')' : '';
        echo json_encode(['success' => false, 'message' => '템플릿 복사 및 문서 생성 실패' . $err_detail]);
        exit;
    }

    @chmod($target_file, 0777);

    echo json_encode(['success' => true, 'filename' => $filename]);
    exit;
}
?>