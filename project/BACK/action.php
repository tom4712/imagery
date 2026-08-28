<?php
include_once('./_common.php');

// 1. AJAX 요청 액션 목록 정의 (비로그인 시 JSON 응답 반환 대상)
$ajax_actions = [
    'get_eo_file_list', 
    'read_eo_file_content', 
    'get_duplicate_video_groups',
    'preview_block_index_data',
    'preview_index_data',
    'get_doc_file_list', 
    'create_doc_from_template',
    'delete_index_file'
];

if (!$is_member) {
    if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], $ajax_actions)) {
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

$base_dir = img_project_path($prj['prj_name']);
$current_user_name = $member['mb_name'] ? $member['mb_name'] : $member['mb_nick'];

// 2. 액션별 라우팅 처리
switch ($action) {
    case 'add_flight_date':
    case 'update_flight_inspect':
    case 'apply_flight_inspection':
    case 'apply_duplicate_video_selection':
    case 'toggle_flight_status':
    case 'delete_flight_dates':
        include_once('./actions/act_flight.php');
        break;

    case 'get_eo_file_list':
    case 'read_eo_file_content':
    case 'get_duplicate_video_groups':
    case 'apply_selected_eo_file':
        include_once('./actions/act_flight_eo.php');
        break;

    case 'preview_index_data':
    case 'preview_block_index_data':
    case 'generate_index_dwg':
    case 'generate_block_index_dwg':
    case 'delete_index_file':
        include_once('./actions/act_flight_index.php');
        break;

    case 'get_doc_file_list':
    case 'create_doc_from_template':
        include_once('./actions/act_flight_doc.php');
        break;

    case 'add_block_single':
    case 'add_block_bulk':
    case 'delete_blocks':
        include_once('./actions/act_block.php');
        break;

    case 'add_security':
    case 'delete_security':
        include_once('./actions/act_security.php');
        break;

    case 'add_qa':
    case 'delete_qa':
        include_once('./actions/act_qa.php');
        break;

    case 'sync_db':
        include_once('./actions/act_sync.php');
        break;

    default:
        action_goto_url(G5_URL.'/project/view.php?id='.$prj_id);
        break;
}
?>
