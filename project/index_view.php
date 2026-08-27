<?php
include_once('./_common.php');

if (!isset($is_member) || !$is_member) {
    goto_url(G5_BBS_URL.'/login.php');
}

$prj_id  = isset($_GET['prj_id']) ? (int)$_GET['prj_id'] : 0;
$date_id = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;

if (!$prj_id || !$date_id) {
    alert('잘못된 접근입니다.', G5_URL.'/index.php');
}

$prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ") ?: [
    'prj_name' => '프로젝트',
    'prj_id'   => $prj_id
];

$flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ") ?: [
    'flight_date' => date('Y-m-d'),
    'flight_name' => '촬영일 미등록',
    'date_id'     => $date_id
];

$g5['title'] = htmlspecialchars($prj['prj_name']) . ' - INDEX 도면 뷰어 [' . ($flight['flight_date'] ?? '') . ']';

// 1. 디렉토리 스캔 (PHP 8.2 순수 UTF-8 경로 처리)
$base_dir = 'E:\#KYS_IMAGERY_SERVER\\' . trim($prj['prj_name']);
$index_dir = $base_dir . '\\date\\' . trim($flight['flight_date']) . '\\INDEX';

$dwg_files = [];
$active_dwg_file = isset($_GET['active_file']) ? trim($_GET['active_file']) : '';

if (is_dir($index_dir)) {
    $files = scandir($index_dir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        
        $full_fpath = $index_dir . '\\' . $f;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['dwg', 'dxf', 'json', 'geojson']) && is_file($full_fpath)) {
            $dwg_files[] = [
                'filename' => $f,
                'path'     => $full_fpath,
                'mtime'    => date('Y-m-d H:i:s', @filemtime($full_fpath) ?: time()),
                'size'     => round((@filesize($full_fpath) ?: 0) / 1024, 1) . ' KB'
            ];
        }
    }
}

// 활성화 파일 지정
if (!$active_dwg_file) {
    $active_idx_row = sql_fetch(" SELECT * FROM IMG_FLIGHT_INDEX WHERE prj_id = {$prj_id} AND date_id = {$date_id} AND is_active = 1 ");
    if ($active_idx_row && file_exists($active_idx_row['file_path'])) {
        $active_dwg_file = $active_idx_row['file_name'];
    } else if (!empty($dwg_files)) {
        $active_dwg_file = $dwg_files[0]['filename'];
    }
}

// 2. DXF 캔버스 렌더링용 파서 (순수 UTF-8 파일 로드)
$parsed_pts = [];
if ($active_dwg_file) {
    $target_dxf_path = $index_dir . '\\' . $active_dwg_file;
    if (file_exists($target_dxf_path)) {
        $dxf_content = file_get_contents($target_dxf_path);
        $lines = preg_split('/\r\n|\n|\r/', $dxf_content);
        $count = count($lines);
        $in_entities = false;

        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            if ($line === 'ENTITIES') { $in_entities = true; continue; }
            if ($line === 'ENDSEC' && $in_entities) { break; }

            if ($in_entities && $line === 'TEXT') {
                $cur_id = ''; $cur_x = 0; $cur_y = 0; $cur_layer = '';
                for ($j = $i + 1; $j < min($i + 40, $count); $j += 2) {
                    if (!isset($lines[$j]) || !isset($lines[$j+1])) break;
                    $code = trim($lines[$j]);
                    $val = trim($lines[$j+1]);
                    if ($code === '0') break;

                    if ($code === '8')  $cur_layer = $val;
                    if ($code === '10') $cur_x = (float)$val;
                    if ($code === '20') $cur_y = (float)$val;
                    if ($code === '1')  $cur_id = $val;
                }

                if ($cur_id && ($cur_x != 0 || $cur_y != 0)) {
                    $parsed_pts[] = [
                        'id' => $cur_id,
                        'x'  => $cur_x,
                        'y'  => $cur_y,
                        'is_reshoot' => (strpos($cur_layer, '_A') !== false || preg_match('/[a-zA-Z]$/', $cur_id))
                    ];
                }
            }
        }
    }
}

$points_json = json_encode($parsed_pts);
?>
<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css" rel="stylesheet" type="text/css" />
    
    <style>
        html, body {
            width: 100vw;
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #060a12;
            font-family: "Pretendard", -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
        }
        :root, [data-theme] {
            --rounded-box: 1.25rem;
            --rounded-btn: 0.875rem;
        }
        .glass-float {
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.7);
        }
        .canvas-grid {
            background-size: 60px 60px;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        }
    </style>
</head>
<body class="relative w-full h-full select-none overflow-hidden bg-slate-950 flex flex-col justify-between">

    <!-- 1. 좌측 상단 플로팅 메뉴 바 -->
    <div class="fixed top-5 left-5 z-50 flex items-center gap-2">
        <div class="glass-float rounded-2xl p-1.5 flex items-center gap-1.5 shadow-2xl">
            <a href="view.php?id=<?php echo $prj_id; ?>&tab=tab-flight" 
               class="btn btn-sm btn-ghost hover:bg-base-100/60 rounded-xl text-xs font-bold gap-1.5 px-3 text-base-content/90 transition-all active:scale-95">
                <i class="fa-solid fa-arrow-left text-primary text-[11px]"></i>
                <span>돌아가기</span>
            </a>
            
            <div class="divider divider-horizontal mx-0.5 my-1.5 w-[1px] bg-base-content/10"></div>

            <button type="button" class="btn btn-sm btn-primary rounded-xl text-xs font-bold gap-1.5 px-3 shadow-md shadow-primary/25 hover:scale-[1.02] active:scale-95 transition-all" onclick="openIndexPreviewModal()">
                <span>🗺️</span>
                <span>인덱스 생성</span>
            </button>

            <button type="button" class="btn btn-sm btn-ghost hover:bg-base-100/60 rounded-xl text-xs font-bold gap-1.5 px-3 text-base-content/90 transition-all" onclick="modal_index_list.showModal()">
                <i class="fa-solid fa-list-ul text-info text-[11px]"></i>
                <span>인덱스 리스트</span>
                <?php if(!empty($dwg_files)) { ?>
                    <span class="badge badge-neutral badge-xs font-mono font-bold"><?php echo count($dwg_files); ?></span>
                <?php } ?>
            </button>
        </div>

        <?php if(!empty($active_dwg_file)) { ?>
            <div class="glass-float rounded-2xl px-3.5 py-2 flex items-center gap-2 text-xs font-mono">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success"></span>
                </span>
                <span class="font-bold text-base-content/80 text-[11px]">LOADED:</span>
                <span class="font-bold text-primary truncate max-w-xs" title="<?php echo htmlspecialchars($active_dwg_file); ?>"><?php echo htmlspecialchars($active_dwg_file); ?></span>
                <span class="badge badge-neutral badge-xs font-mono"><?php echo count($parsed_pts); ?> pts</span>
            </div>
        <?php } ?>
    </div>

    <!-- 2. 우측 상단 정보 및 줌/맞춤 컨트롤러 -->
    <div class="fixed top-5 right-5 z-40 flex items-center gap-2">
        <div class="glass-float rounded-2xl px-4 py-2 flex items-center gap-2 text-xs font-bold">
            <span class="text-base">📁</span>
            <span class="text-base-content/90"><?php echo htmlspecialchars($prj['prj_name']); ?></span>
            <span class="badge badge-primary badge-sm font-mono"><?php echo htmlspecialchars($flight['flight_date']); ?></span>
        </div>
        
        <div class="glass-float rounded-2xl p-1 flex items-center gap-1">
            <button class="btn btn-xs btn-circle btn-ghost" onclick="zoomIn()" title="확대 (+)"><i class="fa-solid fa-plus"></i></button>
            <button class="btn btn-xs btn-circle btn-ghost" onclick="zoomOut()" title="축소 (-)"><i class="fa-solid fa-minus"></i></button>
            <button class="btn btn-xs btn-circle btn-ghost text-primary" onclick="fitView(true)" title="화면맞춤 (휠 더블클릭)"><i class="fa-solid fa-expand"></i></button>
        </div>
    </div>

    <!-- 3. 메인 뷰어 캔버스 영역 -->
    <div class="relative w-full h-full canvas-grid flex items-center justify-center overflow-hidden">
        <?php if(!empty($active_dwg_file) && !empty($parsed_pts)) { ?>
            <canvas id="cad_canvas" class="w-full h-full cursor-grab active:cursor-grabbing"></canvas>
            
            <div class="fixed bottom-6 left-6 z-30 pointer-events-none">
                <div class="glass-float rounded-xl px-4 py-2.5 text-[11px] font-medium text-base-content/70 flex items-center gap-4">
                    <span>🖱️ <strong>휠/좌클릭 드래그</strong>: 이동</span>
                    <span>🔍 <strong>휠 스크롤</strong>: 줌</span>
                    <span>🎯 <strong class="text-primary">마우스 휠 더블클릭</strong>: 화면 맞춤</span>
                    <span class="flex items-center gap-1.5 ml-2"><span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span> 본촬영</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400 inline-block"></span> 재촬영(_A)</span>
                </div>
            </div>
        <?php } else { ?>
            <div class="glass-float rounded-3xl p-10 max-w-md text-center shadow-2xl border border-base-content/10 flex flex-col items-center gap-4">
                <div class="w-20 h-20 rounded-2xl bg-base-200/80 flex items-center justify-center text-4xl shadow-inner border border-base-content/10">
                    🗺️
                </div>
                <div>
                    <h3 class="text-xl font-black text-base-content">생성된 INDEX가 없습니다</h3>
                    <p class="text-xs text-base-content/60 mt-1.5 leading-relaxed">
                        현재 촬영일(<code><?php echo htmlspecialchars($flight['flight_date']); ?></code>)의 INDEX 도면이 등록되지 않았습니다.<br>
                        좌측 상단의 <strong>[인덱스 생성]</strong> 버튼을 눌러 도면을 생성해 주세요.
                    </p>
                </div>
                <div class="flex gap-2 mt-2">
                    <button type="button" class="btn btn-primary btn-sm rounded-xl font-bold px-5" onclick="openIndexPreviewModal()">
                        <span>🚀 지금 인덱스 생성하기</span>
                    </button>
                </div>
            </div>
        <?php } ?>
    </div>

    <!-- ================= MODALS ================= -->

    <!-- 인덱스 생성 전 사전 검증 모달 -->
    <dialog id="modal_index_preview" class="modal z-[220]">
        <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-4xl w-11/12 h-[82vh] flex flex-col p-6">
            
            <div class="flex justify-between items-center pb-3 border-b border-base-content/10">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">📊</span>
                    <div>
                        <h3 class="font-black text-lg text-base-content flex items-center gap-2">
                            <span>인덱스 생성 데이터 사전 검증</span>
                            <span id="preview_total_badge" class="badge badge-primary font-mono font-bold text-xs py-2 px-2.5">0 매</span>
                        </h3>
                        <p class="text-[11px] text-base-content/60" id="preview_eo_source">성과 파일: -</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span id="badge_main_count" class="badge badge-neutral font-mono font-bold text-xs py-2 px-2.5">본촬영: 0매</span>
                    <span id="badge_reshoot_count" class="badge badge-warning font-mono font-bold text-xs py-2 px-2.5">재촬영: 0매</span>
                </div>
            </div>

            <div class="flex-1 overflow-auto custom-scrollbar my-4 rounded-xl border border-base-content/15 bg-base-200/30">
                <table class="table table-xs table-pin-rows font-mono w-full text-center">
                    <thead>
                        <tr class="bg-base-300 text-base-content font-bold border-b border-base-content/15 text-[11px]">
                            <th class="w-12 bg-base-300">No</th>
                            <th class="w-32 text-left bg-base-300 text-primary font-bold">ID (사진번호)</th>
                            <th>X (Easting)</th>
                            <th>Y (Northing)</th>
                            <th>Z (Height)</th>
                            <th class="w-24">구분</th>
                        </tr>
                    </thead>
                    <tbody id="preview_tbody" class="divide-y divide-base-content/5 text-[11px]">
                        <tr><td colspan="6" class="py-12 text-center text-base-content/40 font-sans font-bold">데이터를 로드하고 있습니다...</td></tr>
                    </tbody>
                </table>
            </div>

            <form id="form_generate_dxf" method="post" action="action.php" class="flex flex-col gap-3">
                <input type="hidden" name="action" value="generate_index_dwg">
                <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
                <input type="hidden" name="date_id" value="<?php echo $date_id; ?>">

                <div class="grid grid-cols-2 gap-3 bg-base-200/50 p-2.5 rounded-xl border border-base-content/5 text-xs">
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text font-bold text-[11px]">생성 파일명 (.dxf)</span></label>
                        <input type="text" name="index_name" value="<?php echo htmlspecialchars($prj['prj_name'] . '_' . str_replace('-', '', $flight['flight_date']) . '_INDEX.dxf'); ?>" class="input input-bordered input-xs rounded-lg font-mono" required>
                    </div>
                    <div class="form-control">
                        <label class="label py-0.5"><span class="label-text font-bold text-[11px]">기준 좌표계</span></label>
                        <select name="crs_type" class="select select-bordered select-xs rounded-lg">
                            <option value="EPSG:5186" selected>EPSG:5186 (GRS80 중부원점)</option>
                            <option value="EPSG:5187">EPSG:5187 (GRS80 동부원점)</option>
                            <option value="EPSG:5185">EPSG:5185 (GRS80 서부원점)</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-base-content/10">
                    <span class="text-xs text-base-content/60 font-medium">
                        💡 ID에 알파벳이 포함된 사진은 <strong>재촬영</strong> 레이어(노란색)로 분리 생성됩니다.
                    </span>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_index_preview.close()">취소</button>
                        <button type="submit" id="btn_submit_create_dxf" class="btn btn-primary btn-sm rounded-xl font-bold px-6 shadow-md shadow-primary/25" disabled>
                            <span>도면 생성 및 렌더링 🚀</span>
                        </button>
                    </div>
                </div>
            </form>

        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <!-- 인덱스 리스트 모달 (UTF-8 정상 렌더링) -->
    <dialog id="modal_index_list" class="modal z-[200]">
        <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-lg p-6 flex flex-col max-h-[80vh]">
            <div class="flex justify-between items-center pb-3 border-b border-base-content/10">
                <h3 class="font-black text-lg text-base-content flex items-center gap-2">
                    <span>📑 INDEX 도면 목록</span>
                    <span class="badge badge-primary font-mono text-xs"><?php echo count($dwg_files); ?>개</span>
                </h3>
                <button type="button" class="btn btn-sm btn-circle btn-ghost" onclick="modal_index_list.close()">✕</button>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar my-4 rounded-xl border border-base-content/10 bg-base-200/30">
                <table class="table table-xs w-full text-center select-none font-mono">
                    <thead class="bg-base-200 text-base-content font-bold sticky top-0 z-10 text-[11px]">
                        <tr>
                            <th class="text-left px-3">파일명</th>
                            <th class="w-36">수정일자</th>
                            <th class="w-20">크기</th>
                            <th class="w-20">선택</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-base-content/5 text-xs">
                        <?php if(!empty($dwg_files)) { 
                            foreach($dwg_files as $f) { 
                                $is_cur = ($f['filename'] === $active_dwg_file);
                        ?>
                            <tr class="hover:bg-base-200/60 <?php echo $is_cur ? 'bg-primary/10' : ''; ?>">
                                <td class="text-left px-3 font-bold <?php echo $is_cur ? 'text-primary' : ''; ?> truncate max-w-xs" title="<?php echo htmlspecialchars($f['filename']); ?>">
                                    <?php echo htmlspecialchars($f['filename']); ?>
                                </td>
                                <td class="text-base-content/60 text-[11px]"><?php echo $f['mtime']; ?></td>
                                <td class="text-base-content/60 text-[11px]"><?php echo $f['size']; ?></td>
                                <td>
                                    <?php if($is_cur) { ?>
                                        <span class="badge badge-primary badge-xs py-1 px-1.5 font-bold font-sans">활성화됨</span>
                                    <?php } else { ?>
                                        <button class="btn btn-xs btn-outline btn-primary rounded-lg font-sans font-bold" onclick="selectActiveIndex('<?php echo htmlspecialchars($f['filename'], ENT_QUOTES); ?>')">로드</button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="4" class="py-10 text-base-content/40 font-bold font-sans">생성된 도면 파일이 없습니다.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="modal-action pt-2 border-t border-base-content/10 justify-end">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_index_list.close()">닫기</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
    function openIndexPreviewModal() {
        const tbody = document.getElementById('preview_tbody');
        tbody.innerHTML = `<tr><td colspan="6" class="py-16 text-center text-base-content/50 font-sans font-bold">
            <span class="loading loading-spinner loading-md text-primary mb-2 block mx-auto"></span>
            EO 성과 파일을 분석하고 있습니다...
        </td></tr>`;
        document.getElementById('btn_submit_create_dxf').disabled = true;

        modal_index_preview.showModal();

        fetch(`action.php?action=preview_index_data&prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $date_id; ?>`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-error font-sans font-bold">${data.message}</td></tr>`;
                    return;
                }

                document.getElementById('preview_total_badge').innerText = `${data.total.toLocaleString()} 매`;
                document.getElementById('preview_eo_source').innerText = `성과 파일: ${data.eo_filename}`;
                document.getElementById('badge_main_count').innerText = `본촬영: ${data.main_count.toLocaleString()}매`;
                document.getElementById('badge_reshoot_count').innerText = `재촬영: ${data.reshoot_count.toLocaleString()}매`;

                tbody.innerHTML = '';
                const renderLimit = Math.min(data.entities.length, 500);

                for (let i = 0; i < renderLimit; i++) {
                    const item = data.entities[i];
                    const tr = document.createElement('tr');
                    tr.className = `hover:bg-base-200/50 ${item.is_reshoot ? 'bg-warning/5' : ''}`;
                    
                    const badgeHtml = item.is_reshoot 
                        ? '<span class="badge badge-warning badge-xs font-sans font-bold px-2 py-0.5 whitespace-nowrap">재촬영</span>'
                        : '<span class="badge badge-ghost badge-xs font-sans font-medium px-2 py-0.5 whitespace-nowrap">본촬영</span>';

                    tr.innerHTML = `
                        <td class="text-base-content/40">${i + 1}</td>
                        <td class="text-left font-bold ${item.is_reshoot ? 'text-warning' : 'text-primary'}">${item.id}</td>
                        <td>${item.x.toFixed(3)}</td>
                        <td>${item.y.toFixed(3)}</td>
                        <td>${item.z.toFixed(3)}</td>
                        <td>${badgeHtml}</td>
                    `;
                    tbody.appendChild(tr);
                }

                if (data.entities.length > renderLimit) {
                    const noticeTr = document.createElement('tr');
                    noticeTr.innerHTML = `<td colspan="6" class="py-2 text-center text-base-content/40 font-sans text-xs">... 외 ${(data.entities.length - renderLimit).toLocaleString()}건 생략 (전체 ${data.entities.length.toLocaleString()}건 DXF 생성에 포함됨)</td>`;
                    tbody.appendChild(noticeTr);
                }

                document.getElementById('btn_submit_create_dxf').disabled = false;
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-error font-sans font-bold">통신 오류: ${err.message}</td></tr>`;
            });
    }

    const rawPoints = <?php echo $points_json; ?>;
    const canvas = document.getElementById('cad_canvas');

    let scale = 1;
    let panX = 0, panY = 0;
    let isDragging = false;
    let startX = 0, startY = 0;
    let lastWheelClickTime = 0;

    let minX = 0, maxX = 0, minY = 0, maxY = 0, centerX = 0, centerY = 0;

    if (canvas && rawPoints && rawPoints.length > 0) {
        const ctx = canvas.getContext('2d');

        function calculateBounds() {
            minX = rawPoints[0].x; maxX = rawPoints[0].x;
            minY = rawPoints[0].y; maxY = rawPoints[0].y;

            rawPoints.forEach(p => {
                if (p.x < minX) minX = p.x;
                if (p.x > maxX) maxX = p.x;
                if (p.y < minY) minY = p.y;
                if (p.y > maxY) maxY = p.y;
            });

            centerX = (minX + maxX) / 2;
            centerY = (minY + maxY) / 2;
        }

        function fitView(animate = false) {
            canvas.width = canvas.parentElement.clientWidth;
            canvas.height = canvas.parentElement.clientHeight;

            const padding = 120;
            const w = canvas.width - padding;
            const h = canvas.height - padding;

            const dx = Math.max(10, maxX - minX);
            const dy = Math.max(10, maxY - minY);

            const targetScale = Math.min(w / dx, h / dy);
            const targetPanX = 0;
            const targetPanY = 0;

            if (animate) {
                let startTime = null;
                const initialScale = scale;
                const initialPanX = panX;
                const initialPanY = panY;

                function step(timestamp) {
                    if (!startTime) startTime = timestamp;
                    const progress = Math.min((timestamp - startTime) / 250, 1);
                    const ease = 1 - Math.pow(1 - progress, 3);

                    scale = initialScale + (targetScale - initialScale) * ease;
                    panX = initialPanX + (targetPanX - initialPanX) * ease;
                    panY = initialPanY + (targetPanY - initialPanY) * ease;

                    draw();
                    if (progress < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            } else {
                scale = targetScale;
                panX = targetPanX;
                panY = targetPanY;
                draw();
            }
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (!rawPoints || rawPoints.length === 0) return;

            ctx.save();
            ctx.translate(canvas.width / 2 + panX, canvas.height / 2 + panY);
            ctx.scale(scale, -scale); // CAD Y축 상향 반전

            // 1. 코스별 선형(Flight Line) 렌더링
            const courses = {};
            rawPoints.forEach(p => {
                const cNo = p.id.includes('_') ? p.id.split('_')[0] : p.id.substring(0, 4);
                if (!courses[cNo]) courses[cNo] = [];
                courses[cNo].push(p);
            });

            ctx.lineWidth = Math.max(1, 1.5 / scale);
            ctx.strokeStyle = 'rgba(99, 102, 241, 0.45)';
            ctx.setLineDash([8 / scale, 6 / scale]);

            Object.values(courses).forEach(pts => {
                if (pts.length > 1) {
                    ctx.beginPath();
                    ctx.moveTo(pts[0].x - centerX, pts[0].y - centerY);
                    for (let i = 1; i < pts.length; i++) {
                        ctx.lineTo(pts[i].x - centerX, pts[i].y - centerY);
                    }
                    ctx.stroke();
                }
            });

            ctx.setLineDash([]);

            // -------------------------------------------------------------
            // ★ LOD (Level of Detail) 줌 레벨에 따른 동적 디스플레이 제어
            // -------------------------------------------------------------
            // 화면에 보여지는 실제 텍스트의 픽셀 크기 (DXF 기준 텍스트 높이: 150)
            const screenTextSize = 150 * scale;
            // 축소되어 텍스트가 6px 미만이 되면 렌더링을 완전히 생략 (어지러움/노이즈 방지)
            const showText = screenTextSize > 6;
            
            // 점(원)의 크기: 아무리 축소해도 화면상 최소 2px은 유지하되, 최대 CAD 크기 50으로 제한
            const dotRadius = Math.max(50, 2 / scale);

            rawPoints.forEach(p => {
                const px = p.x - centerX;
                const py = p.y - centerY;

                // 2. 점(원) 렌더링
                ctx.beginPath();
                ctx.arc(px, py, dotRadius, 0, Math.PI * 2);
                ctx.fillStyle = p.is_reshoot ? '#facc15' : '#ef4444';
                ctx.fill();
                
                // 테두리는 줌인 상태에서만 연하게 표시
                if (scale > 0.05) {
                    ctx.strokeStyle = 'rgba(255, 255, 255, 0.5)';
                    ctx.lineWidth = 1 / scale;
                    ctx.stroke();
                }

                // 3. 텍스트 렌더링 (줌인 상태일 때만)
                if (showText) {
                    ctx.save();
                    ctx.translate(px, py);
                    // 텍스트가 뒤집히지 않도록 scale을 역으로 보정
                    ctx.scale(1 / scale, -1 / scale); 
                    ctx.rotate(-Math.PI / 4);
                    
                    // 가독성 보장을 위해 화면상 최소 9px ~ 최대 24px 사이즈로 제한
                    const displayFontSize = Math.max(9, Math.min(24, screenTextSize));
                    
                    ctx.font = `bold ${displayFontSize}px monospace`;
                    ctx.fillStyle = p.is_reshoot ? '#fde047' : '#ffffff';
                    
                    // 점과 텍스트가 겹치지 않게 여백(offset) 부여
                    ctx.fillText(p.id, 12, -4);
                    ctx.restore();
                }
            });

            ctx.restore();
        }

        canvas.addEventListener('mousedown', (e) => {
            if (e.button === 1) {
                e.preventDefault();
                const now = Date.now();
                if (now - lastWheelClickTime < 350) {
                    fitView(true);
                    lastWheelClickTime = 0;
                    return;
                }
                lastWheelClickTime = now;
            }

            isDragging = true;
            startX = e.clientX - panX;
            startY = e.clientY - panY;
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            panX = e.clientX - startX;
            panY = e.clientY - startY;
            draw();
        });

        window.addEventListener('mouseup', () => isDragging = false);

        canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            const mouseX = e.clientX - canvas.getBoundingClientRect().left - canvas.width / 2;
            const mouseY = e.clientY - canvas.getBoundingClientRect().top - canvas.height / 2;

            const zoomFactor = e.deltaY < 0 ? 1.15 : 0.87;
            const newScale = Math.min(Math.max(0.0001, scale * zoomFactor), 50);

            panX -= mouseX * (newScale / scale - 1);
            panY -= mouseY * (newScale / scale - 1);
            scale = newScale;

            draw();
        }, { passive: false });

        window.addEventListener('resize', () => {
            canvas.width = canvas.parentElement.clientWidth;
            canvas.height = canvas.parentElement.clientHeight;
            draw();
        });

        calculateBounds();
        fitView(false);
    }

    function zoomIn() { if (canvas) { scale *= 1.25; fitView_draw(); } }
    function zoomOut() { if (canvas) { scale /= 1.25; fitView_draw(); } }
    function fitView_draw() {
        const evt = new Event('resize');
        window.dispatchEvent(evt);
    }

    function selectActiveIndex(filename) {
        location.href = `index_view.php?prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $date_id; ?>&active_file=${encodeURIComponent(filename)}`;
    }
    </script>
</body>
</html>