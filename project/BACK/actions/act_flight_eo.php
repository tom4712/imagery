<?php
if (!defined('_GNUBOARD_')) exit;

// 1. 해당 촬영일 EO 폴더 내 파일 목록 조회 (AJAX) - 한글 깨짐 및 1970년 시간 버그 수정
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
    
    // 윈도우 환경 호환을 위한 디렉토리 경로 인코딩 처리
    $enc_eo_dir = iconv('UTF-8', 'CP949//IGNORE', $eo_dir);
    $target_dir = is_dir($enc_eo_dir) ? $enc_eo_dir : (is_dir($eo_dir) ? $eo_dir : '');
    
    $file_list = [];
    if ($target_dir && ($files = @scandir($target_dir))) {
        $saved_files = array_map('trim', explode(',', $row['eo_file_name'] ?? ''));

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            
            // 정규식을 활용하여 파일명의 UTF-8 여부 정확히 감지
            $is_utf8 = preg_match('//u', $f);
            
            // 프론트엔드(화면)에 전달할 때는 무조건 UTF-8로 변환
            $utf_name = $is_utf8 ? $f : iconv('CP949', 'UTF-8//IGNORE', $f);
            
            // 파일 시스템 함수(filemtime 등)를 위한 실제 윈도우 경로 (CP949)
            $cp949_name = $is_utf8 ? iconv('UTF-8', 'CP949//IGNORE', $f) : $f;
            $fs_path = $target_dir . DIRECTORY_SEPARATOR . $cp949_name;
            
            // CP949 경로로 못 찾을 경우 원본 이름으로 Fallback
            if (!file_exists($fs_path)) {
                $fs_path = $target_dir . DIRECTORY_SEPARATOR . $f;
            }
            
            // 디렉토리가 아닌 정상적인 파일만 스캔
            if (!is_dir($fs_path)) {
                $mtime = @filemtime($fs_path);
                $size = @filesize($fs_path);
                
                $file_list[] = [
                    'filename' => $utf_name,
                    'mtime' => $mtime ? date('Y-m-d H:i:s', $mtime) : '-',
                    'size' => $size ? round($size / 1024, 1) . ' KB' : '0 KB',
                    'is_current' => in_array($utf_name, $saved_files)
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

// 2. 선택된 EO 파일 내용 읽기 (AJAX) - 파일 열기 경로 인코딩 픽스
if ($action === 'read_eo_file_content') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $date_id  = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
    $filename = isset($_GET['filename']) ? basename(trim($_GET['filename'])) : '';

    $row = sql_fetch(" SELECT flight_date FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    if (!$row || !$filename) {
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        exit;
    }

    $file_path_utf8 = $base_dir . '\\date\\' . trim($row['flight_date']) . '\\EO\\' . $filename;
    $file_path_cp949 = iconv('UTF-8', 'CP949//IGNORE', $file_path_utf8);
    
    // 파일 시스템에서 인식 가능한 인코딩으로 안전하게 파일 찾기
    $fs_path = file_exists($file_path_cp949) ? $file_path_cp949 : $file_path_utf8;

    if (!file_exists($fs_path)) {
        echo json_encode(['success' => false, 'message' => '서버에 해당 파일이 존재하지 않습니다: '.$filename]);
        exit;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($ext, ['txt', 'csv', 'tsv', 'dat'])) {
        $content = file_get_contents($fs_path);
        // 파일 내부 내용도 깨짐 방지 처리
        $utf_content = (mb_detect_encoding($content, 'UTF-8', true)) ? $content : iconv('CP949', 'UTF-8//IGNORE', $content);
        echo json_encode([
            'success' => true,
            'is_binary' => false,
            'filename' => $filename,
            'content' => $utf_content
        ]);
        exit;
    } else {
        $binary_data = file_get_contents($fs_path);
        echo json_encode([
            'success' => true,
            'is_binary' => true,
            'filename' => $filename,
            'base64' => base64_encode($binary_data)
        ]);
        exit;
    }
}

if ($action === 'get_duplicate_video_groups') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $date_id = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
    if (!$date_id) {
        echo json_encode(['success' => false, 'message' => '촬영일 정보를 찾을 수 없습니다.']);
        exit;
    }

    $flight = sql_fetch(" SELECT flight_date FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    if (!$flight) {
        echo json_encode(['success' => false, 'message' => '촬영일 정보를 찾을 수 없습니다.']);
        exit;
    }

    $groups = img_flight_duplicate_video_groups($prj_id, $prj['prj_name'], $date_id);

    echo json_encode([
        'success' => true,
        'date_id' => $date_id,
        'flight_date' => $flight['flight_date'],
        'total' => count($groups),
        'groups' => $groups
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. 선택된 EO 파일 한 개를 활성화하고 DB에 반영
if ($action === 'apply_selected_eo_file') {
    include_once(__DIR__ . '/act_flight.php');

    $date_id        = isset($_POST['date_id']) ? (int)$_POST['date_id'] : 0;
    $filename       = isset($_POST['filename']) ? basename(trim($_POST['filename'])) : '';
    $parsed_shots   = isset($_POST['parsed_shots']) ? (int)$_POST['parsed_shots'] : 0;
    $matched_blocks = isset($_POST['matched_blocks']) ? trim($_POST['matched_blocks']) : '';

    if (!$date_id || !$filename || strpos($filename, ',') !== false) {
        action_error_toast($prj_id, 'tab-flight', '선택된 파일 정보가 올바르지 않습니다.');
    }

    $flight = sql_fetch(" SELECT flight_date FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");
    $eo_path = $flight ? $base_dir . '\\date\\' . trim($flight['flight_date']) . '\\EO\\' . $filename : '';
    $eo_path_cp949 = $eo_path ? iconv('UTF-8', 'CP949//IGNORE', $eo_path) : '';
    if (!$flight || (!file_exists($eo_path) && !file_exists($eo_path_cp949))) {
        action_error_toast($prj_id, 'tab-flight', '선택한 EO 파일이 서버에 존재하지 않습니다.');
    }

    $active_eo_name = $filename;
    $active_eo_path = file_exists($eo_path) ? $eo_path : $eo_path_cp949;
    $auto_items = function_exists('img_auto_duplicate_reshoot_overbuffer_items')
        ? img_auto_duplicate_reshoot_overbuffer_items($prj_id, $prj['prj_name'], $date_id, $active_eo_path)
        : [];

    if (!empty($auto_items) && preg_match('/\.xlsx$/i', $filename)) {
        $eo_dir = $base_dir . '\\date\\' . trim($flight['flight_date']) . '\\EO';
        $new_eo_name = inspect_completed_eo_name($eo_dir, $filename);
        $new_path_fs = inspect_copy_eo_file($active_eo_path, $eo_dir, $new_eo_name);

        if ($new_path_fs) {
            $records = inspect_eo_records($new_path_fs);
            list($marks, $id_type, $id_reason) = inspect_mark_map($records, $auto_items);
            inspect_color_xlsx_rows($new_path_fs, $records, $marks, $id_reason, false);

            $active_eo_name = $new_eo_name;
            $summary = inspect_eo_count_summary($new_path_fs);
            $parsed_shots = (int)$summary['total'];
            $used_shots = (int)$summary['used'];
            $unused_shots = (int)$summary['duplicate'];
            $reshoot_shots = (int)$summary['reshoot'];
        }
    }

    if (!isset($used_shots)) {
        $used_shots = $parsed_shots;
        $unused_shots = 0;
        $reshoot_shots = 0;
    }

    sql_query(" UPDATE IMG_FLIGHT_DATE 
                SET eo_file_name = '".sql_real_escape_string($active_eo_name)."',
                    total_shots = {$parsed_shots},
                    used_shots = {$used_shots},
                    unused_shots = {$unused_shots},
                    reshoot_shots = {$reshoot_shots},
                    matched_blocks = '".sql_real_escape_string($matched_blocks)."',
                    mb_id = '".sql_real_escape_string($member['mb_id'])."',
                    mb_name = '".sql_real_escape_string($current_user_name)."'
                WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

    $vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
    if ($vol_row) {
        sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$vol_row['total_vol']} WHERE prj_id = {$prj_id} ");
    }

    $toast_val = $active_eo_name;
    if (!empty($auto_items) && $active_eo_name !== $filename) {
        $toast_val .= ' / 자동중복 ' . count($auto_items) . '건';
    }

    action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=eo_applied_ok&val='.urlencode($toast_val));
}
?>
