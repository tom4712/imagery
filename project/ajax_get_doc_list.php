<?php
if (file_exists('./_common.php')) {
    include_once('./_common.php');
} else if (file_exists('../_common.php')) {
    include_once('../_common.php');
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($is_member) || !$is_member) {
    echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
    exit;
}

$prj_id      = isset($_GET['prj_id']) ? (int)$_GET['prj_id'] : 0;
$date_id     = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
$flight_date = isset($_GET['flight_date']) ? trim($_GET['flight_date']) : '';

$server_root = 'E:\#KYS_IMAGERY_SERVER';
$prj_name    = '';

// 1. 프로젝트명 추출 (모든 컬럼 탐색)
if ($prj_id > 0) {
    $prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} OR id = {$prj_id} LIMIT 1 ");
    if ($prj) {
        foreach ($prj as $k => $v) {
            if (in_array(strtolower($k), ['prj_name', 'name', 'subject', 'title', 'project_name']) && !empty($v)) {
                $prj_name = trim($v);
                break;
            }
        }
    }
}

// 2. flight_date가 비어있을 경우 date_id로 날짜 자동 추출
if (empty($flight_date) && $date_id > 0) {
    // 테이블 내의 날짜 필드명 유연하게 조회
    $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} OR id = {$date_id} LIMIT 1 ");
    if ($flight) {
        foreach ($flight as $k => $v) {
            $val = trim($v);
            // YYYY-MM-DD 형식 매칭
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $flight_date = $val;
                break;
            }
        }
        // 정규식 매칭 실패 시 일반 키 이름 확인
        if (empty($flight_date)) {
            $flight_date = trim($flight['flight_date'] ?? $flight['date'] ?? $flight['fdate'] ?? '');
        }

        // 프로젝트명이 아직 없다면 비행 테이블의 prj_id로 보완
        if (empty($prj_name) && !empty($flight['prj_id'])) {
            $p = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$flight['prj_id']} OR id = {$flight['prj_id']} LIMIT 1 ");
            if ($p) {
                $prj_name = trim($p['prj_name'] ?? $p['name'] ?? '');
            }
        }
    }
}

// 3. 만약 DB에서 프로젝트명을 끝까지 못 찾았을 경우 디렉토리 스캔으로 폴백
if (empty($prj_name) && is_dir($server_root)) {
    $sc = @scandir($server_root);
    if ($sc) {
        foreach ($sc as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            // 해당 프로젝트 폴더 안에 해당 날짜 폴더가 실제로 있는지 확인
            if (!empty($flight_date) && is_dir($server_root . '\\' . $dir . '\\date\\' . $flight_date)) {
                $prj_name = $dir;
                break;
            }
        }
    }
}

if (empty($prj_name) || empty($flight_date)) {
    echo json_encode([
        'status' => 'error', 
        'message' => "프로젝트 또는 날짜를 식별할 수 없습니다. (prj_name: '{$prj_name}', flight_date: '{$flight_date}')"
    ]);
    exit;
}

// 4. E드라이브 문서 폴더 탐색
$doc_dir = $server_root . '\\' . $prj_name . '\\date\\' . $flight_date . '\\문서';
$enc_dir = (mb_detect_encoding($doc_dir, 'UTF-8', true)) ? iconv('UTF-8', 'CP949//IGNORE', $doc_dir) : $doc_dir;

if (!is_dir($enc_dir)) {
    $enc_dir = $doc_dir;
}

// 문서 폴더가 없는 경우 빈 목록 반환
if (!is_dir($enc_dir)) {
    echo json_encode([
        'status' => 'success', 
        'data' => [], 
        'debug_path' => $doc_dir,
        'message' => '문서 폴더가 아직 생성되지 않았습니다.'
    ]);
    exit;
}

$docs = [];
$scan = @scandir($enc_dir);
if ($scan) {
    foreach ($scan as $f) {
        if ($f === '.' || $f === '..') continue;
        $full_path = $enc_dir . '\\' . $f;
        if (is_file($full_path)) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls', 'xlsm'])) continue;

            $utf_filename = (mb_detect_encoding($f, 'UTF-8', true)) ? $f : iconv('CP949', 'UTF-8//IGNORE', $f);
            $size = @filesize($full_path);
            $mtime = @filemtime($full_path);
            
            $docs[] = [
                'filename'   => $utf_filename,
                'filesize'   => round($size / 1024, 1) . ' KB',
                'updated_at' => date('Y-m-d H:i', $mtime)
            ];
        }
    }
}

echo json_encode([
    'status' => 'success', 
    'data' => $docs, 
    'debug_path' => $doc_dir
]);