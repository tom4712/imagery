<?php
include_once('./_common.php');

$g5['title'] = '항공사진 관리 - 로그인';

// 이미 로그인된 상태라면 메인 페이지로 강제 이동
if ($is_member) {
    goto_url(G5_URL);
}

// PHP 8 Undefined array key "url" 경고 방지 처리
$url = isset($_GET['url']) ? clean_xss_tags($_GET['url']) : '';
$login_url = $url;
$login_action_url = G5_HTTPS_BBS_URL."/login_check.php";

// 테마 스킨 경로 우선 탐색
$target_skin_path = (defined('G5_THEME_PATH') && is_dir(G5_THEME_PATH.'/skin/member/basic')) 
    ? G5_THEME_PATH.'/skin/member/basic' 
    : G5_SKIN_PATH.'/member/basic';

// 프레임 충돌 없는 깔끔한 풀스크린 렌더링
include_once(G5_PATH.'/head.sub.php');
include_once($target_skin_path.'/login.skin.php');
include_once(G5_PATH.'/tail.sub.php');
?>