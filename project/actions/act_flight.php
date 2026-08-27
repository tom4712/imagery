<?php
if (!defined('_GNUBOARD_')) exit;

// -------------------------------------------------------------------------
// [AJAX] 1. 해당 촬영일 EO 폴더 내 파일 목록 조회
// -------------------------------------------------------------------------
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

// -------------------------------------------------------------------------
// [AJAX] 2. 선택된 EO 파일 내용 읽기
// -------------------------------------------------------------------------
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

// -------------------------------------------------------------------------
// 3. 선택된 EO 파일로 활성화 및 DB 반영
// -------------------------------------------------------------------------
if ($action === 'apply_selected_eo_file') {
    $date_id        = isset($_POST['date_id']) ? (int)$_POST['date_id'] : 0;
    $filename       = isset($_POST['filename']) ? trim($_POST['filename']) : '';
    $parsed_shots   = isset($_POST['parsed_shots']) ? (int)$_POST['parsed_shots'] : 0;
    $matched_blocks = isset($_POST['matched_blocks']) ? trim($_POST['matched_blocks']) : '';

    if (!$date_id || !$filename) {
        action_error_toast($prj_id, 'tab-flight', '선택된 파일 정보가 올바르지 않습니다.');
    }

    $current_user = $member['mb_name'] ? $member['mb_name'] : $member['mb_nick'];

    sql_query(" UPDATE IMG_FLIGHT_DATE 
                SET eo_file_name = '".sql_real_escape_string($filename)."',
                    total_shots = {$parsed_shots},
                    used_shots = {$parsed_shots},
                    unused_shots = 0,
                    reshoot_shots = 0,
                    matched_blocks = '".sql_real_escape_string($matched_blocks)."',
                    mb_id = '".sql_real_escape_string($member['mb_id'])."',
                    mb_name = '".sql_real_escape_string($current_user)."'
                WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=eo_applied_ok&val='.urlencode($filename));
}

// -------------------------------------------------------------------------
// 4. 신규 촬영일 등록
// -------------------------------------------------------------------------
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

    $current_user = $member['mb_name'] ? $member['mb_name'] : $member['mb_nick'];

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
                 mb_name = '".sql_real_escape_string($current_user)."',
                 status = 'ACTIVE',
                 created_at = NOW() ";
    sql_query($sql);

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=flight_date_ok&val='.$flight_date);
}

// -------------------------------------------------------------------------
// 5. 검수내역 업데이트 (미사용 매수 포함)
// -------------------------------------------------------------------------
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

// -------------------------------------------------------------------------
// 6. 촬영일 활성 / 비활성 토글
// -------------------------------------------------------------------------
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

// -------------------------------------------------------------------------
// 7. 촬영일 선택 삭제 (DB + E드라이브 폴더 완전 삭제)
// -------------------------------------------------------------------------
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
?>