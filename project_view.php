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

// KPI 집계
$stat_sql = " SELECT 
                IFNULL(SUM(CASE WHEN status = 'ACTIVE' THEN total_shots ELSE 0 END), 0) AS total_shots,
                IFNULL(SUM(CASE WHEN status = 'ACTIVE' THEN used_shots ELSE 0 END), 0) AS used_shots,
                IFNULL(SUM(CASE WHEN status = 'ACTIVE' THEN reshoot_shots ELSE 0 END), 0) AS reshoot_shots,
                MAX(CASE WHEN status = 'ACTIVE' THEN flight_date ELSE NULL END) AS last_flight_date,
                COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) AS active_date_count
              FROM IMG_FLIGHT_DATE 
              WHERE prj_id = {$prj_id} ";
$stats = sql_fetch($stat_sql);

// 탭 데이터 조회
$flight_dates = sql_query(" SELECT * FROM IMG_FLIGHT_DATE WHERE prj_id = {$prj_id} ORDER BY flight_date DESC ");
$blocks = sql_query(" SELECT * FROM IMG_BLOCK WHERE prj_id = {$prj_id} ORDER BY block_id ASC ");
$sec_checks = sql_query(" SELECT * FROM IMG_SECURITY_CHECK WHERE prj_id = {$prj_id} ORDER BY round_no ASC ");
$qa_checks = sql_query(" SELECT * FROM IMG_QA_CHECK WHERE prj_id = {$prj_id} ORDER BY round_no ASC ");
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
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(150, 150, 150, 0.2);
            border-radius: 10px;
        }
    </style>
</head>
<body id="main-theme-wrapper" data-theme="dark" class="theme-wrapper relative flex flex-col items-center justify-between p-5 select-none">
    
    <!-- 배경 조명 -->
    <div class="fixed -top-40 -left-40 w-[500px] h-[500px] rounded-full blur-[110px] pointer-events-none z-0" style="background: var(--blob-1);"></div>
    <div class="fixed -bottom-40 -right-40 w-[500px] h-[500px] rounded-full blur-[110px] pointer-events-none z-0" style="background: var(--blob-2);"></div>
    <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] rounded-full blur-[120px] pointer-events-none z-0" style="background: var(--blob-3);"></div>

    <!-- 상단 헤더 / GNB -->
    <div class="w-full max-w-6xl flex justify-between items-center relative z-50">
        <div class="flex items-center gap-3">
            <a href="index.php" class="btn btn-sm btn-circle btn-ghost text-base-content/80 hover:bg-base-100/60" title="목록으로">
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
            <a href="project_action.php?action=sync_db&prj_id=<?php echo $prj_id; ?>" 
               class="btn btn-sm btn-warning rounded-xl font-black text-xs gap-1.5 shadow-md shadow-warning/20">
                <span>⚡</span> DB 갱신하기
            </a>

            <!-- 테마 토글 -->
            <label class="swap swap-rotate btn btn-sm btn-circle btn-ghost text-base-content/80">
                <input type="checkbox" id="theme-controller" onchange="toggleThemeMode()" />
                <span class="text-base swap-off">☀️</span>
                <span class="text-base swap-on">🌙</span>
            </label>

            <!-- 사용자 메뉴 -->
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

    <!-- 메인 대시보드 패널 -->
    <div class="glass-panel w-full max-w-6xl flex-1 my-3 flex flex-col relative z-10 shadow-2xl p-5 overflow-hidden">
        
        <!-- KPI 스탯 카드 -->
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
                <div role="tablist" class="tabs tabs-boxed bg-base-200/50 p-1 rounded-xl">
                    <button role="tab" class="tab font-bold text-xs" onclick="switchTab('tab-flight', this)">🛫 촬영일자</button>
                    <button role="tab" class="tab font-bold text-xs" onclick="switchTab('tab-block', this)">🧩 블럭 관리</button>
                    <button role="tab" class="tab font-bold text-xs" onclick="switchTab('tab-security', this)">🛡️ 보안성검토</button>
                    <button role="tab" class="tab font-bold text-xs" onclick="switchTab('tab-qa', this)">🔬 품질검수</button>
                </div>
                <div id="tab-action-container"></div>
            </div>

            <!-- 탭 1: 촬영일자 -->
            <div id="tab-flight" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar hidden">
                <table class="table table-fixed w-full text-center">
                    <thead class="bg-base-200/50 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
                        <tr>
                            <th class="w-16"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded" onclick="toggleAllCheckboxes(this, 'chk_flight')"></th>
                            <th class="w-32">촬영일자</th>
                            <th>전체매수</th>
                            <th>사용매수</th>
                            <th>재촬영매수</th>
                            <th class="w-28">상태</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs divide-y divide-base-content/5">
                        <?php 
                        $has_dates = false;
                        while($row = sql_fetch_array($flight_dates)) { 
                            $has_dates = true;
                        ?>
                        <tr class="hover:bg-base-200/40">
                            <td><input type="checkbox" name="chk_flight[]" value="<?php echo $row['date_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded"></td>
                            <td class="font-mono font-bold"><?php echo $row['flight_date']; ?></td>
                            <td class="font-mono"><?php echo number_format($row['total_shots']); ?></td>
                            <td class="font-mono text-success"><?php echo number_format($row['used_shots']); ?></td>
                            <td class="font-mono text-warning"><?php echo number_format($row['reshoot_shots']); ?></td>
                            <td>
                                <span class="badge <?php echo $row['status'] == 'ACTIVE' ? 'badge-success' : 'badge-ghost'; ?> badge-xs font-semibold py-2 px-2 rounded-lg">
                                    <?php echo $row['status'] == 'ACTIVE' ? '활성' : '비활성'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php } if(!$has_dates) { ?>
                        <tr><td colspan="6" class="py-16 text-base-content/40 font-bold">등록된 촬영일자가 없습니다.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- 탭 2: 블럭 관리 (업그레이드 완료) -->
            <div id="tab-block" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar hidden">
                <form id="form_block_delete" method="post" action="project_action.php">
                    <input type="hidden" name="action" value="delete_blocks">
                    <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
                    
                    <table class="table table-fixed w-full text-center">
                        <thead class="bg-base-200/50 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
                            <tr>
                                <th class="w-16"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded" onclick="toggleAllCheckboxes(this, 'block_ids')"></th>
                                <th class="w-28 text-left px-4">블럭명</th>
                                <th class="w-40 text-left px-4">포함 코스 (범위)</th>
                                <th class="w-24">코스 수</th>
                                <th class="w-24">사진 매수</th>
                                <th class="w-28">등록자</th>
                                <th class="w-32">등록일자</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs divide-y divide-base-content/5">
                            <?php 
                            $has_blocks = false;
                            while($row = sql_fetch_array($blocks)) { 
                                $has_blocks = true;
                            ?>
                            <tr class="hover:bg-base-200/40">
                                <td><input type="checkbox" name="block_ids[]" value="<?php echo $row['block_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded chk-block-item" data-name="<?php echo htmlspecialchars($row['block_name']); ?>"></td>
                                <td class="text-left px-4 font-bold text-primary font-mono text-sm">
                                    <?php echo htmlspecialchars($row['block_name']); ?>
                                </td>
                                <td class="text-left px-4 font-mono font-medium text-base-content/80 truncate" title="<?php echo htmlspecialchars($row['line_list']); ?>코스">
                                    <span class="badge badge-neutral badge-sm rounded-lg font-mono"><?php echo htmlspecialchars($row['line_range']); ?></span>
                                </td>
                                <td class="font-mono font-bold"><?php echo number_format($row['line_count']); ?> 코스</td>
                                <td class="font-mono text-base-content/70"><?php echo number_format($row['photo_count']); ?> 장</td>
                                <td class="font-medium text-base-content/80">👤 <?php echo htmlspecialchars($row['mb_name']); ?></td>
                                <td class="font-mono text-base-content/60 text-[11px]"><?php echo substr($row['created_at'], 0, 10); ?></td>
                            </tr>
                            <?php } if(!$has_blocks) { ?>
                            <tr><td colspan="7" class="py-20 text-base-content/40 font-bold text-sm">등록된 블럭 DB가 없습니다. 상단의 [+ 등록] 버튼을 눌러 추가해주세요.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </form>
            </div>

            <!-- 탭 3: 보안성검토 -->
            <div id="tab-security" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar hidden">
                <table class="table table-fixed w-full text-center">
                    <thead class="bg-base-200/50 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
                        <tr>
                            <th class="w-16"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded"></th>
                            <th class="w-24">차수</th>
                            <th>검토 신청일</th>
                            <th>결과 상태</th>
                            <th>비고</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs divide-y divide-base-content/5">
                        <?php 
                        $has_sec = false;
                        while($row = sql_fetch_array($sec_checks)) { 
                            $has_sec = true;
                        ?>
                        <tr class="hover:bg-base-200/40">
                            <td><input type="checkbox" name="chk_sec[]" value="<?php echo $row['sec_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded"></td>
                            <td class="font-bold font-mono"><?php echo $row['round_no']; ?>차</td>
                            <td class="font-mono"><?php echo $row['check_date']; ?></td>
                            <td>
                                <span class="badge <?php echo $row['result_status'] == '승인' ? 'badge-success' : ($row['result_status'] == '보완' ? 'badge-error' : 'badge-info'); ?> badge-xs py-2 px-2.5 rounded-lg">
                                    <?php echo $row['result_status']; ?>
                                </span>
                            </td>
                            <td class="text-left px-4 text-base-content/70"><?php echo htmlspecialchars($row['remarks']); ?></td>
                        </tr>
                        <?php } if(!$has_sec) { ?>
                        <tr><td colspan="5" class="py-16 text-base-content/40 font-bold">등록된 보안성검토 차수가 없습니다.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- 탭 4: 품질검수 -->
            <div id="tab-qa" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar hidden">
                <table class="table table-fixed w-full text-center">
                    <thead class="bg-base-200/50 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
                        <tr>
                            <th class="w-16"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded"></th>
                            <th class="w-24">차수</th>
                            <th>검수일자</th>
                            <th>합격률(%)</th>
                            <th>최종상태</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs divide-y divide-base-content/5">
                        <?php 
                        $has_qa = false;
                        while($row = sql_fetch_array($qa_checks)) { 
                            $has_qa = true;
                        ?>
                        <tr class="hover:bg-base-200/40">
                            <td><input type="checkbox" name="chk_qa[]" value="<?php echo $row['qa_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded"></td>
                            <td class="font-bold font-mono"><?php echo $row['round_no']; ?>차</td>
                            <td class="font-mono"><?php echo $row['qa_date']; ?></td>
                            <td class="font-mono font-bold text-primary"><?php echo $row['pass_rate']; ?>%</td>
                            <td>
                                <span class="badge <?php echo $row['qa_status'] == '합격' ? 'badge-success' : ($row['qa_status'] == '불합격' ? 'badge-error' : 'badge-warning'); ?> badge-xs py-2 px-2.5 rounded-lg">
                                    <?php echo $row['qa_status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php } if(!$has_qa) { ?>
                        <tr><td colspan="5" class="py-16 text-base-content/40 font-bold">등록된 품질검수 차수가 없습니다.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- 버전 태그 -->
    <div class="w-full max-w-6xl flex justify-end relative z-10">
        <div class="badge shadow-md px-3 py-2 text-[10px] font-mono border border-base-content/10 bg-base-100/70 backdrop-blur text-base-content/60 rounded-xl">
            🛠️ VER : <strong class="text-primary ml-1">v1.0.0-RELEASE</strong>
        </div>
    </div>

    <!-- ================= MODALS ================= -->

    <!-- 모달 1: 블럭 수동 단일 등록 -->
    <dialog id="modal_add_block_single" class="modal z-[200]">
        <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-sm">
            <h3 class="font-black text-lg mb-2 text-base-content flex items-center gap-2">🧩 블럭 수동 등록</h3>
            <p class="text-xs text-base-content/60 mb-4">블럭명과 시작/종료 코스를 입력하면 폴더와 DB가 생성됩니다.</p>
            
            <form method="post" action="project_action.php" class="space-y-4">
                <input type="hidden" name="action" value="add_block_single">
                <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
                
                <div class="form-control">
                    <label class="label py-1"><span class="label-text font-bold text-xs">블럭명</span></label>
                    <input type="text" name="block_name" placeholder="예: 1BL 또는 Block_A" class="input input-bordered rounded-xl w-full text-sm font-medium" required>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="form-control">
                        <label class="label py-1"><span class="label-text font-bold text-xs">시작 코스</span></label>
                        <input type="number" name="start_line" min="1" placeholder="예: 1" class="input input-bordered rounded-xl w-full text-sm font-medium" required>
                    </div>
                    <div class="form-control">
                        <label class="label py-1"><span class="label-text font-bold text-xs">종료 코스</span></label>
                        <input type="number" name="end_line" min="1" placeholder="예: 12" class="input input-bordered rounded-xl w-full text-sm font-medium" required>
                    </div>
                </div>

                <div class="modal-action mt-6">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_add_block_single.close()">취소</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-5">등록 및 폴더 생성</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <!-- 모달 2: 블럭 텍스트 일괄 등록 -->
    <dialog id="modal_add_block_bulk" class="modal z-[200]">
        <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-lg">
            <h3 class="font-black text-lg mb-2 text-base-content flex items-center gap-2">📄 블럭 텍스트 일괄 등록</h3>
            <div class="alert bg-base-200/70 border-none rounded-xl py-2 px-3 text-xs mb-3">
                <span>💡 <code>블럭명 [탭/공백] 코스번호</code> 형식으로 복사해 넣으세요. 코스 범위와 개수가 자동 분석됩니다.</span>
            </div>
            
            <form method="post" action="project_action.php" class="space-y-3">
                <input type="hidden" name="action" value="add_block_bulk">
                <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
                
                <div class="form-control">
                    <textarea name="bulk_text" rows="10" placeholder="1BL	1&#10;1BL	2&#10;3BL	13&#10;3BL	14..." class="textarea textarea-bordered rounded-xl font-mono text-xs leading-relaxed focus:textarea-primary" required></textarea>
                </div>

                <div class="modal-action mt-4">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_add_block_bulk.close()">취소</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-6">일괄 등록 실행 🚀</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <!-- 모달 3: 블럭 삭제 확인 경고 모달 -->
    <dialog id="modal_confirm_delete_block" class="modal z-[210]">
        <div class="modal-box bg-base-100 border border-error/20 shadow-2xl rounded-2xl max-w-sm text-center">
            <div class="w-14 h-14 rounded-full bg-error/10 text-error flex items-center justify-center text-2xl mx-auto mb-3">
                ⚠️
            </div>
            <h3 class="font-black text-lg text-base-content">선택한 블럭을 정말 삭제합니까?</h3>
            <div class="text-xs text-base-content/70 mt-2 space-y-1">
                <p>DB 기록뿐만 아니라 <strong class="text-error font-bold">E 드라이브의 실제 폴더(EO, INDEX, 문서)</strong>까지 완전히 영구 삭제됩니다.</p>
                <div id="delete_target_list" class="badge badge-ghost mt-2 py-2 px-3 font-mono text-xs"></div>
            </div>
            <div class="modal-action justify-center gap-2 mt-6">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_confirm_delete_block.close()">취소</button>
                <button type="button" class="btn btn-error btn-sm rounded-xl font-bold px-6 text-white" onclick="executeBlockDelete()">영구 삭제</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <!-- 모달 4: 촬영일 등록 모달 -->
    <dialog id="modal_add_flight" class="modal z-[200]">
        <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-sm">
            <h3 class="font-black text-lg mb-4 text-base-content flex items-center gap-2">🛫 새 촬영일 등록</h3>
            <form method="post" action="project_action.php" class="space-y-4">
                <input type="hidden" name="action" value="add_flight_date">
                <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
                <div class="form-control">
                    <label class="label py-1"><span class="label-text font-bold text-xs">촬영일자</span></label>
                    <input type="date" name="flight_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered rounded-xl w-full text-sm font-medium" required>
                </div>
                <div class="modal-action mt-6">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl" onclick="modal_add_flight.close()">취소</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-5">폴더 생성 및 등록</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
        // 전체 선택 체크박스
        function toggleAllCheckboxes(master, targetName) {
            document.querySelectorAll(`input[name="${targetName}[]"]`).forEach(chk => chk.checked = master.checked);
        }

        // 블럭 삭제 확인 모달 열기
        function confirmBlockDelete() {
            const checkedItems = document.querySelectorAll('.chk-block-item:checked');
            if (checkedItems.length === 0) {
                alert('삭제할 블럭을 먼저 선택해주세요.');
                return;
            }
            const names = Array.from(checkedItems).map(i => i.dataset.name).join(', ');
            document.getElementById('delete_target_list').innerText = `대상: ${names}`;
            modal_confirm_delete_block.showModal();
        }

        // 실제 삭제 Form Submit 실행
        function executeBlockDelete() {
            document.getElementById('form_block_delete').submit();
        }

        // 탭 전환 함수
        function switchTab(tabId, el) {
            document.querySelectorAll('.tab-content-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('tab-active'));
            
            document.getElementById(tabId).classList.remove('hidden');
            if (el) el.classList.add('tab-active');

            const container = document.getElementById('tab-action-container');
            if (tabId === 'tab-flight') {
                container.innerHTML = `
                    <div class="flex items-center gap-1.5">
                        <button class="btn btn-xs btn-primary rounded-lg font-bold" onclick="modal_add_flight.showModal()">+ 촬영일 등록</button>
                        <button class="btn btn-xs btn-warning rounded-lg font-bold">비활성화</button>
                        <button class="btn btn-xs btn-error rounded-lg font-bold">삭제</button>
                    </div>`;
            } else if (tabId === 'tab-block') {
                container.innerHTML = `
                    <div class="flex items-center gap-1.5">
                        <button class="btn btn-xs btn-outline btn-primary rounded-lg font-bold" onclick="modal_add_block_single.showModal()">+ 수동 등록</button>
                        <button class="btn btn-xs btn-primary rounded-lg font-bold shadow-md shadow-primary/20" onclick="modal_add_block_bulk.showModal()">📄 텍스트 일괄 등록</button>
                        <button class="btn btn-xs btn-error rounded-lg font-bold shadow-md shadow-error/20" onclick="confirmBlockDelete()">🗑️ 블럭 삭제</button>
                    </div>`;
            } else if (tabId === 'tab-security') {
                container.innerHTML = `
                    <div class="flex items-center gap-1.5">
                        <button class="btn btn-xs btn-primary rounded-lg font-bold">+ 보안성검토 등록</button>
                        <button class="btn btn-xs btn-error rounded-lg font-bold">차수 삭제</button>
                    </div>`;
            } else if (tabId === 'tab-qa') {
                container.innerHTML = `
                    <div class="flex items-center gap-1.5">
                        <button class="btn btn-xs btn-primary rounded-lg font-bold">+ 품질검수 등록</button>
                        <button class="btn btn-xs btn-error rounded-lg font-bold">차수 삭제</button>
                    </div>`;
            }
        }

        // project_view.php 하단 스크립트 블록
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const toastKey = urlParams.get('toast');
    const errMsg   = urlParams.get('err_msg') || '';
    const cnt      = urlParams.get('cnt') || '';
    const val      = urlParams.get('val') || '';

    if (toastKey) {
        if (toastKey === 'error') {
            // 실패/경고 토스트 (빨간색)
            triggerToast(decodeURIComponent(errMsg), 'error', '⚠️');
        } else if (toastKey === 'block_single_ok') {
            triggerToast(`[${val}] 블럭 DB 및 폴더가 생성되었습니다.`, 'success', '🧩');
        } else if (toastKey === 'block_bulk_ok') {
            triggerToast(`총 ${cnt}개 블럭 DB 및 폴더가 일괄 등록되었습니다.`, 'success', '🚀');
        } else if (toastKey === 'block_delete_ok') {
            triggerToast(`선택한 ${cnt}개 블럭과 실제 폴더가 삭제되었습니다.`, 'warning', '🗑️');
        } else if (toastKey === 'flight_date_ok') {
            triggerToast(`[${val}] 촬영일 폴더 및 DB가 등록되었습니다.`, 'success', '🛫');
        } else if (toastKey === 'sync_ok') {
            triggerToast('파일 시스템 데이터를 스캔하여 DB 캐시를 갱신했습니다.', 'success', '⚡');
        }

        // 주소창 정리 (새로고침 시 토스트 재발생 방지)
        const currentTab = urlParams.get('tab') || 'tab-block';
        const cleanUrl = window.location.pathname + '?id=' + (urlParams.get('id') || '') + '&tab=' + currentTab;
        window.history.replaceState({}, document.title, cleanUrl);
    }
});
    </script>
</body>
</html>