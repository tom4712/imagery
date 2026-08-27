<?php
if (!defined('_GNUBOARD_')) exit;

// 1. 촬영일 등록
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
                 reshoot_shots = 0,
                 matched_blocks = '".sql_real_escape_string($matched_blocks)."',
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
    $reshoot_shots = isset($_POST['reshoot_shots']) ? (int)$_POST['reshoot_shots'] : 0;

    if (!$date_id) {
        action_error_toast($prj_id, 'tab-flight', '수정할 촬영일 정보를 찾을 수 없습니다.');
    }

    sql_query(" UPDATE IMG_FLIGHT_DATE 
                SET used_shots = {$used_shots},
                    reshoot_shots = {$reshoot_shots}
                WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=inspect_ok');
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

// 4. [핵심] 촬영일 선택 삭제 (물리 디렉토리 + DB 레코드 동시 삭제)
if ($action === 'delete_flight_dates') {
    $flight_ids = isset($_POST['flight_ids']) ? $_POST['flight_ids'] : [];

    // 단일 값 또는 배열 모두 처리 가능하도록 정규화
    if (!is_array($flight_ids)) {
        $flight_ids = array_filter(array_map('intval', explode(',', $flight_ids)));
    } else {
        $flight_ids = array_filter(array_map('intval', $flight_ids));
    }

    if (empty($flight_ids)) {
        action_error_toast($prj_id, 'tab-flight', '삭제할 촬영일을 1개 이상 선택해주세요.');
    }

    $deleted_cnt = 0;
    foreach ($flight_ids as $fid) {
        if ($fid <= 0) continue;

        // DB에서 날짜 확인
        $row = sql_fetch(" SELECT flight_date FROM IMG_FLIGHT_DATE WHERE date_id = {$fid} AND prj_id = {$prj_id} ");
        if ($row && !empty($row['flight_date'])) {
            $flight_date_clean = trim($row['flight_date']);

            // 1. E드라이브 date/[일자] 물리 폴더 강제 삭제
            $target_dir = $base_dir . '\\date\\' . $flight_date_clean;
            rrmdir($target_dir);

            // 2. DB 레코드 삭제 (G5 sql_query)
            $del_res = sql_query(" DELETE FROM IMG_FLIGHT_DATE WHERE date_id = {$fid} AND prj_id = {$prj_id} ", false);
            if ($del_res) {
                $deleted_cnt++;
            }
        }
    }

    if ($deleted_cnt === 0) {
        action_error_toast($prj_id, 'tab-flight', '선택된 촬영일 데이터를 찾지 못했거나 삭제에 실패했습니다.');
    }

    // 3. 메인 프로젝트 총 물량(활성 매수 합산) 즉시 재계산
    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    $new_volume = $vol_row ? (int)$vol_row['total_vol'] : 0;
    sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$new_volume} WHERE prj_id = {$prj_id} ");

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=flight_delete_ok&cnt='.$deleted_cnt);
}

?>