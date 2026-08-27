<?php
include_once('./_common.php');

if (!$is_member) {
    alert('로그인 후 이용 가능합니다.', G5_BBS_URL.'/login.php');
}

$prj_name = isset($_POST['prj_name']) ? trim($_POST['prj_name']) : '';
$prj_type = isset($_POST['prj_type']) ? trim($_POST['prj_type']) : '';

if (!$prj_name || !$prj_type) {
    alert('사업명과 구분을 모두 입력해주세요.');
}

$safe_prj_name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', '', $prj_name);

$base_dir = 'E:\#KYS_IMAGERY_SERVER';
$project_dir = $base_dir . '\\' . $safe_prj_name;
$eo_dir = $project_dir . '\\EO';

$enc_base_dir = iconv('UTF-8', 'CP949//IGNORE', $base_dir);
$enc_project_dir = iconv('UTF-8', 'CP949//IGNORE', $project_dir);
$enc_eo_dir = iconv('UTF-8', 'CP949//IGNORE', $eo_dir);

if (!is_dir($enc_base_dir)) {
    @mkdir($enc_base_dir, 0777, true);
}

if (!is_dir($enc_project_dir)) {
    @mkdir($enc_project_dir, 0777, true);
    @mkdir($enc_eo_dir, 0777, true);
}

// DB 등록 처리 (에러 발생 시 메시지 출력)
$sql = " INSERT INTO IMG_PROJECT
            SET prj_name = '".sql_real_escape_string($safe_prj_name)."',
                prj_type = '".sql_real_escape_string($prj_type)."',
                prj_volume = 0,
                created_at = NOW() ";
$result = sql_query($sql, false);

if ($result) {
    goto_url(G5_URL.'/index.php');
} else {
    // DB 에러 상세 출력
    $db_err = sql_error();
    alert('DB 등록 실패: ' . $db_err);
}
?>