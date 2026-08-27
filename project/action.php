<?php
include_once('./_common.php');

if (!$is_member) {
    action_goto_url(G5_BBS_URL.'/login.php');
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$prj_id = isset($_REQUEST['prj_id']) ? (int)$_REQUEST['prj_id'] : 0;

if (!$prj_id) {
    action_goto_url(G5_URL.'/index.php');
}

$prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ");
if (!$prj) {
    action_goto_url(G5_URL.'/index.php');
}

$base_dir = 'E:\#KYS_IMAGERY_SERVER\\' . $prj['prj_name'];
$current_user_name = $member['mb_name'] ? $member['mb_name'] : $member['mb_nick'];

// 기능별 액션 분기 처리
switch ($action) {
    // 1. 블럭 관련
    case 'add_block_single':
    case 'add_block_bulk':
    case 'delete_blocks':
        include_once('./actions/act_block.php');
        break;

    // 2. 촬영일 관련
    case 'add_flight_date':
    case 'toggle_flight_date':
    case 'delete_flight_date':
        include_once('./actions/act_flight.php');
        break;

    // 3. 보안성검토 관련
    case 'add_security':
    case 'delete_security':
        include_once('./actions/act_security.php');
        break;

    // 4. 품질검수 관련
    case 'add_qa':
    case 'delete_qa':
        include_once('./actions/act_qa.php');
        break;

    // 5. ⚡ DB 동기화
    case 'sync_db':
        include_once('./actions/act_sync.php');
        break;

    default:
        action_goto_url(G5_URL.'/project/view.php?id='.$prj_id);
        break;
}
?>