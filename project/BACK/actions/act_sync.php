<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * EO 파일 내부의 유효 사진 데이터 행 수를 계산하는 함수
 */
function parse_eo_photo_count($file_path) {
    if (!file_exists($file_path) || is_dir($file_path)) return 0;

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $photo_count = 0;

    // 1. 텍스트/CSV 계열 (.txt, .csv, .tsv, .dat 등)
    if (in_array($ext, ['txt', 'csv', 'tsv', 'dat'])) {
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;
                
                // 공백 또는 탭/쉼표로 분리
                $parts = preg_split('/[\t,\s]+/', $line);
                if (count($parts) >= 8) { // ID, X, Y, Z, O, P, K 등 최소 8개 이상 열
                    $id = $parts[0];
                    // 헤더 행 제외 (ID, Photo, Name 등의 글자가 포함된 경우 제외)
                    if (preg_match('/[a-zA-Z]/', $id) && (stripos($id, 'id') !== false || stripos($id, 'photo') !== false || stripos($id, 'name') !== false)) {
                        continue;
                    }
                    $photo_count++;
                }
            }
        }
    } 
    // 2. 엑셀 계열 (.xlsx, .xls) - XML 파싱 기반 경량 카운트
    else if ($ext === 'xlsx') {
        $zip = new ZipArchive;
        if ($zip->open($file_path) === TRUE) {
            $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            if ($sheet_xml) {
                // <row> 태그 개수 산출 (헤더 1행 제외)
                $row_matches = preg_match_all('/<row\b[^>]*>/i', $sheet_xml, $matches);
                if ($row_matches > 1) {
                    $photo_count = $row_matches - 1; // 헤더 제외
                } else {
                    $photo_count = $row_matches;
                }
            }
        }
    }

    return $photo_count;
}

// -------------------------------------------------------------------------
// 동기화 실행 메인 루틴
// -------------------------------------------------------------------------
$date_root = $base_dir . '\\date';
$enc_date_root = iconv('UTF-8', 'CP949//IGNORE', $date_root);
$total_prj_volume = 0;

if (is_dir($enc_date_root)) {
    $dirs = scandir($enc_date_root);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        
        $folder_name = iconv('CP949', 'UTF-8//IGNORE', $dir);
        $eo_path = $date_root . '\\' . $folder_name . '\\EO';
        $enc_eo_path = iconv('UTF-8', 'CP949//IGNORE', $eo_path);

        $photo_count = 0;
        $eo_file_name = '';

        if (is_dir($enc_eo_path)) {
            $files = scandir($enc_eo_path);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                
                $full_file_path = $enc_eo_path . DIRECTORY_SEPARATOR . $f;
                $decoded_file_name = iconv('CP949', 'UTF-8//IGNORE', $f);
                
                // EO 폴더 내의 성과 파일 데이터 라인 분석
                $count = parse_eo_photo_count($full_file_path);
                if ($count > 0) {
                    $photo_count += $count;
                    $eo_file_name = $decoded_file_name;
                }
            }
        }

        // DB에 촬영일자 정보 업데이트 또는 자동 생성
        $exist = sql_fetch(" SELECT date_id, used_shots, reshoot_shots, status FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND flight_date = '".sql_real_escape_string($folder_name)."' ");
        
        if ($exist) {
            // 기존 등록 건: total_shots 갱신 및 사용매수 보정 (재촬영 매수가 없으면 total과 동일하게)
            $used = ($exist['reshoot_shots'] > 0) ? ($photo_count - (int)$exist['reshoot_shots']) : $photo_count;
            if ($used < 0) $used = 0;

            $update_sql = " UPDATE IMG_FLIGHT_DATE 
                            SET total_shots = {$photo_count},
                                used_shots = {$used} ";
            if ($eo_file_name) {
                $update_sql .= ", eo_file_name = '".sql_real_escape_string($eo_file_name)."' ";
            }
            $update_sql .= " WHERE date_id = {$exist['date_id']} ";
            sql_query($update_sql);

            if ($exist['status'] === 'ACTIVE') {
                $total_prj_volume += $photo_count;
            }
        } else {
            // 폴더만 있고 DB에 없었던 건 신규 등록 (날짜 포맷 검증)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $folder_name)) {
                sql_query(" INSERT INTO IMG_FLIGHT_DATE 
                            SET prj_id = {$prj_id},
                                flight_name = '자동스캔 (".sql_real_escape_string($folder_name).")',
                                flight_date = '".sql_real_escape_string($folder_name)."',
                                sensor_name = '-',
                                eo_file_name = '".sql_real_escape_string($eo_file_name)."',
                                total_shots = {$photo_count},
                                used_shots = {$photo_count},
                                reshoot_shots = 0,
                                matched_blocks = '',
                                status = 'ACTIVE',
                                created_at = NOW() ");
                
                $total_prj_volume += $photo_count;
            }
        }
    }
}

// 상단 프로젝트 전체 활성 매수 캐시 동기화
$active_vol_row = sql_fetch(" SELECT IFNULL(SUM(total_shots), 0) AS total_vol FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} AND status = 'ACTIVE' ");
$final_volume = $active_vol_row ? (int)$active_vol_row['total_vol'] : $total_prj_volume;

sql_query(" UPDATE IMG_PROJECT SET prj_volume = {$final_volume} WHERE prj_id = {$prj_id} ");

action_goto_url(G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight&toast=sync_ok');
?>