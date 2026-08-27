<?php
include_once('./_common.php');

if (!$is_member) {
    if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['get_eo_file_list', 'read_eo_file_content'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
        exit;
    }
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

switch ($action) {
    // 1. 촬영일 기본 관리 (CRUD & 상태)
    case 'add_flight_date':
    case 'update_flight_inspect':
    case 'toggle_flight_status':
    case 'delete_flight_dates':
        include_once('./actions/act_flight.php');
        break;

    // 2. EO 성과 파일 관리 및 파싱
    case 'get_eo_file_list':
    case 'read_eo_file_content':
    case 'apply_selected_eo_file':
        include_once('./actions/act_flight_eo.php');
        break;

    // 3. INDEX 도면(DXF/DWG) 생성 및 관리
    case 'generate_index_dwg':
        include_once('./actions/act_flight_index.php');
        break;

    // 4. 블럭 관리
    case 'add_block_single':
    case 'add_block_bulk':
    case 'delete_blocks':
        include_once('./actions/act_block.php');
        break;

    // 5. 보안성검토
    case 'add_security':
    case 'delete_security':
        include_once('./actions/act_security.php');
        break;

    // 6. 품질검수
    case 'add_qa':
    case 'delete_qa':
        include_once('./actions/act_qa.php');
        break;

    // 7. DB 동기화
    case 'sync_db':
        include_once('./actions/act_sync.php');
        break;
    case 'preview_index_data':
    case 'generate_index_dwg':
        include_once('./actions/act_flight_index.php');
        break;
    default:
        action_goto_url(G5_URL.'/project/view.php?id='.$prj_id);
        break;
}
?>