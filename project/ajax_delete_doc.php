<?php
include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

if (!$is_member) {
    echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
    exit;
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

$prj_id   = isset($data['prj_id']) ? (int)$data['prj_id'] : 0;
$date_id  = isset($data['date_id']) ? (int)$data['date_id'] : 0;
$filename = isset($data['filename']) ? basename(trim($data['filename'])) : '';

if (!$prj_id || !$date_id || !$filename) {
    echo json_encode(['status' => 'error', 'message' => '잘못된 접근 파라미터입니다.']);
    exit;
}

$prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ");
$flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

if (!$prj || !$flight) {
    echo json_encode(['status' => 'error', 'message' => '비행 정보를 찾을 수 없습니다.']);
    exit;
}

$doc_path = 'E:\#KYS_IMAGERY_SERVER\\' . trim($prj['prj_name']) . '\\date\\' . trim($flight['flight_date']) . '\\문서\\' . $filename;
$enc_path = (mb_detect_encoding($doc_path, 'UTF-8', true)) ? iconv('UTF-8', 'CP949//IGNORE', $doc_path) : $doc_path;

if (!file_exists($enc_path)) {
    $enc_path = $doc_path;
}

if (!file_exists($enc_path)) {
    echo json_encode(['status' => 'error', 'message' => '삭제할 파일이 존재하지 않습니다.']);
    exit;
}

if (@unlink($enc_path)) {
    echo json_encode(['status' => 'success', 'message' => '문서가 정상적으로 삭제되었습니다.']);
} else {
    echo json_encode(['status' => 'error', 'message' => '파일 삭제 권한 오류가 발생했습니다.']);
}