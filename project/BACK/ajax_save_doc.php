<?php
include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

if (!$is_member) {
    echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
    exit;
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

$prj_id      = isset($data['prj_id']) ? (int)$data['prj_id'] : 0;
$date_id     = isset($data['date_id']) ? (int)$data['date_id'] : 0;
$filename    = isset($data['filename']) ? img_doc_filename($data['filename']) : '';
$file_base64 = isset($data['file_base64']) ? $data['file_base64'] : '';

if (!$prj_id || !$date_id || !$filename || empty($file_base64)) {
    echo json_encode(['status' => 'error', 'message' => '전송된 데이터가 올바르지 않습니다.']);
    exit;
}

$prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ");
$flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

if (!$prj || !$flight) {
    echo json_encode(['status' => 'error', 'message' => '프로젝트 또는 촬영일자 정보를 찾을 수 없습니다.']);
    exit;
}

$doc_dir  = img_doc_dir($prj['prj_name'], $flight['flight_date']);
$doc_path = $doc_dir . '\\' . $filename;

// UTF-8 -> CP949 경로 인코딩 처리
$enc_dir  = img_fs_path($doc_dir);
$enc_path = img_fs_path($doc_path);

if (!is_dir($enc_dir)) {
    @mkdir($enc_dir, 0777, true);
}

// Base64 디코딩 및 파일 덮어쓰기 저장
$binary_data = base64_decode($file_base64, true);
if ($binary_data === false) {
    echo json_encode(['status' => 'error', 'message' => '엑셀 데이터 디코딩에 실패했습니다.']);
    exit;
}

$save_result = @file_put_contents($enc_path, $binary_data);

if ($save_result === false) {
    // CP949 실패 시 원본 경로로 재시도
    $save_result = @file_put_contents($doc_path, $binary_data);
}

if ($save_result !== false) {
    echo json_encode(['status' => 'success', 'message' => '문서 폴더에 성공적으로 저장되었습니다.']);
} else {
    echo json_encode(['status' => 'error', 'message' => '서버 파일 쓰기 권한 오류로 저장에 실패했습니다.']);
}
