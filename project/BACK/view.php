<?php
include_once('./_common.php');

if (!$is_member) {
    goto_url(G5_BBS_URL.'/login.php');
}

$prj_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$prj_id) {
    alert('올바른 프로젝트 ID가 아닙니다.', G5_URL.'/index.php');
}

$prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ");
if (!$prj) {
    alert('존재하지 않는 프로젝트입니다.', G5_URL.'/index.php');
}

$g5['title'] = htmlspecialchars($prj['prj_name']) . ' - 프로젝트 관리';
$base_dir = img_project_path($prj['prj_name']);

// 1. KPI 지표 통계 집계 (활성 상태 기준)
$stat_sql = " SELECT 
                IFNULL(SUM(CASE WHEN status = 'ACTIVE' THEN total_shots ELSE 0 END), 0) AS total_shots,
                IFNULL(SUM(CASE WHEN status = 'ACTIVE' THEN used_shots ELSE 0 END), 0) AS used_shots,
                IFNULL(SUM(CASE WHEN status = 'ACTIVE' THEN reshoot_shots ELSE 0 END), 0) AS reshoot_shots,
                MAX(CASE WHEN status = 'ACTIVE' THEN flight_date ELSE NULL END) AS last_flight_date,
                COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) AS active_date_count
              FROM IMG_FLIGHT_DATE 
              WHERE prj_id = {$prj_id} ";
$stats = sql_fetch($stat_sql);

// 2. 탭별 데이터 쿼리
$flight_dates = sql_query(" SELECT * FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} ORDER BY flight_date DESC ");
$blocks = sql_query(" SELECT * FROM IMG_BLOCK WHERE prj_id = {$prj_id} ORDER BY block_id ASC ");
$sec_checks = sql_query(" SELECT * FROM IMG_SECURITY_CHECK WHERE prj_id = {$prj_id} ORDER BY round_no ASC ");
$qa_checks = sql_query(" SELECT * FROM IMG_QA_CHECK WHERE prj_id = {$prj_id} ORDER BY round_no ASC ");
$block_photo_counts = img_block_photo_counts($prj_id, $prj['prj_name']);
$flight_duplicate_video_counts = img_flight_duplicate_video_counts($prj_id, $prj['prj_name']);
$block_metrics = img_block_metrics($prj_id, $prj['prj_name']);

// 3. EO 파싱 블럭 교차 대조용 데이터
$block_rows_for_js = [];
$b_res = sql_query(" SELECT block_name, line_range, line_list FROM IMG_BLOCK WHERE prj_id = {$prj_id} ");
if ($b_res) {
    while($b = sql_fetch_array($b_res)) {
        $block_rows_for_js[] = [
            'name' => $b['block_name'],
            'range' => $b['line_range'],
            'line_list' => implode(',', img_block_course_numbers($b['line_list']))
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    
    <style>
        html, body {
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            font-family: "Pretendard", -apple-system, BlinkMacSystemFont, system-ui, sans-serif !important;
        }
        :root, [data-theme] {
            --rounded-box: 1.25rem !important;
            --rounded-btn: 1rem !important;
            --rounded-badge: 0.75rem !important;
        }
        [data-theme="light"] {
            --bg-grad: linear-gradient(135deg, #eff6ff 0%, #faf5ff 50%, #f0fdf4 100%);
            --blob-1: rgba(99, 102, 241, 0.15);
            --blob-2: rgba(236, 72, 153, 0.12);
            --blob-3: rgba(14, 165, 233, 0.12);
            --glass-bg: rgba(255, 255, 255, 0.88);
            --glass-border: rgba(255, 255, 255, 0.95);
        }
        [data-theme="dark"] {
            --bg-grad: #0b1120;
            --blob-1: rgba(99, 102, 241, 0.20);
            --blob-2: rgba(168, 85, 247, 0.18);
            --blob-3: rgba(56, 189, 248, 0.15);
            --glass-bg: rgba(17, 24, 39, 0.88);
            --glass-border: rgba(255, 255, 255, 0.08);
        }
        .theme-wrapper { background: var(--bg-grad); transition: background 0.4s ease; }
        .glass-panel {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 1.25rem;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(150, 150, 150, 0.25);
            border-radius: 10px;
        }
    </style>
</head>
<body id="main-theme-wrapper" data-theme="dark" class="theme-wrapper relative flex flex-col items-center justify-between p-5 select-none">
    
    <div class="fixed -top-40 -left-40 w-[500px] h-[500px] rounded-full blur-[110px] pointer-events-none z-0" style="background: var(--blob-1);"></div>
    <div class="fixed -bottom-40 -right-40 w-[500px] h-[500px] rounded-full blur-[110px] pointer-events-none z-0" style="background: var(--blob-2);"></div>
    <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] rounded-full blur-[120px] pointer-events-none z-0" style="background: var(--blob-3);"></div>

    <!-- 상단 GNB -->
    <div class="w-full max-w-6xl flex justify-between items-center relative z-50">
        <div class="flex items-center gap-3">
            <a href="<?php echo G5_URL; ?>/index.php" class="btn btn-sm btn-circle btn-ghost text-base-content/80 hover:bg-base-100/60" title="목록으로">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="flex items-center gap-2">
                <span class="text-xl">📁</span>
                <h1 class="text-lg font-black tracking-tight text-base-content truncate max-w-md" title="<?php echo htmlspecialchars($prj['prj_name']); ?>">
                    <?php echo htmlspecialchars($prj['prj_name']); ?>
                </h1>
                <span class="badge badge-primary badge-sm font-bold ml-1"><?php echo htmlspecialchars($prj['prj_type']); ?></span>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <a href="action.php?action=sync_db&prj_id=<?php echo $prj_id; ?>" 
               class="btn btn-sm btn-warning rounded-xl font-black text-xs gap-1.5 shadow-md shadow-warning/20 hover:scale-[1.02] active:scale-[0.98] transition-transform">
                <span>⚡</span> DB 갱신하기
            </a>

            <button class="btn btn-sm btn-ghost bg-base-100/40 rounded-xl font-bold text-xs gap-1 border border-base-content/10 shadow-sm" onclick="modal_rename.showModal()">
                <span>🏷️</span> 이름변경
            </button>
            <button class="btn btn-sm btn-ghost bg-base-100/40 rounded-xl font-bold text-xs gap-1 border border-base-content/10 shadow-sm" onclick="modal_index.showModal()">
                <span>🗺️</span> 전체 인덱스
            </button>

            <label class="swap swap-rotate btn btn-sm btn-circle btn-ghost text-base-content/80">
                <input type="checkbox" id="theme-controller" onchange="toggleThemeMode()" />
                <span class="text-base swap-off">☀️</span>
                <span class="text-base swap-on">🌙</span>
            </label>

            <div class="dropdown dropdown-end dropdown-bottom">
                <div tabindex="0" role="button" class="btn btn-sm btn-ghost gap-1.5 px-3 font-bold text-base-content bg-base-100/60 backdrop-blur rounded-xl border border-base-content/10 shadow-sm">
                    <span>👤</span> <?php echo $member['mb_name'] ? $member['mb_name'] : $member['mb_nick']; ?> 님
                    <span class="text-[10px] opacity-60">▼</span>
                </div>
                <ul tabindex="0" class="dropdown-content z-[100] menu p-2 shadow-2xl bg-base-100 rounded-2xl w-36 mt-2 border border-base-content/15 backdrop-blur-md">
                    <li><a href="<?php echo G5_BBS_URL; ?>/logout.php" class="text-error font-bold justify-center py-2 hover:bg-error/10 rounded-xl"><span>🚪</span> 로그아웃</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 대시보드 메인 패널 -->
    <div class="glass-panel w-full max-w-6xl flex-1 my-3 flex flex-col relative z-10 shadow-2xl p-5 overflow-hidden">
        
        <!-- KPI 스탯 -->
        <div class="grid grid-cols-4 gap-4 mb-4">
            <div class="bg-base-100/60 border border-base-content/10 rounded-2xl p-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-bold text-base-content/60">등록전체 매수</p>
                    <h3 class="text-2xl font-black text-primary font-mono mt-0.5"><?php echo number_format($stats['total_shots']); ?><span class="text-xs font-normal text-base-content/60 ml-1">장</span></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary text-lg">📸</div>
            </div>
            
            <div class="bg-base-100/60 border border-base-content/10 rounded-2xl p-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-bold text-base-content/60">사용 매수 (정상)</p>
                    <h3 class="text-2xl font-black text-success font-mono mt-0.5"><?php echo number_format($stats['used_shots']); ?><span class="text-xs font-normal text-base-content/60 ml-1">장</span></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-success/10 flex items-center justify-center text-success text-lg">✅</div>
            </div>

            <div class="bg-base-100/60 border border-base-content/10 rounded-2xl p-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-bold text-base-content/60">재촬영 매수 (결손)</p>
                    <h3 class="text-2xl font-black text-warning font-mono mt-0.5"><?php echo number_format($stats['reshoot_shots']); ?><span class="text-xs font-normal text-base-content/60 ml-1">장</span></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-warning/10 flex items-center justify-center text-warning text-lg">🔄</div>
            </div>

            <div class="bg-base-100/60 border border-base-content/10 rounded-2xl p-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-xs font-bold text-base-content/60">최근 촬영일 / 일수</p>
                    <h3 class="text-lg font-black text-base-content font-mono mt-1">
                        <?php echo $stats['last_flight_date'] ? $stats['last_flight_date'] : '-'; ?>
                        <span class="text-xs font-bold text-info ml-1">(<?php echo (int)$stats['active_date_count']; ?>일차)</span>
                    </h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-info/10 flex items-center justify-center text-info text-lg">🗓️</div>
            </div>
        </div>

        <!-- 탭 네비게이션 & 컨텐츠 -->
        <div class="flex-1 flex flex-col min-h-0 bg-base-100/40 rounded-2xl border border-base-content/10 p-4">
            <div class="flex justify-between items-center border-b border-base-content/10 pb-3 mb-3">
    <div class="flex items-center gap-3">
        <div role="tablist" class="tabs tabs-boxed bg-base-200/50 p-1 rounded-xl">
            <button role="tab" class="tab font-bold text-xs" onclick="switchTab('tab-flight', this)">🛫 촬영일자</button>
            <button role="tab" class="tab font-bold text-xs" onclick="switchTab('tab-block', this)">🧩 블럭 관리</button>
            <button role="tab" class="tab font-bold text-xs" onclick="switchTab('tab-security', this)">🛡️ 보안성검토</button>
            <button role="tab" class="tab font-bold text-xs" onclick="switchTab('tab-qa', this)">🔬 품질검수</button>
        </div>

        <!-- 📏 행 높이(밀도) 조절 세그먼트 버튼 -->
        <div class="join bg-base-200/60 p-0.5 rounded-xl border border-base-content/10">
            <button type="button" class="btn btn-xs join-item density-btn font-bold text-[11px]" data-size="table-xs" onclick="setTableDensity('table-xs', this)">축소</button>
            <button type="button" class="btn btn-xs join-item density-btn font-bold text-[11px]" data-size="table-sm" onclick="setTableDensity('table-sm', this)">기본</button>
            <button type="button" class="btn btn-xs join-item density-btn font-bold text-[11px]" data-size="table-md" onclick="setTableDensity('table-md', this)">확대</button>
        </div>
    </div>
    
    <div id="tab-action-container"></div>
</div>

            <!-- 서브 뷰 컴포넌트들 -->
            <?php 
                include_once('./views/tab_flight.php');
                include_once('./views/tab_block.php');
                include_once('./views/tab_security.php');
                include_once('./views/tab_qa.php');
            ?>
        </div>
    </div>

    <!-- 하단 버전 태그 -->
    <div class="w-full max-w-6xl flex justify-end relative z-10">
        <div class="badge shadow-md px-3 py-2 text-[10px] font-mono border border-base-content/10 bg-base-100/70 backdrop-blur text-base-content/60 rounded-xl">
            🛠️ VER : <strong class="text-primary ml-1">v1.0.0-RELEASE</strong>
        </div>
    </div>

    <script>
    var PROJECT_BLOCKS = <?php echo json_encode($block_rows_for_js); ?> || [];
    </script>

    <!-- 모달 컴포넌트들 -->
    <?php
        include_once('./modals/modal_flight.php');
        include_once('./modals/modal_block.php');
        include_once('./modals/modal_security.php');
        include_once('./modals/modal_qa.php');
        include_once('./modals/modal_tools.php');
    ?>

    <div id="toast-container" class="toast toast-top toast-end z-[300] p-4 pointer-events-none"></div>

    <script>
        function triggerToast(message, type = 'success', emoji = '✨') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const alertClass = (type === 'error') ? 'alert-error' : (type === 'warning' ? 'alert-warning' : 'alert-success');
            const toast = document.createElement('div');
            toast.className = `alert ${alertClass} shadow-2xl rounded-2xl py-3 px-4 text-xs font-bold flex items-center gap-2 border border-base-content/10 backdrop-blur pointer-events-auto transition-all duration-300 transform translate-y-3 opacity-0`;
            toast.innerHTML = `<span class="text-base">${emoji}</span><span class="text-base-content font-bold">${message}</span>`;

            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-y-3', 'opacity-0'));
            setTimeout(() => {
                toast.classList.add('opacity-0', '-translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function toggleAllCheckboxes(master, targetName) {
            document.querySelectorAll(`input[name="${targetName}[]"]`).forEach(chk => chk.checked = master.checked);
        }

        function confirmBlockDelete() {
            const checkedItems = document.querySelectorAll('.chk-block-item:checked');
            if (checkedItems.length === 0) {
                triggerToast('삭제할 블럭을 먼저 선택해주세요.', 'warning', '⚠️');
                return;
            }
            const names = Array.from(checkedItems).map(i => i.dataset.name).join(', ');
            document.getElementById('delete_target_list').innerText = `대상: ${names}`;
            modal_confirm_delete_block.showModal();
        }

        function executeBlockDelete() {
            document.getElementById('form_block_delete').submit();
        }

        function switchTab(tabId, el) {
            document.querySelectorAll('.tab-content-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('tab-active'));
            
            const targetPanel = document.getElementById(tabId);
            if (targetPanel) targetPanel.classList.remove('hidden');
            if (el) el.classList.add('tab-active');

            const container = document.getElementById('tab-action-container');
            if (!container) return;

            if (tabId === 'tab-flight') {
                container.innerHTML = `
                    <div class="flex items-center gap-1.5">
                        <button class="btn btn-xs btn-primary rounded-lg font-bold shadow-md shadow-primary/20" onclick="modal_add_flight.showModal()">+ 촬영일 등록</button>
                        <button class="btn btn-xs btn-error rounded-lg font-bold shadow-md shadow-error/20" onclick="confirmFlightDelete()">🗑️ 촬영일 삭제</button>
                    </div>`;
            } else if (tabId === 'tab-block') {
                container.innerHTML = `
                    <div class="flex items-center gap-1.5">
                        <button class="btn btn-xs btn-outline btn-primary rounded-lg font-bold" onclick="modal_add_block_single.showModal()">+ 수동 등록</button>
                        <button class="btn btn-xs btn-primary rounded-lg font-bold shadow-md shadow-primary/20" onclick="modal_add_block_bulk.showModal()">📄 텍스트 일괄 등록</button>
                        <button class="btn btn-xs btn-error rounded-lg font-bold shadow-md shadow-error/20" onclick="confirmBlockDelete()">🗑️ 블럭 삭제</button>
                    </div>`;
            } else if (tabId === 'tab-security') {
                container.innerHTML = `<button class="btn btn-xs btn-primary rounded-lg font-bold" onclick="modal_add_sec.showModal()">+ 보안성검토 등록</button>`;
            } else if (tabId === 'tab-qa') {
                container.innerHTML = `<button class="btn btn-xs btn-primary rounded-lg font-bold" onclick="modal_add_qa.showModal()">+ 품질검수 등록</button>`;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'tab-flight';
            const tabBtn = document.querySelector(`[onclick*="${activeTab}"]`) || document.querySelector('.tab');
            switchTab(activeTab, tabBtn);

            const toastKey = urlParams.get('toast');
            const errMsg   = urlParams.get('err_msg') || '';
            const cnt      = urlParams.get('cnt') || '';
            const val      = urlParams.get('val') || '';

            if (toastKey) {
                if (toastKey === 'error') triggerToast(decodeURIComponent(errMsg), 'error', '⚠️');
                else if (toastKey === 'block_single_ok') triggerToast(`[${val}] 블럭 DB 및 폴더가 생성되었습니다.`, 'success', '🧩');
                else if (toastKey === 'block_bulk_ok') triggerToast(`총 ${cnt}개 블럭 DB 및 폴더가 일괄 등록되었습니다.`, 'success', '🚀');
                else if (toastKey === 'block_index_ok') triggerToast(`[${val}] 블럭 INDEX가 생성되었습니다.`, 'success', '🗺️');
                else if (toastKey === 'block_delete_ok') triggerToast(`선택한 ${cnt}개 블럭과 폴더가 삭제되었습니다.`, 'warning', '🗑️');
                else if (toastKey === 'flight_date_ok') triggerToast(`[${val}] 촬영일 폴더 및 DB가 등록되었습니다.`, 'success', '🛫');
                else if (toastKey === 'flight_delete_ok') triggerToast(`선택한 ${cnt}개 촬영일과 실제 폴더가 완전히 삭제되었습니다.`, 'warning', '🗑️');
                else if (toastKey === 'status_active') triggerToast(`[${val}] 촬영일이 '활성' 상태로 전환되었습니다.`, 'success', '🟢');
                else if (toastKey === 'status_inactive') triggerToast(`[${val}] 촬영일이 '비활성' 상태로 제외되었습니다.`, 'warning', '🔴');
                else if (toastKey === 'inspect_ok') triggerToast(val ? `[${val}] 검수완료 EO가 생성되어 활성화되었습니다.` : '검수 내역이 성공적으로 저장되었습니다.', 'success', '✍️');
                else if (toastKey === 'sync_ok') triggerToast('파일 시스템 데이터를 스캔하여 DB 캐시를 갱신했습니다.', 'success', '⚡');
				else if (toastKey === 'eo_applied_ok') {triggerToast(`[${val}] 성과 파일이 활성화되어 DB에 매수 및 블럭이 반영되었습니다.`, 'success', '🧭');}

                const cleanUrl = window.location.pathname + '?id=' + (urlParams.get('id') || '') + '&tab=' + activeTab;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        });

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            const wrapper = document.getElementById('main-theme-wrapper');
            if (wrapper) wrapper.setAttribute('data-theme', theme);
            localStorage.setItem('app-theme', theme);
            const controller = document.getElementById('theme-controller');
            if (controller) controller.checked = (theme !== 'dark');
        }

        function toggleThemeMode() {
            const currentTheme = localStorage.getItem('app-theme') || 'dark';
            applyTheme((currentTheme === 'dark') ? 'light' : 'dark');
        }

        (function initTheme() {
            applyTheme(localStorage.getItem('app-theme') || 'dark');
        })();
        function setTableDensity(densityClass, btnElement) {
    // 모든 동적 테이블에 클래스 교체 적용
    const tables = document.querySelectorAll('.dynamic-density-table');
    tables.forEach(tbl => {
        tbl.classList.remove('table-xs', 'table-sm', 'table-md', 'table-lg');
        tbl.classList.add(densityClass);
    });

    // 버튼 활성 스타일 업데이트
    document.querySelectorAll('.density-btn').forEach(b => {
        b.classList.remove('btn-primary', 'text-white');
        b.classList.add('btn-ghost');
    });

    if (btnElement) {
        btnElement.classList.remove('btn-ghost');
        btnElement.classList.add('btn-primary', 'text-white');
    }

    // 사용자 로컬 스토리지에 저장 (새로고침 시 유지)
    localStorage.setItem('app-table-density', densityClass);
}

// 페이지 로드 시 저장된 행 크기 적용
document.addEventListener('DOMContentLoaded', () => {
    const savedDensity = localStorage.getItem('app-table-density') || 'table-sm';
    const targetBtn = document.querySelector(`.density-btn[data-size="${savedDensity}"]`) || document.querySelectorAll('.density-btn')[1];
    setTableDensity(savedDensity, targetBtn);
});
    </script>
</body>
</html>
