<?php
if (!defined('_GNUBOARD_')) exit;

// 등록된 코스 맵 가져오기
function get_existing_course_map($prj_id, $exclude_block_name = '') {
    $map = [];
    $sql = " SELECT block_name, line_list FROM IMG_BLOCK WHERE prj_id = {$prj_id} ";
    if ($exclude_block_name) {
        $sql .= " AND block_name != '" . sql_real_escape_string($exclude_block_name) . "' ";
    }
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        if (!$row['line_list']) continue;
        $lines = explode(',', $row['line_list']);
        foreach ($lines as $ln) {
            $ln = (int)trim($ln);
            if ($ln > 0) $map[$ln] = $row['block_name'];
        }
    }
    return $map;
}

// 1. 단일 수동 등록
if ($action === 'add_block_single') {
    $block_name = isset($_POST['block_name']) ? trim($_POST['block_name']) : '';
    $start_line = isset($_POST['start_line']) ? (int)$_POST['start_line'] : 0;
    $end_line   = isset($_POST['end_line']) ? (int)$_POST['end_line'] : 0;

    if (!$block_name || !$start_line || !$end_line) {
        action_error_toast($prj_id, 'tab-block', '블럭명과 시작/종료 코스를 모두 입력해주세요.');
    }

    if ($start_line > $end_line) {
        $tmp = $start_line; $start_line = $end_line; $end_line = $tmp;
    }

    $safe_block_name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', '', $block_name);
    $new_courses = range($start_line, $end_line);

    // 중복 검증
    $existing_map = get_existing_course_map($prj_id, $safe_block_name);
    $duplicated = [];
    foreach ($new_courses as $c_no) {
        if (isset($existing_map[$c_no])) {
            $duplicated[] = "{$c_no}코스(기존: {$existing_map[$c_no]})";
        }
    }

    if (!empty($duplicated)) {
        $dup_msg = implode(', ', array_slice($duplicated, 0, 3));
        if (count($duplicated) > 3) $dup_msg .= " 외 " . (count($duplicated) - 3) . "건";
        action_error_toast($prj_id, 'tab-block', '코스 중복: ' . $dup_msg . '이 이미 등록되어 있습니다.');
    }

    $line_count = count($new_courses);
    $line_range = ($start_line === $end_line) ? "{$start_line}코스" : "{$start_line} ~ {$end_line}코스";
    $line_list = implode(',', $new_courses);

    // 폴더 생성
    $target_dir = $base_dir . '\\block\\' . $safe_block_name;
    $enc_target = iconv('UTF-8', 'CP949//IGNORE', $target_dir);
    if (!is_dir($enc_target)) {
        @mkdir($enc_target, 0777, true);
        create_sub_dirs($target_dir);
    }

    $sql = " INSERT INTO IMG_BLOCK 
             SET prj_id = {$prj_id},
                 block_name = '".sql_real_escape_string($safe_block_name)."',
                 line_range = '".sql_real_escape_string($line_range)."',
                 line_count = {$line_count},
                 line_list = '".sql_real_escape_string($line_list)."',
                 photo_count = 0,
                 mb_id = '".sql_real_escape_string($member['mb_id'])."',
                 mb_name = '".sql_real_escape_string($current_user_name)."',
                 created_at = NOW()
             ON DUPLICATE KEY UPDATE
                 line_range = '".sql_real_escape_string($line_range)."',
                 line_count = {$line_count},
                 line_list = '".sql_real_escape_string($line_list)."',
                 mb_id = '".sql_real_escape_string($member['mb_id'])."',
                 mb_name = '".sql_real_escape_string($current_user_name)."',
                 created_at = NOW() ";
    sql_query($sql);

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-block&toast=block_single_ok&val='.urlencode($safe_block_name));
}

// 2. 텍스트 일괄 등록
if ($action === 'add_block_bulk') {
    $bulk_text = isset($_POST['bulk_text']) ? trim($_POST['bulk_text']) : '';
    if (!$bulk_text) {
        action_error_toast($prj_id, 'tab-block', '일괄 등록할 텍스트 데이터를 입력해주세요.');
    }

    $lines = preg_split('/\r\n|\r|\n/', $bulk_text);
    $block_map = [];
    $input_course_owners = [];
    $internal_duplicates = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        $parts = preg_split('/\s+/', $line);
        if (count($parts) >= 2) {
            $b_name = trim($parts[0]);
            $c_no = (int)$parts[1];
            if ($b_name && $c_no > 0) {
                if (isset($input_course_owners[$c_no]) && $input_course_owners[$c_no] !== $b_name) {
                    $internal_duplicates[] = "{$c_no}코스({$input_course_owners[$c_no]} ↔ {$b_name})";
                } else {
                    $input_course_owners[$c_no] = $b_name;
                }
                $block_map[$b_name][] = $c_no;
            }
        }
    }

    if (!empty($internal_duplicates)) {
        $dup_msg = implode(', ', array_slice($internal_duplicates, 0, 3));
        action_error_toast($prj_id, 'tab-block', '입력 내 중복 코스: ' . $dup_msg);
    }

    if (empty($block_map)) {
        action_error_toast($prj_id, 'tab-block', '올바른 형식의 데이터가 없습니다.');
    }

    $existing_map = get_existing_course_map($prj_id);
    $db_duplicates = [];
    foreach ($block_map as $b_name => $courses) {
        foreach (array_unique($courses) as $c_no) {
            if (isset($existing_map[$c_no]) && $existing_map[$c_no] !== $b_name) {
                $db_duplicates[] = "{$c_no}코스(신규:{$b_name}, 기존:{$existing_map[$c_no]})";
            }
        }
    }

    if (!empty($db_duplicates)) {
        $dup_msg = implode(', ', array_slice($db_duplicates, 0, 3));
        if (count($db_duplicates) > 3) $dup_msg .= " 외 " . (count($db_duplicates) - 3) . "건";
        action_error_toast($prj_id, 'tab-block', '기존 등록 코스와 충돌: ' . $dup_msg);
    }

    $success_cnt = 0;
    foreach ($block_map as $b_name => $courses) {
        $safe_block_name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', '', $b_name);
        $unique_courses = array_values(array_unique($courses));
        $line_count = count($unique_courses);
        $line_range = format_course_range($unique_courses);
        $line_list = implode(',', $unique_courses);

        $target_dir = $base_dir . '\\block\\' . $safe_block_name;
        $enc_target = iconv('UTF-8', 'CP949//IGNORE', $target_dir);
        if (!is_dir($enc_target)) {
            @mkdir($enc_target, 0777, true);
            create_sub_dirs($target_dir);
        }

        $sql = " INSERT INTO IMG_BLOCK 
                 SET prj_id = {$prj_id},
                     block_name = '".sql_real_escape_string($safe_block_name)."',
                     line_range = '".sql_real_escape_string($line_range)."',
                     line_count = {$line_count},
                     line_list = '".sql_real_escape_string($line_list)."',
                     photo_count = 0,
                     mb_id = '".sql_real_escape_string($member['mb_id'])."',
                     mb_name = '".sql_real_escape_string($current_user_name)."',
                     created_at = NOW()
                 ON DUPLICATE KEY UPDATE
                     line_range = '".sql_real_escape_string($line_range)."',
                     line_count = {$line_count},
                     line_list = '".sql_real_escape_string($line_list)."',
                     mb_id = '".sql_real_escape_string($member['mb_id'])."',
                     mb_name = '".sql_real_escape_string($current_user_name)."',
                     created_at = NOW() ";
        sql_query($sql);
        $success_cnt++;
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-block&toast=block_bulk_ok&cnt='.$success_cnt);
}

// 3. 블럭 삭제
if ($action === 'delete_blocks') {
    $block_ids = isset($_POST['block_ids']) ? (array)$_POST['block_ids'] : [];
    if (empty($block_ids)) {
        action_error_toast($prj_id, 'tab-block', '삭제할 블럭을 선택해주세요.');
    }

    $deleted_cnt = 0;
    foreach ($block_ids as $bid) {
        $bid = (int)$bid;
        $row = sql_fetch(" SELECT * FROM IMG_BLOCK WHERE block_id = {$bid} AND prj_id = {$prj_id} ");
        if ($row) {
            $target_dir = $base_dir . '\\block\\' . $row['block_name'];
            $enc_target = iconv('UTF-8', 'CP949//IGNORE', $target_dir);
            rrmdir($enc_target);

            sql_query(" DELETE FROM IMG_BLOCK WHERE block_id = {$bid} ");
            $deleted_cnt++;
        }
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-block&toast=block_delete_ok&cnt='.$deleted_cnt);
}
?>