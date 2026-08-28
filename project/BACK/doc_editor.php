<?php
include_once('./_common.php');

// DB 접속 정보 (XAMPP 환경 기준 그누보드 dbconfig.php 설정과 동일하게 구동됨)
// G5_MYSQL_HOST = 'localhost'
// G5_MYSQL_USER = 'IMAGERY'
// G5_MYSQL_PASSWORD = 'geolabs778800'
// G5_MYSQL_DB = 'kysimagery'
// G5_TABLE_PREFIX = 'IMG_'

if (!$is_member) {
    goto_url(G5_BBS_URL.'/login.php');
}

$prj_id   = isset($_GET['prj_id']) ? (int)$_GET['prj_id'] : 0;
$date_id  = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
$filename = isset($_GET['filename']) ? img_doc_filename($_GET['filename']) : '';

if (!$prj_id || !$date_id || !$filename) {
    alert('잘못된 접근입니다.', G5_URL.'/index.php');
}

$prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ");
$flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ");

if (!$prj || !$flight) {
    alert('프로젝트 또는 촬영일 정보를 찾을 수 없습니다.', G5_URL.'/index.php');
}

$prj_name = trim($prj['prj_name'] ?? $prj['name'] ?? '');
$flight_date = trim($flight['flight_date'] ?? $flight['date'] ?? '');

$doc_path_utf8  = img_doc_dir($prj_name, $flight_date) . '\\' . $filename;
$doc_path_cp949 = img_fs_path($doc_path_utf8);

$enc_path = file_exists($doc_path_cp949) ? $doc_path_cp949 : $doc_path_utf8;

if (!file_exists($enc_path)) {
    alert('문서 파일이 존재하지 않습니다: ' . $filename, G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-flight');
}

$file_base64 = base64_encode(file_get_contents($enc_path));

// EO 파일 사전 로드
$eo_dir = img_project_path($prj_name) . '\\date\\' . $flight_date . '\\EO';
$eo_enc_dir = img_fs_path($eo_dir);
$eo_target = is_dir($eo_enc_dir) ? $eo_enc_dir : (is_dir($eo_dir) ? $eo_dir : '');

$eo_payload = ['exists' => false, 'is_binary' => false, 'data' => ''];
$active_eo_name = trim(explode(',', (string)($flight['eo_file_name'] ?? ''))[0]);
$active_eo_name = $active_eo_name !== '' ? basename($active_eo_name) : '';

if ($eo_target && ($scan = @scandir($eo_target))) {
    $target_files = [];
    if ($active_eo_name !== '') {
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..') continue;
            $utf_name = preg_match('//u', $f) ? $f : iconv('CP949', 'UTF-8//IGNORE', $f);
            if ($utf_name === $active_eo_name || basename($utf_name) === $active_eo_name) {
                $target_files[] = $f;
                break;
            }
        }
    }
    foreach ($scan as $f) {
        if ($f === '.' || $f === '..' || in_array($f, $target_files, true)) continue;
        $target_files[] = $f;
    }

    foreach ($target_files as $f) {
        $eo_file_path = $eo_target . '\\' . $f;
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

    <!-- ExcelJS & SheetJS -->
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <style>
        *, *::before, *::after { transition: none !important; animation: none !important; }
        html, body { width: 100vw; height: 100vh; margin: 0; padding: 0; overflow: hidden; font-family: "Pretendard", sans-serif; background-color: #060a12; }
        .form-card { background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 1rem; padding: 1.2rem; }
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
                <span class="badge badge-accent badge-sm font-mono font-bold"><?php echo htmlspecialchars($flight_date); ?></span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-sm btn-secondary rounded-xl font-bold text-xs gap-1 shadow-md shadow-secondary/20" onclick="autoPopulateFromEO()">
                <i class="fa-solid fa-wand-magic-sparkles"></i><span>EO 자동 분석 배치</span>
            </button>
            <button type="button" id="btn_save_server" class="btn btn-sm btn-primary rounded-xl font-bold text-xs gap-1.5 shadow-md shadow-primary/20 px-5" onclick="exportAndSaveExcel()">
                <i class="fa-regular fa-floppy-disk"></i><span>저장</span>
            </button>
        </div>
    </div>

    <!-- 메인 폼 컨테이너 -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 flex justify-center items-start">
        <div class="w-full max-w-5xl space-y-4">

            <!-- 1. 기본 사업 및 운항 인원 -->
            <div class="form-card space-y-3">
                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                    <div class="flex items-center gap-2">
                        <span>✈️</span><h3 class="font-bold text-xs text-white">기본 사업 및 운항 인원</h3>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-2.5">
                    <div class="form-control col-span-2">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400 font-bold">항공기 등록부호 (B2)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-bold text-cyan-300 bg-slate-900 header-input" data-cell="B2">
                    </div>
                    <div class="form-control col-span-2">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400 font-bold">사업명 (B4)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-medium text-cyan-300 bg-slate-900 header-input" data-cell="B4" value="<?php echo htmlspecialchars($prj_name); ?>">
                    </div>
                    <div class="form-control col-span-2">
                        <label class="label py-0.5"><span class="label-text text-[11px] text-slate-400 font-bold">촬영일자 (B5)</span></label>
                        <input type="text" class="input input-bordered input-sm rounded-lg text-xs font-mono font-medium text-cyan-300 bg-slate-900 header-input" data-cell="B5" value="<?php echo htmlspecialchars($flight_date); ?>">
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
                            <span class="text-[11px] text-amber-400 font-bold">카메라(N5):</span>
                            <select class="select select-bordered select-xs rounded-lg text-xs font-bold bg-slate-900 border-amber-400/40 text-amber-300 header-input" id="select_camera_preset" data-cell="N5" onchange="applyCameraPreset(this.value)">
                                <option value="">-- 프리셋 선택 --</option>
                                <option value="Leica CountryMapper">Leica CountryMapper</option>
                                <option value="DMC III">DMC III</option>
                                <option value="UltraCam Osprey 4.1">UltraCam Osprey 4.1</option>
                                <option value="UltraCam Eagle M3">UltraCam Eagle M3</option>
                                <option value="direct">직접 입력...</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-[10px] font-mono">
                        <div class="p-2 rounded-lg bg-slate-900 border border-white/5">
                            <span class="text-slate-500 block">초점거리(N7 mm)</span>
                            <input type="text" class="bg-transparent text-cyan-300 font-bold w-full outline-none header-input" data-cell="N7" id="cam_n7">
                        </div>
                        <div class="p-2 rounded-lg bg-slate-900 border border-white/5">
                            <span class="text-slate-500 block">카메라번호(N8)</span>
                            <input type="text" class="bg-transparent text-cyan-300 font-bold w-full outline-none header-input" data-cell="N8" id="cam_n8">
                        </div>
                        <div class="p-2 rounded-lg bg-slate-900 border border-white/5">
                            <span class="text-slate-500 block">Pixels(N9)</span>
                            <input type="text" class="bg-transparent text-cyan-300 font-bold w-full outline-none header-input" data-cell="N9" id="cam_n9">
                        </div>
                        <div class="p-2 rounded-lg bg-slate-900 border border-white/5">
                            <span class="text-slate-500 block">Pixel Size(N10 μm)</span>
                            <input type="text" class="bg-transparent text-cyan-300 font-bold w-full outline-none header-input" data-cell="N10" id="cam_n10">
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
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input time-input" data-cell="W5" placeholder="0000" maxlength="4">
                        </div>
                        <div class="form-control">
                            <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">착륙시간(W6)</span></label>
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input time-input" data-cell="W6" placeholder="0000" maxlength="4">
                        </div>
                        <div class="form-control">
                            <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">비행(W7시/AA7분)</span></label>
                            <div class="flex gap-1">
                                <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="W7">
                                <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="AA7">
                            </div>
                        </div>
                        <div class="form-control">
                            <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">계기속도(V8)</span></label>
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="V8">
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
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">기지표고 (H7)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="H7"></div>
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">기준면표고 (H8)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="H8"></div>
                    <div class="form-control"><label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">계기고도 (H9)</span></label><input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 header-input" data-cell="H9"></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-2 pt-2 border-t border-white/5">
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">일기(C11) / 기류(E11)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-medium text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="C11" value="양호">
                            <input type="text" class="input input-bordered input-xs font-medium text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="E11" value="양호">
                        </div>
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">풍향(H11) / 풍속(H12)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="H11">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="H12">
                        </div>
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">기온(N11:1천/N12:4천)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="N11">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="N12">
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
                        <label class="label py-0.5"><span class="label-text text-[10px] text-slate-400">기압(AA9:이륙 / AA11:착륙)</span></label>
                        <div class="flex gap-1">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="AA9">
                            <input type="text" class="input input-bordered input-xs font-mono text-cyan-300 bg-slate-900 w-1/2 text-center header-input" data-cell="AA11">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. 코스별 촬영 세부 내역 (14행부터 시작) -->
            <div class="form-card space-y-2">
                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                    <div class="flex items-center gap-2">
                        <span>📝</span><h3 class="font-bold text-xs text-white">코스별 촬영 세부 내역 (1페이지당 19줄)</h3>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400">기존 파일 데이터 100% 로드됨</span>
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
                            <!-- JS 동적 렌더링 -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <dialog id="modal_editor_notice" class="modal z-[100]">
        <div class="modal-box bg-slate-900 border border-white/10 shadow-2xl rounded-3xl max-w-sm text-center p-6">
            <div id="editor_notice_icon" class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl mx-auto mb-3">ℹ️</div>
            <h3 id="editor_notice_title" class="font-black text-base text-white">문서 안내</h3>
            <p id="editor_notice_message" class="text-xs text-slate-300 mt-2 leading-relaxed break-words">-</p>
            <div class="modal-action justify-center mt-5">
                <button type="button" class="btn btn-sm btn-primary rounded-xl font-bold px-6 text-xs" onclick="modal_editor_notice.close()">확인</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
        const prjId     = <?php echo $prj_id; ?>;
        const dateId    = <?php echo $date_id; ?>;
        const filename  = <?php echo json_encode($filename, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const rawBase64 = "<?php echo $file_base64; ?>";
        const eoPayload = <?php echo json_encode($eo_payload); ?>;

        function showEditorNotice(title, message, type = 'info') {
            const styles = {
                success: ['✅', 'bg-emerald-500/10 text-emerald-400'],
                error: ['⚠️', 'bg-rose-500/10 text-rose-400'],
                warning: ['⚠️', 'bg-amber-500/10 text-amber-400'],
                info: ['ℹ️', 'bg-primary/10 text-primary']
            };
            const [icon, className] = styles[type] || styles.info;
            const iconBox = document.getElementById('editor_notice_icon');
            iconBox.textContent = icon;
            iconBox.className = `w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-3 ${className}`;
            document.getElementById('editor_notice_title').textContent = title;
            document.getElementById('editor_notice_message').textContent = message;
            document.getElementById('modal_editor_notice').showModal();
        }

        const CAMERA_PRESETS = {
            'Leica CountryMapper': { n7: '122.00', n8: '10214', n9: '20160 x 14112', n10: '3.76' },
            'DMC III':            { n7: '92.00',  n8: '30122', n9: '25728 x 14592', n10: '3.90' },
            'UltraCam Osprey 4.1': { n7: '120.00', n8: '40198', n9: '20544 x 14016', n10: '3.76' },
            'UltraCam Eagle M3':   { n7: '100.00', n8: '50102', n9: '26460 x 17004', n10: '4.00' }
        };

        function applyCameraPreset(camName) {
            if (camName === 'direct') {
                document.getElementById('cam_n7').value = '';
                document.getElementById('cam_n8').value = '';
                document.getElementById('cam_n9').value = '';
                document.getElementById('cam_n10').value = '';
                return;
            }
            const preset = CAMERA_PRESETS[camName];
            if (preset) {
                document.getElementById('cam_n7').value = preset.n7;
                document.getElementById('cam_n8').value = preset.n8;
                document.getElementById('cam_n9').value = preset.n9;
                document.getElementById('cam_n10').value = preset.n10;
            }
        }

        // 이착륙 시간 자동계산 로직 추가 (W5, W6 이벤트 감지)
        document.querySelectorAll('.time-input').forEach(input => {
            input.addEventListener('input', function() {
                // 숫자 이외의 문자 제거 및 4자리 제한
                this.value = this.value.replace(/[^0-9]/g, '').substring(0, 4);
                if (this.value.length === 4) {
                    calculateFlightTime();
                }
            });
        });

        function calculateFlightTime() {
            const offVal = document.querySelector('[data-cell="W5"]').value;
            const landVal = document.querySelector('[data-cell="W6"]').value;

            if (offVal.length === 4 && landVal.length === 4) {
                let offH = parseInt(offVal.substring(0, 2), 10);
                let offM = parseInt(offVal.substring(2, 4), 10);
                let landH = parseInt(landVal.substring(0, 2), 10);
                let landM = parseInt(landVal.substring(2, 4), 10);

                if (offH > 23 || offM > 59 || landH > 23 || landM > 59) return;

                let startMins = (offH * 60) + offM;
                let endMins = (landH * 60) + landM;

                // 자정을 넘기는 경우 처리 (예: 2300 이륙, 0100 착륙)
                if (endMins < startMins) endMins += (24 * 60);

                let diff = endMins - startMins;
                let diffH = Math.floor(diff / 60);
                let diffM = diff % 60;

                document.querySelector('[data-cell="W7"]').value = diffH;
                document.querySelector('[data-cell="AA7"]').value = diffM;
            }
        }

        // 코스 테이블 행 UI 생성 함수
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
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900/80 border-cyan-500/30 text-cyan-300 font-mono course-input" data-cell="D${excelRow}" id="row_start_${i}" maxlength="4"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900/80 border-cyan-500/30 text-cyan-300 font-mono course-input" data-cell="G${excelRow}" id="row_end_${i}" maxlength="4"></td>
                    <td><span class="badge badge-ghost badge-xs text-slate-500 font-mono">auto</span></td>
                    <td><input type="text" class="input input-xs w-full text-left px-2 bg-slate-900 border-white/5 text-amber-300 font-mono font-medium course-input" data-cell="O${excelRow}" id="row_range_${i}"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900 border-white/5 text-slate-300 font-bold course-input" data-cell="V${excelRow}" id="row_count_${i}"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900 border-white/5 text-slate-400 course-input" data-cell="X${excelRow}" id="row_remark_${i}"></td>
                    <td><input type="text" class="input input-xs w-full text-center bg-slate-900 border-white/5 text-lg text-primary font-black course-input" data-cell="Y${excelRow}" id="row_arrow_${i}"></td>
                `;
                tbody.appendChild(tr);
            }
        }

        async function loadExistingWorkbook() {
            try {
                const bytes = base64ToUint8Array(rawBase64);
                const workbook = new ExcelJS.Workbook();
                await workbook.xlsx.load(bytes.buffer);
                const ws = workbook.worksheets[0];

                // 1. 공통 헤더 읽어와 화면 input에 주입
                document.querySelectorAll('.header-input').forEach(input => {
                    const cellAddr = input.dataset.cell;
                    const cell = ws.getCell(cellAddr);
                    if (cell.value !== null && cell.value !== undefined) {
                        // 시간 등 4자리 문자열 유지를 위해 padStart 사용
                        let valStr = String(cell.value).trim();
                        if (['W5', 'W6'].includes(cellAddr) && valStr.length < 4 && !isNaN(valStr)) {
                            valStr = valStr.padStart(4, '0');
                        }
                        input.value = valStr;
                    }
                });

                // 카메라 프리셋 연동
                const n5Val = document.querySelector('[data-cell="N5"]').value;
                if (n5Val) applyCameraPreset(n5Val);

                // 2. 엑셀에 작성되어 있는 코스 행 탐색
                let maxExcelRow = 32;
                ws.eachRow({ includeEmpty: false }, (row, rowNumber) => {
                    if (rowNumber >= 14) {
                        const valA = row.getCell(1).value; // A열(코스)
                        const valO = row.getCell(15).value; // O열(스트립 범위)
                        if (valA !== null || valO !== null) {
                            maxExcelRow = Math.max(maxExcelRow, rowNumber);
                        }
                    }
                });

                // 총 필요 행 수 계산
                const totalPages = Math.ceil((maxExcelRow - 13) / 34) || 1;
                const totalCourseRows = totalPages * 19;
                
                // 코스 UI 테이블 생성
                buildCourseRows(totalCourseRows);

                // 3. UI 인풋에 기존 엑셀 값 매핑 주입
                for (let i = 0; i < totalCourseRows; i++) {
                    const p = Math.floor(i / 19);
                    const rInP = i % 19;
                    const r = (p * 34) + 14 + rInP;

                    const getVal = (colIdx, isTime = false) => {
                        const cell = ws.getRow(r).getCell(colIdx);
                        if (cell.value === null || cell.value === undefined) return '';
                        let v = String(cell.value).trim();
                        if (isTime && v.length < 4 && !isNaN(v)) v = v.padStart(4, '0');
                        return v;
                    };

                    const valA = getVal(1);       // A: 코스
                    const valD = getVal(4, true); // D: 개시시간 (4자리 유지)
                    const valG = getVal(7, true); // G: 종료시간 (4자리 유지)
                    const valO = getVal(15);      // O: Strip 범위
                    const valV = getVal(22);      // V: 매수
                    const valX = getVal(24);      // X: 비고
                    const valY = getVal(25);      // Y: 방향

                    if (valA) document.getElementById(`row_course_${i}`).value = valA;
                    if (valD) document.getElementById(`row_start_${i}`).value = valD;
                    if (valG) document.getElementById(`row_end_${i}`).value = valG;
                    if (valO) document.getElementById(`row_range_${i}`).value = valO;
                    if (valV) document.getElementById(`row_count_${i}`).value = valV;
                    if (valX) document.getElementById(`row_remark_${i}`).value = valX;
                    if (valY) document.getElementById(`row_arrow_${i}`).value = valY;
                }

            } catch (err) {
                console.error('엑셀 파싱 오류:', err);
            }
        }

        // EO 분석 자동 배치 함수 (기존 로직 유지)
        function autoPopulateFromEO() {
            if (!eoPayload || !eoPayload.exists || !eoPayload.data) {
                showEditorNotice('EO 파일이 없습니다', '자동 분석을 하려면 먼저 해당 촬영일의 EO 파일을 등록해 주세요.', 'warning'); return;
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

            const findInspectResultIndex = (rows) => {
                if (rows.length > 0 && Array.isArray(rows[0])) {
                    let lonIdx = -1;
                    const headerRow = rows[0].map(c => String(c ?? '').trim().toLowerCase());
                    const resultIdx = headerRow.findIndex(h => h === '검수결과' || h === 'inspection' || h.includes('inspect'));
                    if (resultIdx !== -1) return resultIdx;
                    lonIdx = headerRow.findIndex(h => h === 'lon(deg)' || h === 'lon' || h.includes('longitude'));
                    if (lonIdx !== -1) return lonIdx + 1;
                }
                return 10;
            };

            const readInspectResult = (row, inspectIdx) => {
                const primary = row.length > inspectIdx ? String(row[inspectIdx] ?? '').trim() : '';
                if (primary) return primary;
                for (let i = 8; i < row.length; i++) {
                    const candidate = String(row[i] ?? '').trim();
                    if (candidate.startsWith('재촬영') || candidate.startsWith('중복미사용') || candidate.startsWith('미사용')) return candidate;
                }
                return '';
            };

            const parseShotParts = (rawId) => {
                const id = String(rawId || '').trim().toUpperCase();
                let m = id.match(/^0*(\d+)[-_]0*(\d+)([A-Z])?$/);
                if (m) return { courseNo: parseInt(m[1], 10), shotNo: parseInt(m[2], 10), suffix: m[3] || '' };
                m = id.match(/^(\d{4})(\d{4,5})([A-Z])?$/);
                if (m) return { courseNo: parseInt(m[1], 10), shotNo: parseInt(m[2], 10), suffix: m[3] || '' };
                return null;
            };

            const buildReshootWindowMarks = (rows) => {
                const aShots = {};
                rows.forEach(row => {
                    if (!row || row.length < 1) return;
                    const parts = parseShotParts(row[0]);
                    if (!parts || !parts.courseNo || !parts.shotNo || !parts.suffix) return;
                    if (!aShots[parts.courseNo]) aShots[parts.courseNo] = new Set();
                    aShots[parts.courseNo].add(parts.shotNo);
                });

                const marks = { overlap: {}, actual: {} };
                Object.keys(aShots).forEach(courseNo => {
                    const shots = Array.from(aShots[courseNo]).sort((a, b) => a - b);
                    let start = null, prev = null;
                    const flush = () => {
                        if (start === null) return;
                        for (let n = start; n <= prev; n++) {
                            if ((n - start) < 3 || (prev - n) < 3) {
                                if (!marks.overlap[courseNo]) marks.overlap[courseNo] = new Set();
                                marks.overlap[courseNo].add(n);
                            } else {
                                if (!marks.actual[courseNo]) marks.actual[courseNo] = new Set();
                                marks.actual[courseNo].add(n);
                            }
                        }
                    };
                    shots.forEach(n => {
                        if (start === null || n > prev + 1) {
                            flush();
                            start = n;
                        }
                        prev = n;
                    });
                    flush();
                });
                return marks;
            };

            const inspectResultIdx = findInspectResultIndex(rawRows);
            const reshootOverlapMarks = buildReshootWindowMarks(rawRows);
            const validRecords = [];
            let excludedByInspect = 0;
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

                // A 재촬영 구간의 앞뒤 3장은 본촬영 겹침 구간이므로 재촬영 표시가 있어도 산정에 포함
                const parsedParts = parseShotParts(rawId);
                const isOriginalActualReshoot = parsedParts && !parsedParts.suffix && reshootOverlapMarks.actual[parsedParts.courseNo]?.has(parsedParts.shotNo);
                const hasActualWindow = parsedParts && !parsedParts.suffix && !!reshootOverlapMarks.actual[parsedParts.courseNo]?.size;
                const inspectResult = readInspectResult(row, inspectResultIdx);
                const isReshootMarked = inspectResult.startsWith('재촬영');
                const isUnusedMarked = inspectResult.startsWith('중복미사용') || inspectResult.startsWith('미사용');
                const excludeReshoot = isReshootMarked && !parsedParts?.suffix && (hasActualWindow ? isOriginalActualReshoot : true);
                if (excludeReshoot || isUnusedMarked) {
                    excludedByInspect++;
                    return;
                }

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

            if (validRecords.length === 0) { showEditorNotice('분석할 데이터가 없습니다', 'EO 파일에서 유효한 촬영 데이터를 찾지 못했습니다.', 'warning'); return; }
            validRecords.sort((a, b) => a.gpsTime - b.gpsTime);

            const courseMap = new Map();
            validRecords.forEach(rec => {
                if (!courseMap.has(rec.courseNo)) courseMap.set(rec.courseNo, []);
                courseMap.get(rec.courseNo).push({ id: rec.shotNo, kappa: rec.kappa });
            });

            const sortedCourses = Array.from(courseMap.keys()).sort((a, b) => a - b);
            buildCourseRows(sortedCourses.length);

            const formatRange = (shotList) => {
                if (!shotList || shotList.length === 0) return '';
                const normals = [], reshoots = [];
                shotList.forEach(item => {
                    const rawShot = String(item.id).trim().toUpperCase();
                    const num = parseInt(rawShot.replace(/[^0-9]/g, ''), 10);
                    if (!isNaN(num)) {
                        if (rawShot.includes('A')) { if (!reshoots.includes(num)) reshoots.push(num); }
                        else { if (!normals.includes(num)) normals.push(num); }
                    }
                });
                normals.sort((a, b) => a - b);
                reshoots.sort((a, b) => a - b);
                const getR = (arr, suffix = '') => {
                    if (arr.length === 0) return [];
                    const ranges = [];
                    let start = arr[0], prev = arr[0];
                    for (let i = 1; i < arr.length; i++) {
                        if (arr[i] === prev + 1) prev = arr[i];
                        else { ranges.push(start === prev ? `${start}${suffix}` : `${start}${suffix}-${prev}${suffix}`); start = prev = arr[i]; }
                    }
                    ranges.push(start === prev ? `${start}${suffix}` : `${start}${suffix}-${prev}${suffix}`);
                    return ranges;
                };
                return [...getR(normals, ''), ...getR(reshoots, 'A')].join(', ');
            };

            sortedCourses.forEach((courseNo, index) => {
                const shots = courseMap.get(courseNo);
                const count = shots.length;
                const avgKappa = shots.reduce((acc, cur) => acc + cur.kappa, 0) / (count || 1);
                const arrow = (Math.abs(avgKappa) >= 90) ? '←' : '→';

                document.getElementById(`row_course_${index}`).value = courseNo;
                document.getElementById(`row_range_${index}`).value = formatRange(shots);
                document.getElementById(`row_count_${index}`).value = count;
                document.getElementById(`row_arrow_${index}`).value = arrow;
            });

            const exclusionNote = excludedByInspect > 0 ? ` (검수내역상 재촬영/중복미사용 ${excludedByInspect}건 제외됨)` : '';
            showEditorNotice('자동 분석 완료', `총 ${sortedCourses.length}개 코스를 문서에 반영했습니다.${exclusionNote} 저장하면 엑셀 파일에 적용됩니다.`, 'success');
        }

        // 데이터 유형에 따라 엑셀 저장 형식을 정하는 헬퍼 함수
        function parseCellValue(addr, val) {
            if (val === '') return null;
            // 특정 셀(이착륙 및 코스 시간)은 0을 포함한 4자리 문자열 그대로 유지
            if (['W5', 'W6'].includes(addr) || addr.startsWith('D') || addr.startsWith('G')) {
                return val;
            }
            // 그 외 숫자로 변환 가능한 값은 Number 처리
            return !isNaN(val) ? Number(val) : val;
        }

        // 엑셀 저장 함수
        async function exportAndSaveExcel() {
            const btn = document.getElementById('btn_save_server');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span><span>저장 중...</span>';

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
                    cell.value = parseCellValue(cellAddr, v);
                });

                // 2. 1페이지 코스 영역 초기화
                for (let r = 14; r <= 32; r++) {
                    ['A','D','G','I','K','M','O','V','X','Y'].forEach(c => {
                        worksheet.getCell(`${c}${r}`).value = null;
                    });
                }

                // 3. 페이지네이션 복제
                const courseInputs = document.querySelectorAll('input[id^="row_course_"]');
                const totalCourses = Math.max(19, courseInputs.length);
                const totalPages = Math.ceil(totalCourses / 19);

                const originalMerges = Object.values(worksheet._merges || {}).map(m => ({
                    top: m.top, left: m.left, bottom: m.bottom, right: m.right
                }));

                for (let p = 1; p < totalPages; p++) {
                    const rowOffset = p * 34;

                    for (let r = 1; r <= 32; r++) {
                        const srcRow = worksheet.getRow(r);
                        const dstRow = worksheet.getRow(r + rowOffset);
                        if (srcRow.height) dstRow.height = srcRow.height;

                        for (let c = 1; c <= 29; c++) {
                            const srcCell = srcRow.getCell(c);
                            const dstCell = dstRow.getCell(c);
                            dstCell.style = JSON.parse(JSON.stringify(srcCell.style));
                            dstCell.value = srcCell.value;
                        }
                    }

                    originalMerges.forEach(m => {
                        if (m.top >= 1 && m.bottom <= 32) {
                            try {
                                worksheet.mergeCells(m.top + rowOffset, m.left, m.bottom + rowOffset, m.right);
                            } catch(e){}
                        }
                    });

                    worksheet.getRow(2 + rowOffset).getCell(29).value = p + 1;
                }

                // 4. 코스 데이터 주입
                document.querySelectorAll('.course-input').forEach(input => {
                    const cellAddr = input.dataset.cell;
                    const v = input.value.trim();
                    if (v !== '') {
                        const cell = worksheet.getCell(cellAddr);
                        cell.value = parseCellValue(cellAddr, v);
                    }
                });

                // 5. auto 고정값 주입
                for (let p = 0; p < totalPages; p++) {
                    const offset = p * 34;
                    for (let r = 14; r <= 32; r++) {
                        worksheet.getCell(`I${r + offset}`).value = 'auto';
                        worksheet.getCell(`K${r + offset}`).value = 'auto';
                        worksheet.getCell(`M${r + offset}`).value = 'auto';
                    }
                }

                // 6. 바이너리 생성 후 서버 AJAX 전송
                const buffer = await workbook.xlsx.writeBuffer();
                let binaryStr = '';
                const bytesOut = new Uint8Array(buffer);
                for (let i = 0; i < bytesOut.byteLength; i++) binaryStr += String.fromCharCode(bytesOut[i]);
                const wbBase64 = btoa(binaryStr);

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
                    showEditorNotice('저장 완료', '문서 폴더에 변경 내용을 저장했습니다.', 'success');
                } else {
                    showEditorNotice('저장 실패', result.message || '문서를 저장하지 못했습니다.', 'error');
                }
            } catch (err) {
                console.error(err);
                showEditorNotice('저장 중 오류', err.message || '문서를 저장하지 못했습니다.', 'error');
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

        window.addEventListener('load', () => {
            loadExistingWorkbook();
        });
    </script>
</body>
</html>
