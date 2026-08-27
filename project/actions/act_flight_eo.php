<?php
if (!defined('_GNUBOARD_')) exit;

// 1. 해당 촬영일 EO 폴더 내 파일 목록 조회 (AJAX)
if ($action === 'get_eo_file_list') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $date_id = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
    
    $row = sql_fetch(" SELECT flight_date, eo_file_name FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    if (!$row) {
        echo json_encode(['success' => false, 'message' => '촬영일 정보를 찾을 수 없습니다.']);
        exit;
    }

    $eo_dir = $base_dir . '\\date\\' . trim($row['flight_date']) . '\\EO';
    $enc_eo_dir = iconv('UTF-8', 'CP949//IGNORE', $eo_dir);
    
    $file_list = [];
    if (is_dir($enc_eo_dir)) {
        $files = scandir($enc_eo_dir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            
            $full_path = $enc_eo_dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($full_path)) {
                $utf_name = iconv('CP949', 'UTF-8//IGNORE', $f);
                $file_list[] = [
                    'filename' => $utf_name,
                    'mtime' => date('Y-m-d H:i:s', filemtime($full_path)),
                    'size' => round(filesize($full_path) / 1024, 1) . ' KB',
                    'is_current' => ($utf_name === $row['eo_file_name'])
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'flight_date' => $row['flight_date'],
        'current_eo' => $row['eo_file_name'],
        'files' => $file_list
    ]);
    exit;
}

// 2. 선택된 EO 파일 내용 읽기 (AJAX)
if ($action === 'read_eo_file_content') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $date_id  = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
    $filename = isset($_GET['filename']) ? trim($_GET['filename']) : '';

    $row = sql_fetch(" SELECT flight_date FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    if (!$row || !$filename) {
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        exit;
    }

    $file_path = $base_dir . '\\date\\' . trim($row['flight_date']) . '\\EO\\' . $filename;
    $enc_path = iconv('UTF-8', 'CP949//IGNORE', $file_path);

    if (!file_exists($enc_path)) {
        echo json_encode(['success' => false, 'message' => '서버에 해당 파일이 존재하지 않습니다.']);
        exit;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($ext, ['txt', 'csv', 'tsv', 'dat'])) {
        $content = file_get_contents($enc_path);
        $utf_content = (mb_detect_encoding($content, 'UTF-8', true)) ? $content : iconv('CP949', 'UTF-8//IGNORE', $content);
        echo json_encode([
            'success' => true,
            'is_binary' => false,
            'filename' => $filename,
            'content' => $utf_content
        ]);
        exit;
    } else {
        $binary_data = file_get_contents($enc_path);
        echo json_encode([
            'success' => true,
            'is_binary' => true,
            'filename' => $filename,
            'base64' => base64_encode($binary_data)
        ]);
        exit;
    }
}

// 3. 선택된 EO 파일로 활성화 및 DB 반영
if ($action === 'apply_selected_eo_file') {
    $date_id        = isset($_POST['date_id']) ? (int)$_POST['date_id'] : 0;
    $filename       = isset($_POST['filename']) ? trim($_POST['filename']) : '';
    $parsed_shots   = isset($_POST['parsed_shots']) ? (int)$_POST['parsed_shots'] : 0;
    $matched_blocks = isset($_POST['matched_blocks']) ? trim($_POST['matched_blocks']) : '';

    if (!$date_id || !$filename) {
        action_error_toast($prj_id, 'tab-flight', '선택된 파일 정보가 올바르지 않습니다.');
    }

    sql_query(" UPDATE IMG_FLIGHT_DATE 
                SET eo_file_name = '".sql_real_escape_string($filename)."',
                    total_shots = {$parsed_shots},
                    used_shots = {$parsed_shots},
                    unused_shots = 0,
                    reshoot_shots = 0,
                    matched_blocks = '".sql_real_escape_string($matched_blocks)."',
                    mb_id = '".sql_real_escape_string($member['mb_id'])."',
                    mb_name = '".sql_real_escape_string($current_user_name)."'
                WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=eo_applied_ok&val='.urlencode($filename));
}
?>