<?php
include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

if (!$is_member) {
    echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
    exit;
}

$prj_id      = isset($_GET['prj_id']) ? (int)$_GET['prj_id'] : 0;
$date_id     = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
$flight_date = isset($_GET['flight_date']) ? trim($_GET['flight_date']) : '';
$prj_name    = isset($_GET['prj_name']) ? trim($_GET['prj_name']) : '';

if (!$prj_id || !$date_id) {
    echo json_encode(['status' => 'error', 'message' => '잘못된 접근 파라미터입니다.']);
    exit;
}

// prj_name / flight_date 가 비어오면 DB에서 다시 조회해서 보강
if (empty($prj_name)) {
    $prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ");
    $prj_name = $prj['prj_name'] ?? '';
}
if (empty($flight_date)) {
    $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    $flight_date = $flight['flight_date'] ?? '';
}

if (empty($prj_name) || empty($flight_date)) {
    echo json_encode(['status' => 'error', 'message' => '프로젝트 또는 촬영일자 정보를 찾을 수 없습니다.']);
    exit;
}

$doc_dir = img_doc_dir($prj_name, $flight_date);
$enc_dir = img_fs_path($doc_dir);

if (!is_dir($enc_dir) && !is_dir($doc_dir)) {
    // 문서 폴더가 아직 없으면 빈 목록으로 정상 응답 (에러 아님)
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

$target_dir = is_dir($enc_dir) ? $enc_dir : $doc_dir;

$data = [];
$scan = @scandir($target_dir);
if ($scan) {
    foreach ($scan as $f) {
        if ($f === '.' || $f === '..') continue;

        $full_path = $target_dir . DIRECTORY_SEPARATOR . $f;
        if (!is_file($full_path)) continue;
        if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) !== 'xlsx') continue;

        // CP949 로 저장된 파일명을 화면 표시용 UTF-8로 변환
        $display_name = (mb_detect_encoding($f, 'UTF-8', true)) ? $f : iconv('CP949', 'UTF-8//IGNORE', $f);

        $doc_type = '기타';
        if (mb_strpos($display_name, '촬영기록부') !== false) {
            $doc_type = '촬영기록부';
        } elseif (mb_strpos($display_name, '코스별검사표') !== false) {
            $doc_type = '코스별검사표';
        }

        $size_kb = round(filesize($full_path) / 1024, 1);

        $data[] = [
            'filename'   => $display_name,
            'filesize'   => $size_kb . ' KB',
            'updated_at' => date('Y-m-d H:i', filemtime($full_path)),
            'doc_type'   => $doc_type
        ];
    }
}

// 최신 수정일 순으로 정렬
usort($data, function ($a, $b) {
    return strcmp($b['updated_at'], $a['updated_at']);
});

echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
exit;
