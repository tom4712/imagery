<?php
// 상위 루트의 그누보드 코어 common.php 직접 로드
include_once('../common.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 안전 리다이렉트 헬퍼
function action_goto_url($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    echo '<script>location.replace(' . json_encode($url) . ');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    exit;
}

// 에러 토스트 리다이렉트 헬퍼
function action_error_toast($prj_id, $tab, $msg) {
    $url = G5_URL . '/project/view.php?id=' . $prj_id . '&tab=' . $tab . '&toast=error&err_msg=' . urlencode($msg);
    action_goto_url($url);
}

// 서브폴더 일괄 생성 (EO, INDEX, 문서)
function create_sub_dirs($target_path) {
    $sub_folders = ['EO', 'INDEX', '문서'];
    foreach ($sub_folders as $sub) {
        $path = $target_path . '\\' . $sub;
        $enc_path = iconv('UTF-8', 'CP949//IGNORE', $path);
        if (!is_dir($enc_path)) {
            @mkdir($enc_path, 0777, true);
        }
    }
}

// 폴더 및 내부 파일 완전 삭제 (Windows 호환)
function rrmdir($dir) {
    if (!$dir) return;
    
    // UTF-8 -> CP949 변환 (Windows 파일 시스템 경로 호환)
    $enc_dir = (mb_detect_encoding($dir, 'UTF-8', true)) ? iconv('UTF-8', 'CP949//IGNORE', $dir) : $dir;
    if (!file_exists($enc_dir)) return;

    if (is_file($enc_dir) || is_link($enc_dir)) {
        @chmod($enc_dir, 0777);
        @unlink($enc_dir);
        return;
    }

    $files = @scandir($enc_dir);
    if (is_array($files)) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $enc_dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                rrmdir($filePath);
            } else {
                @chmod($filePath, 0777);
                @unlink($filePath);
            }
        }
    }
    
    @chmod($enc_dir, 0777);
    @rmdir($enc_dir);

    // PHP 기본 함수로 삭제되지 않을 경우 Windows 셸 명령으로 강제 삭제(rd /s /q)
    if (file_exists($enc_dir) && is_dir($enc_dir)) {
        @exec('rd /s /q "' . $enc_dir . '"');
    }
}

// 코스 배열 포맷 변환 (예: 1~12코스)
function format_course_range($courses) {
    sort($courses, SORT_NUMERIC);
    $courses = array_values(array_unique($courses));
    if (empty($courses)) return '-';
    
    $min = min($courses);
    $max = max($courses);
    if (count($courses) === ($max - $min + 1)) {
        return ($min === $max) ? "{$min}코스" : "{$min} ~ {$max}코스";
    } else {
        return implode(', ', $courses) . '코스';
    }
}
?>