<?php
include_once('./_common.php');

if (!$is_member) {
    goto_url(G5_BBS_URL.'/login.php');
}

$prj_id   = isset($_GET['prj_id']) ? (int)$_GET['prj_id'] : 0;
$date_id  = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
$filename = isset($_GET['filename']) ? basename(trim($_GET['filename'])) : '';

if (!$prj_id || !$date_id || !$filename) {
    alert('잘못된 접근입니다.', G5_URL.'/index.php');
}

$prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ");
$flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

$doc_path = 'E:\#KYS_IMAGERY_SERVER\\' . trim($prj['prj_name']) . '\\date\\' . trim($flight['flight_date']) . '\\문서\\' . $filename;
$enc_path = (mb_detect_encoding($doc_path, 'UTF-8', true)) ? iconv('UTF-8', 'CP949//IGNORE', $doc_path) : $doc_path;

if (!file_exists($enc_path)) {
    $enc_path = $doc_path;
    if (!file_exists($enc_path)) {
        alert('문서 파일이 존재하지 않습니다.', G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight');
    }
}

$file_base64 = base64_encode(file_get_contents($enc_path));

// EO 파일 로드
$eo_dir = 'E:\#KYS_IMAGERY_SERVER\\' . trim($prj['prj_name']) . '\\date\\' . trim($flight['flight_date']) . '\\EO';
$eo_payload = ['exists' => false, 'is_binary' => false, 'data' => ''];

if (is_dir($eo_dir)) {
    $scan = @scandir($eo_dir);
    if ($scan) {
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..') continue;
            $eo_file_path = $eo_dir . '\\' . $f;
            if (is_file($eo_file_path)) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                $raw = @file_get_contents($eo_file_path);
                
                if (in_array($ext, ['xlsx', 'xls'])) {
                    $eo_payload = ['exists' => true, 'is_binary' => true, 'filename' => $f, 'data' => base64_encode($raw)];
                } else {
                    $utf_text = mb_detect_encoding($raw, 'UTF-8', true) ? $raw : iconv('CP949', 'UTF-8//IGNORE', $raw);
                    $eo_payload = ['exists' => true, 'is_binary' => false, 'filename' => $f, 'data' => $utf_text];
                }
                break;
            }
        }
    }
}

$g5['title'] = htmlspecialchars($filename) . ' - 촬영기록부 편집기';
?>
<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css" rel="stylesheet" type="text/css" />

    <!-- 💡 서식/스타일 완벽 보존용 ExcelJS & 파싱용 SheetJS -->
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <style>
        *, *::before, *::after { transition: none !important; animation: none !important; }
        html, body {
            width: 100vw; height: 100vh; margin: 0; padding: 0; overflow: hidden;
            font-family: "Pretendard", system-ui, sans-serif; background-color: #060a12;
        }
        .form-card {
            background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1rem; padding: 1.2rem;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #060a12; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
    </style>
</head>
<body class="w-full h-full flex flex-col bg-slate-950 select-none">

    <!-- 상단 툴바 -->
    <div class="h-14 bg-slate-900 border-b border-white/10 px-6 flex items-center justify-between z-30 flex-shrink-0">
        <div class="flex items-center gap-3">
            <a href="view.php?id=<?php echo $prj_id; ?>&tab=tab-flight" class="btn btn-sm btn-ghost rounded-xl text-xs gap-1.5 font-bold hover:bg-white/10 text-white/80">
                <i class="fa-solid fa-arrow-left"></i><span>돌아가기</span>
            </a>
            <div class="h-4 w-[1px] bg-white/10"></div>
            <div class="flex items-center gap-2">
                <span class="text-xl">📋</span>
                <h1 class="font-bold text-sm text-white font-mono"><?php echo htmlspecialchars($filename); ?></h1>
                <span class="badge badge-accent badge-sm font-mono font-bold"><?php echo htmlspecialchars($flight['flight_date']); ?></span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-sm btn-secondary rounded-xl font-bold text-xs gap-1 shadow-md shadow-secondary/20" onclick="autoPopulateFromEO()">
                <i class="fa-solid fa-wand-magic-sparkles"></i><span>EO 분석 및 코스 배치</span>
            </button>
            <button type="button" id="btn_save_server" class="btn btn-sm btn-primary rounded-xl font-bold text-xs gap-1.5 shadow-md shadow-primary/20 px-5" onclick="exportAndSaveExcel()">
                <i class="fa-regular fa-floppy-disk"></i><span>저장</span>
            </button>
        </div>
    </div>

    <!-- 메인 입력 폼 컨테이너 -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 flex justify-center items-start">
        <div class="w-full max-w-5xl space-y-4">

            <!-- 1. 헤더: 항공기 및 탑승자 정보 (전 페이지 공통) -->
            <div class="form-card space-y-3">
                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                    <div class="flex items-center gap-2">
                        <span>✈️</span><h3 class="font-bold text-xs text-white">기본 사업 및 운항 인원 (전 페이지 공통)</h3>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-2.5">
                    <div class="form-control col-span-2">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400 font-bold">항공기 등록부호 (B2)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-bold text-cyan-300 bg-slate-900 header-input" data-cell="B2">
                    </div>
                    <div class="form-control col-span-2">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400 font-bold">사업명 (B4)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-medium text-cyan-300 bg-slate-900 header-input" data-cell="B4" value="<?php echo htmlspecialchars($prj['prj_name']); ?>">
                    </div>
                    <div class="form-control col-span-2">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400 font-bold">촬영일자 (B5)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-mono font-medium text-cyan-300 bg-slate-900 header-input" data-cell="B5" value="<?php echo htmlspecialchars($flight['flight_date']); ?>">
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-2.5 pt-1 border-t border-white/5">
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400">조종사 (I4)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-medium text-cyan-300 bg-slate-900 header-input" data-cell="I4">
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400">항로지시사 (O4)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-medium text-cyan-300 bg-slate-900 header-input" data-cell="O4">
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400">촬영사 (V4)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-medium text-cyan-300 bg-slate-900 header-input" data-cell="V4">
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400">정비사 (AA4)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-medium text-cyan-300 bg-slate-900 header-input" data-cell="AA4">
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400">사용기지 (I5)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-medium text-cyan-300 bg-slate-900 header-input" data-cell="I5">
                    </div>
                </div>
            </div>

            <!-- 2. 카메라 제원 및 비행 시간/속도 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="form-card md:col-span-2 space-y-2.5">
                    <div class="flex items-center justify-between border-b border-white/5 pb-2">
                        <div class="flex items-center gap-2">
                            <span>📷</span><h3 class="font-bold text-xs text-white">카메라 제원</h3>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] text-amber-400 font-bold">카메라(O5):</span>
                            <select class="select select-bordered select-xs rounded-lg text-xs font-bold bg-slate-900 border-amber-400/40 text-amber-300 header-input" id="select_camera_preset" data-cell="O5" onchange="applyCameraPreset(this.value)">
                                <option value="">-- 프리셋 선택 --</option>
                                <option value="Leica CountryMapper">Leica CountryMapper</option>
                                <option value="DMC III">DMC III</option>
                                <option value="UltraCam Osprey 4.1">UltraCam Osprey 4.1</option>
                                <option value="UltraCam Eagle M3">UltraCam Eagle M3</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-[10px] font-mono">
                        <div class="p-2 rounded-lg bg-slate-900 border border-white/5">
                            <span class="text-slate-500 block">초점거리(Q7 mm)</span>
                            <input type="text" class="bg-transparent text-cyan-300 font-bold w-full outline-none header-input" data-cell="Q7" id="cam_q7" readonly>
                        </div>
                        <div class="p-2 rounded-lg bg-slate-900 border border-white/5">
                            <span class="text-slate-500 block">카메라번호(Q8)</span>
                            <input type="text" class="bg-transparent text-cyan-300 font-bold w-full outline-none header-input" data-cell="Q8" id="cam_q8" readonly>
                        </div>
                        <div class="p-2 rounded-lg bg-slate-900 border border-white/5">
                            <span class="text-slate-500 block">Pixels(Q9)</span>
                            <input type="text" class="bg-transparent text-cyan-300 font-bold w-full outline-none header-input" data-cell="Q9" id="cam_q9" readonly>
                        </div>
                        <div class="p-2 rounded-lg bg-slate-900 border border-white/5">
                            <span class="text-slate-500 block">Pixel Size(Q10 μm)</span>
                            <input type="text" class="bg-transparent text-cyan-300 font-bold w-full outline-none header-input" data-cell="Q10" id="cam_q10" readonly>
                        </div>
                    </div>
                </div>
                <div class="form-card space-y-2">
                    <div class="flex items-center gap-2 border-b border-white/5 pb-2">
                        <span>⏱️</span><h3 class="font-bold text-xs text-white">비행 시간/속도</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="form-control">
                            <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">이륙시간(W5)</span></label>
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="W5">
                        </div>
                        <div class="form-control">
                            <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">착륙시간(W6)</span></label>
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="W6">
                        </div>
                        <div class="form-control">
                            <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">비행(W7시/AA7분)</span></label>
                            <div class="flex gap-1">
                                <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="W7">
                                <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="AA7">
                            </div>
                        </div>
                        <div class="form-control">
                            <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">속도(W8)</span></label>
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="W8">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. 고도, 기상, 표고, 온도, 기압 -->
            <div class="form-card space-y-3">
                <div class="flex items-center gap-2 border-b border-white/5 pb-2">
                    <span>🌤️</span><h3 class="font-bold text-xs text-white">고도 및 기상 환경</h3>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-2">
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">고도 (B7 ft)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="B7"></div>
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">고도 (B8 m)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="B8"></div>
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">해상도 (B9)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="B9"></div>
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">기지표고 (I7)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="I7"></div>
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">기준면표고 (I8)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="I8"></div>
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">계기고도 (I9)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="I9"></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-2 pt-2 border-t border-white/5">
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">일기(C11) / 기류(G11)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-medium text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="C11" value="양호">
                            <input type="text" class="input input-bordered input-xs font-medium text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="G11" value="양호">
                        </div>
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">풍향(K11) / 풍속(K12)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="K11">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="K12">
                        </div>
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">기온(Q11:1천/Q12:4천)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="Q11">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="Q12">
                        </div>
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">지상온도(W9:륙/W11:착)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="W9">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="W11">
                        </div>
                    </div>
                    <div class="form-control col-span-2">
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">기압(AB9:이륙 / AB11:착륙)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="AB9">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="AB11">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. 무한 코스 리스트 -->
            <div class="form-card space-y-2">
                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                    <div class="flex items-center gap-2">
                        <span>📝</span><h3 class="font-bold text-xs text-white">코스별 촬영 세부 내역 (1페이지당 19개 자동 배치)</h3>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400">저장 시 ExcelJS를 통해 원본 템플릿 서식 100% 보존 복제</span>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="table table-xs w-full text-center border-separate border-spacing-y-1">
                        <thead class="text-slate-400 text-[11px]">
                            <tr>
                                <th class="w-12 bg-slate-900/90 rounded-l-lg">페이지</th>
                                <th class="w-16 bg-slate-900/90">엑셀 행</th>
                                <th class="w-20 bg-slate-900/90">코스(A)</th>
                                <th class="w-24 bg-slate-900/90 text-cyan-400">개시시간(D)</th>
                                <th class="w-24 bg-slate-900/90 text-cyan-400">종료시간(G)</th>
                                <th class="w-24 bg-slate-900/90 text-slate-500">I, K, M</th>
                                <th class="text-left px-3 bg-slate-900/90">Strip Image 범위(O)</th>
                                <th class="w-16 bg-slate-900/90">매수(V)</th>
                                <th class="w-20 bg-slate-900/90">비고(X)</th>
                                <th class="w-24 bg-slate-900/90 rounded-r-lg">방향(Y)</th>
                            </tr>
                        </thead>
                        <tbody id="course_tbody" class="text-xs font-mono">
                            <!-- 동적 생성 -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        const prjId     = <?php echo $prj_id; ?>;
        const dateId    = <?php echo $date_id; ?>;
        const filename  = "<?php echo htmlspecialchars($filename, ENT_QUOTES); ?>";
        const rawBase64 = "<?php echo $file_base64; ?>";
        const eoPayload = <?php echo json_encode($eo_payload); ?>;

        const CAMERA_PRESETS = {
            'Leica CountryMapper': { q7: '122.00', q8: '10214', q9: '20160 x 14112', q10: '3.76' },
            'DMC III':            { q7: '92.00',  q8: '30122', q9: '25728 x 14592', q10: '3.90' },
            'UltraCam Osprey 4.1': { q7: '120.00', q8: '40198', q9: '20544 x 14016', q10: '3.76' },
            'UltraCam Eagle M3':   { q7: '100.00', q8: '50102', q9: '26460 x 17004', q10: '4.00' }
        };

        function applyCameraPreset(camName) {
            const preset = CAMERA_PRESETS[camName];
            if (preset) {
                document.getElementById('cam_q7').value = preset.q7;
                document.getElementById('cam_q8').value = preset.q8;
                document.getElementById('cam_q9').value = preset.q9;
                document.getElementById('cam_q10').value = preset.q10;
            } else {
                ['cam_q7', 'cam_q8', 'cam_q9', 'cam_q10'].forEach(id => document.getElementById(id).value = '');
            }
        }

        function buildCourseRows(count = 19) {
            const tbody = document.getElementById('course_tbody');
            tbody.innerHTML = '';

            const totalCount = Math.max(19, count);

            for (let i = 0; i < totalCount; i++) {
                const pageIdx = Math.floor(i / 19);
                const rowInPage = i % 19;
                const excelRow = (pageIdx * 34) + 14 + rowInPage; 

                if (i > 0 && rowInPage === 0) {
                    const divider = document.createElement('tr');
                    divider.innerHTML = `<td colspan="10" class="bg-indigo-900/40 text-indigo-300 font-bold py-2 border-y border-indigo-500/30">▼ PAGE ${pageIdx + 1} (자동 확장됨) ▼</td>`;
                    tbody.appendChild(divider);
                }

                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-900/50';
                tr.innerHTML = `
                    <td class="text-indigo-400 font-bold bg-slate-900/40 border-r border-white/5">P${pageIdx + 1}</td>
                    <td class="text-slate-600 font-bold">${excelRow}행</td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900 border-white/5 font-bold text-white course-input" data-cell="A${excelRow}" id="row_course_${i}"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900/80 border-cyan-500/30 text-cyan-300 font-mono course-input" data-cell="D${excelRow}"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900/80 border-cyan-500/30 text-cyan-300 font-mono course-input" data-cell="G${excelRow}"></td>
                    <td><span class="badge badge-ghost badge-xs text-slate-500 font-mono">auto</span></td>
                    <td><input type="text" class="input input-xs w-full text-left px-2 bg-slate-900 border-white/5 text-amber-300 font-mono font-medium course-input" data-cell="O${excelRow}" id="row_range_${i}"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900 border-white/5 text-slate-300 font-bold course-input" data-cell="V${excelRow}" id="row_count_${i}"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900 border-white/5 text-slate-400 course-input" data-cell="X${excelRow}"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900 border-white/5 text-lg text-primary font-black course-input" data-cell="Y${excelRow}" id="row_arrow_${i}" readonly></td>
                `;
                tbody.appendChild(tr);
            }
        }

        function formatPhotoRangeString(shotList) {
            if (!shotList || shotList.length === 0) return '';
            const normals = [], reshoots = [];

            shotList.forEach(item => {
                const rawShot = String(item.id).trim().toUpperCase();
                const num = parseInt(rawShot.replace(/[^0-9]/g, ''), 10);
                if (!isNaN(num)) {
                    if (rawShot.includes('A')) {
                        if (!reshoots.includes(num)) reshoots.push(num);
                    } else {
                        if (!normals.includes(num)) normals.push(num);
                    }
                }
            });

            normals.sort((a, b) => a - b);
            reshoots.sort((a, b) => a - b);

            const getRanges = (arr, suffix = '') => {
                if (arr.length === 0) return [];
                const ranges = [];
                let start = arr[0], prev = arr[0];
                for (let i = 1; i < arr.length; i++) {
                    if (arr[i] === prev + 1) prev = arr[i];
                    else {
                        ranges.push(start === prev ? `${start}${suffix}` : `${start}${suffix}-${prev}${suffix}`);
                        start = prev = arr[i];
                    }
                }
                ranges.push(start === prev ? `${start}${suffix}` : `${start}${suffix}-${prev}${suffix}`);
                return ranges;
            };

            return [...getRanges(normals, ''), ...getRanges(reshoots, 'A')].join(', ');
        }

        function autoPopulateFromEO() {
            if (!eoPayload || !eoPayload.exists || !eoPayload.data) {
                alert('EO 파일이 없습니다.'); return;
            }

            let rawRows = [];
            if (eoPayload.is_binary) {
                const bytes = base64ToUint8Array(eoPayload.data);
                const eoWb = XLSX.read(bytes, { type: 'array' });
                rawRows = XLSX.utils.sheet_to_json(eoWb.Sheets[eoWb.SheetNames[0]], { header: 1 });
            } else {
                rawRows = eoPayload.data.split(/\r\n|\n|\r/).map(l => l.trim().split(/\t|,|\s+/).filter(v => v !== ''));
            }

            let gpsTimeColIdx = -1;
            if (rawRows.length > 0) {
                const headerRow = rawRows[0].map(c => String(c).trim().toLowerCase());
                gpsTimeColIdx = headerRow.findIndex(h => h.includes('time') || h.includes('gps') || h.includes('sec'));
            }

            const validRecords = [];
            rawRows.forEach((row, rowIdx) => {
                if (!row || row.length < 8) return;
                const rawId = String(row[0]).trim();
                if (!rawId || rawId.includes('ID') || rawId.includes('코스') || rawId.toLowerCase().includes('photo')) return;

                let courseNo = 0, shotNo = '';
                const matchUnder = rawId.match(/^0*(\d+)[-_]0*(\d+)(_?([A-Za-z]))?$/);
                if (matchUnder) {
                    courseNo = parseInt(matchUnder[1], 10);
                    shotNo = !!matchUnder[4] ? `${parseInt(matchUnder[2], 10)}${matchUnder[4].toUpperCase()}` : `${parseInt(matchUnder[2], 10)}`;
                } else {
                    const matchConcat = rawId.match(/^(\d{4})(\d{4,5})(_?([A-Za-z]))?$/);
                    if (matchConcat) {
                        courseNo = parseInt(matchConcat[1], 10);
                        shotNo = !!matchConcat[4] ? `${parseInt(matchConcat[2], 10)}${matchConcat[4].toUpperCase()}` : `${parseInt(matchConcat[2], 10)}`;
                    } else return;
                }

                if (isNaN(courseNo) || courseNo <= 0) return;

                let gpsTime = 0.0;
                if (gpsTimeColIdx !== -1 && row[gpsTimeColIdx] !== undefined) gpsTime = parseFloat(row[gpsTimeColIdx]) || 0.0;
                else {
                    for (let i = 1; i < row.length; i++) {
                        const val = parseFloat(row[i]);
                        if (!isNaN(val) && val > 10000 && val < 604800) { gpsTime = val; break; }
                    }
                    if (gpsTime === 0.0) gpsTime = rowIdx;
                }

                validRecords.push({ courseNo, shotNo, gpsTime, kappa: parseFloat(row[7]) || 0.0 });
            });

            if (validRecords.length === 0) { alert('유효한 데이터가 없습니다.'); return; }

            validRecords.sort((a, b) => a.gpsTime - b.gpsTime);

            const courseMap = new Map();
            validRecords.forEach(rec => {
                if (!courseMap.has(rec.courseNo)) courseMap.set(rec.courseNo, []);
                courseMap.get(rec.courseNo).push({ id: rec.shotNo, kappa: rec.kappa });
            });

            const sortedCourses = Array.from(courseMap.keys()).sort((a, b) => a - b);
            
            buildCourseRows(sortedCourses.length);

            sortedCourses.forEach((courseNo, index) => {
                const shots = courseMap.get(courseNo);
                const count = shots.length;
                const avgKappa = shots.reduce((acc, cur) => acc + cur.kappa, 0) / (count || 1);
                const arrow = (Math.abs(avgKappa) >= 90) ? '←' : '→';

                document.getElementById(`row_course_${index}`).value = courseNo;
                document.getElementById(`row_range_${index}`).value = formatPhotoRangeString(shots);
                document.getElementById(`row_count_${index}`).value = count;
                document.getElementById(`row_arrow_${index}`).value = arrow;
            });

            alert(`총 ${sortedCourses.length}개 코스 분석 및 테이블 매핑 완료`);
        }

        // 💡 ExcelJS 기반: 원본 서식 100% 보존 저장 함수
        async function exportAndSaveExcel() {
            const btn = document.getElementById('btn_save_server');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span><span>서식 보존 저장 중...</span>';

            try {
                const bytes = base64ToUint8Array(rawBase64);
                const workbook = new ExcelJS.Workbook();
                await workbook.xlsx.load(bytes.buffer);

                const worksheet = workbook.worksheets[0];

                // 1. 공통 헤더 주입
                document.querySelectorAll('.header-input').forEach(input => {
                    const cellAddr = input.dataset.cell;
                    const v = input.value.trim();
                    const cell = worksheet.getCell(cellAddr);
                    if (v !== '') cell.value = (!isNaN(v) && v !== '') ? Number(v) : v;
                    else cell.value = null;
                });

                // 2. 1페이지 코스 영역(14~32행) 초기화
                for (let r = 14; r <= 32; r++) {
                    ['A','D','G','I','K','M','O','V','X','Y'].forEach(c => {
                        worksheet.getCell(`${c}${r}`).value = null;
                    });
                }

                // 3. 페이지네이션 복제 (필요 시 A1:AC32 서식/테두리/병합/높이 100% 딥카피)
                const courseInputs = document.querySelectorAll('input[id^="row_course_"]');
                const totalCourses = Math.max(19, courseInputs.length);
                const totalPages = Math.ceil(totalCourses / 19);

                // 원본 병합 모델 스냅샷
                const originalMerges = Object.values(worksheet._merges || {}).map(m => ({
                    top: m.top, left: m.left, bottom: m.bottom, right: m.right
                }));

                for (let p = 1; p < totalPages; p++) {
                    const rowOffset = p * 34;

                    // 3-1. 행 높이 및 셀 스타일/서식 딥카피
                    for (let r = 1; r <= 32; r++) {
                        const srcRow = worksheet.getRow(r);
                        const dstRow = worksheet.getRow(r + rowOffset);
                        if (srcRow.height) dstRow.height = srcRow.height;

                        for (let c = 1; c <= 29; c++) {
                            const srcCell = srcRow.getCell(c);
                            const dstCell = dstRow.getCell(c);

                            // 스타일/서식 완전 보존 복제
                            dstCell.style = JSON.parse(JSON.stringify(srcCell.style));
                            dstCell.value = srcCell.value;
                        }
                    }

                    // 3-2. 병합 정보 1:1 복제
                    originalMerges.forEach(m => {
                        if (m.top >= 1 && m.bottom <= 32) {
                            try {
                                worksheet.mergeCells(
                                    m.top + rowOffset,
                                    m.left,
                                    m.bottom + rowOffset,
                                    m.right
                                );
                            } catch(e){}
                        }
                    });

                    // 3-3. NO. 번호 갱신 (AC2 셀: col 29, row 2)
                    worksheet.getRow(2 + rowOffset).getCell(29).value = p + 1;
                }

                // 4. 코스별 데이터 주입
                document.querySelectorAll('.course-input').forEach(input => {
                    const cellAddr = input.dataset.cell;
                    const v = input.value.trim();
                    if (v !== '') {
                        const cell = worksheet.getCell(cellAddr);
                        cell.value = (!isNaN(v) && v !== '') ? Number(v) : v;
                    }
                });

                // 5. auto 고정값 주입
                for (let p = 0; p < totalPages; p++) {
                    const offset = p * 34;
                    for (let r = 14; r <= 32; r++) {
                        const absRow = r + offset;
                        ['I', 'K', 'M'].forEach(c => {
                            worksheet.getCell(`${c}${absRow}`).value = 'auto';
                        });
                    }
                }

                // 6. ExcelJS 바이너리 생성 및 Base64 인코딩
                const buffer = await workbook.xlsx.writeBuffer();
                let binaryStr = '';
                const bytesOut = new Uint8Array(buffer);
                const len = bytesOut.byteLength;
                for (let i = 0; i < len; i++) binaryStr += String.fromCharCode(bytesOut[i]);
                const wbBase64 = btoa(binaryStr);

                // 7. 서버 저장 API 전송
                const res = await fetch('./ajax_save_doc.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        prj_id: prjId,
                        date_id: dateId,
                        filename: filename,
                        file_base64: wbBase64
                    })
                });

                const result = await res.json();
                if (result.status === 'success') {
                    alert('원본 서식이 100% 보존된 상태로 문서 폴더에 저장되었습니다.');
                } else {
                    alert('저장 실패: ' + result.message);
                }
            } catch (err) {
                console.error(err);
                alert('저장 중 오류가 발생했습니다: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i><span>저장</span>';
            }
        }

        function base64ToUint8Array(base64) {
            const binStr = atob(base64);
            const bytes = new Uint8Array(binStr.length);
            for (let i = 0; i < binStr.length; i++) bytes[i] = binStr.charCodeAt(i);
            return bytes;
        }

        window.addEventListener('load', async () => {
            buildCourseRows(19);

            try {
                const bytes = base64ToUint8Array(rawBase64);
                const workbook = new ExcelJS.Workbook();
                await workbook.xlsx.load(bytes.buffer);
                const ws = workbook.worksheets[0];

                document.querySelectorAll('.header-input').forEach(input => {
                    const cellAddr = input.dataset.cell;
                    const cell = ws.getCell(cellAddr);
                    if (cell.value !== null && cell.value !== undefined) {
                        input.value = String(cell.value).trim();
                    }
                });

                const o5Val = document.getElementById('select_camera_preset').value;
                if (o5Val) applyCameraPreset(o5Val);
            } catch(e) {
                console.warn('초기 로드 파싱:', e);
            }
        });
    </script>
</body>
</html>