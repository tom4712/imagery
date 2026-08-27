<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 비회원(로그인하지 않은 사용자)인 경우
if (!$is_member) {
    // 예외 처리할 페이지 배열 (로그인, 비밀번호 찾기 등)
    $allowed_pages = array(
        'login.php',
        'login_check.php',
        'password_lost.php',
        'password_lost_certify.php',
        'register.php',
        'register_form.php',
        'register_form_update.php'
    );

    $current_page = basename($_SERVER['SCRIPT_NAME']);

    // 현재 접속한 페이지가 예외 페이지가 아니라면 로그인 페이지로 강제 이동
    if (!in_array($current_page, $allowed_pages)) {
        // alert 메시지 없이 자연스럽게 이동하려면 goto_url 사용
        goto_url(G5_BBS_URL.'/login.php');
    }
}
?>