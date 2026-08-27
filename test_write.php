<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$target_dir = 'E:\#KYS_IMAGERY_SERVER\26년 항공사진촬영 및 항공삼각측량(전라서부)\date\2026-08-26\INDEX';
$target_file = $target_dir . '\test_index.dxf';

echo "<h2>📁 윈도우 파일 생성 진단 테스트</h2>";
echo "<b>PHP 버전:</b> " . phpversion() . "<br>";
echo "<b>테스트 대상 경로:</b> " . htmlspecialchars($target_file) . "<br><hr>";

// 1. 드라이브 존재 여부 확인
$drive = 'E:\\';
if (!is_dir($drive)) {
    echo "<span style='color:red;'>❌ E: 드라이브를 인식할 수 없습니다. (외장 디스크 연결 또는 드라이브 문자 확인 필요)</span><br>";
    exit;
} else {
    echo "<span style='color:green;'>✅ E: 드라이브 정상 인식됨</span><br>";
}

// 2. 디렉토리 재귀 생성 테스트 (UTF-8 직접 생성)
if (!is_dir($target_dir)) {
    $mk = @mkdir($target_dir, 0777, true);
    if (!$mk) {
        $err = error_get_last();
        echo "<span style='color:red;'>❌ 폴더 생성 실패 (UTF-8): " . ($err['message'] ?? '권한 부족') . "</span><br>";
        
        // CP949 대체 시도
        $cp_dir = iconv('UTF-8', 'CP949//IGNORE', $target_dir);
        $mk_cp = @mkdir($cp_dir, 0777, true);
        echo $mk_cp ? "<span style='color:orange;'>⚠️ CP949 변환으로 폴더 생성 성공</span><br>" : "<span style='color:red;'>❌ CP949 변환으로도 폴더 생성 실패</span><br>";
    } else {
        echo "<span style='color:green;'>✅ UTF-8 폴더 생성 성공</span><br>";
    }
} else {
    echo "<span style='color:green;'>✅ 폴더가 이미 존재함</span><br>";
}

// 3. 파일 쓰기 테스트
$sample_dxf = "0\nSECTION\n2\nENTITIES\n0\nENDSEC\n0\nEOF\n";
$bytes = @file_put_contents($target_file, $sample_dxf);

if ($bytes !== false) {
    echo "<span style='color:green;'>🎉 <b>파일 생성 성공!</b> (" . $bytes . " bytes 기록됨)</span><br>";
    echo "실제 파일 위치: " . htmlspecialchars($target_file) . "<br>";
} else {
    $err = error_get_last();
    echo "<span style='color:red;'>❌ file_put_contents 실패: " . ($err['message'] ?? '원인 불명') . "</span><br>";
    
    // CP949로 재시도
    $cp_file = iconv('UTF-8', 'CP949//IGNORE', $target_file);
    $bytes_cp = @file_put_contents($cp_file, $sample_dxf);
    if ($bytes_cp !== false) {
        echo "<span style='color:orange;'>⚠️ CP949 인코딩으로 파일 생성 성공 (" . $bytes_cp . " bytes)</span><br>";
    }
}
?>