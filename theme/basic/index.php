<?php
include_once('./_common.php');

// 비로그인 사용자 차단
if (!$is_member) {
    goto_url(G5_BBS_URL.'/login.php');
}

$g5['title'] = '항공사진촬영 관리 - 프로젝트 목록';

// 1개 / 5개 보기 설정
$page_rows = isset($_GET['rows']) ? (int)$_GET['rows'] : 5;
if (!in_array($page_rows, [1, 5])) $page_rows = 5;

// DB에서 프로젝트 목록 조회
$sql = " SELECT * FROM IMG_PROJECT ORDER BY prj_id DESC LIMIT 0, {$page_rows} ";
$result = sql_query($sql, false);

$projects = [];
if ($result) {
    while ($row = sql_fetch_array($result)) {
        $projects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    <!-- Pretendard & DaisyUI & Tailwind -->
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css" />
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
        .theme-wrapper {
            background: var(--bg-grad);
            transition: background 0.4s ease;
        }
        .glass-panel {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 1.25rem;
        }

        /* 테이블 너비 고정 */
        .table-fixed-layout {
            table-layout: fixed;
            width: 100%;
        }
    </style>
</head>
<body id="main-theme-wrapper" data-theme="dark" class="theme-wrapper relative flex flex-col items-center justify-between p-6 select-none">
    
    <!-- 배경 은은한 오로라 라이트 -->
    <div class="fixed -top-40 -left-40 w-[500px] h-[500px] rounded-full blur-[110px] pointer-events-none z-0" style="background: var(--blob-1);"></div>
    <div class="fixed -bottom-40 -right-40 w-[500px] h-[500px] rounded-full blur-[110px] pointer-events-none z-0" style="background: var(--blob-2);"></div>
    <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] rounded-full blur-[120px] pointer-events-none z-0" style="background: var(--blob-3);"></div>

    <!-- 상단 GNB (z-50으로 설정하여 아래 요소보다 항상 위로 배치) -->
    <div class="w-full max-w-5xl flex justify-between items-center relative z-50">
        <div class="flex items-center gap-2">
            <span class="text-2xl">✈️</span>
            <h1 class="text-xl font-black tracking-tight text-base-content">항공사진촬영 관리</h1>
        </div>
        
        <div class="flex items-center gap-3">
            <!-- 다크/라이트 테마 토글 버튼 -->
            <label class="swap swap-rotate btn btn-sm btn-circle btn-ghost text-base-content/80">
                <input type="checkbox" id="theme-controller" onchange="toggleThemeMode()" />
                <span class="text-base swap-off">☀️</span>
                <span class="text-base swap-on">🌙</span>
            </label>

            <!-- 사용자 정보 & 로그아웃 드롭다운 -->
            <div class="dropdown dropdown-end dropdown-bottom">
                <div tabindex="0" role="button" class="btn btn-sm btn-ghost gap-1.5 px-3 font-bold text-base-content bg-base-100/60 backdrop-blur rounded-xl border border-base-content/10 shadow-sm hover:bg-base-100/90 transition-all">
                    <span>👤</span> <?php echo $member['mb_name'] ? $member['mb_name'] : $member['mb_nick']; ?> 님
                    <span class="text-[10px] opacity-60">▼</span>
                </div>
                <!-- z-[100] 및 강제 상위 레이어 지정 -->
                <ul tabindex="0" class="dropdown-content z-[100] menu p-2 shadow-2xl bg-base-100 rounded-2xl w-36 mt-2 border border-base-content/15 backdrop-blur-md">
                    <li>
                        <a href="<?php echo G5_BBS_URL; ?>/logout.php" class="text-error font-bold justify-center py-2 hover:bg-error/10 rounded-xl transition-colors">
                            <span>🚪</span> 로그아웃
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 메인 패널 (프로젝트 리스트: z-10으로 배치하여 상단 드롭다운에 간섭 없음) -->
    <div class="glass-panel w-full max-w-5xl flex-1 my-4 flex flex-col relative z-10 shadow-2xl p-6 overflow-hidden">
        
        <!-- 툴바 영역 -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                📂 프로젝트 목록
            </h2>
            <div class="flex items-center gap-2">
                <select class="select select-bordered select-sm rounded-xl font-medium text-xs focus:select-primary" onchange="location.href='?rows='+this.value">
                    <option value="5" <?php echo $page_rows == 5 ? 'selected' : ''; ?>>5개씩 보기</option>
                    <option value="1" <?php echo $page_rows == 1 ? 'selected' : ''; ?>>1개씩 보기</option>
                </select>
                <button class="btn btn-primary btn-sm rounded-xl font-bold shadow-md shadow-primary/20 text-xs gap-1" onclick="create_modal.showModal()">
                    <span>+ 만들기</span>
                </button>
            </div>
        </div>

        <!-- 테이블 영역 -->
        <div class="overflow-hidden flex-1 w-full rounded-2xl border border-base-content/10 bg-base-100/40 flex flex-col">
            <table class="table table-fixed-layout w-full text-center">
                <thead class="bg-base-200/50 text-base-content/80 text-xs font-bold border-b border-base-content/10">
                    <tr>
                        <th class="w-[120px] py-3.5 whitespace-nowrap">생성일자</th>
                        <th class="py-3.5 text-left px-6">사업명</th>
                        <th class="w-[110px] py-3.5 whitespace-nowrap">구분</th>
                        <th class="w-[110px] py-3.5 whitespace-nowrap">물량</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-medium divide-y divide-base-content/5">
                    <?php if (!empty($projects)) { ?>
                        <?php foreach ($projects as $row) { 
                            $raw_date = substr($row['created_at'], 0, 10);
                            $name = $row['prj_name'];
                            $display_name = mb_strlen($name, 'utf-8') > 30 
                                          ? mb_substr($name, 0, 30, 'utf-8').'...' 
                                          : $name;
                        ?>
                        <tr class="hover:bg-base-200/50 transition-colors cursor-pointer group" 
    onclick="location.href='<?php echo G5_URL; ?>/project/view.php?id=<?php echo $row['prj_id']; ?>'">
                            <!-- 날짜 -->
                            <td class="text-base-content/70 font-mono text-xs whitespace-nowrap py-3.5">
                                <?php echo $raw_date; ?>
                            </td>
                            
                            <!-- 사업명 -->
                            <td class="text-left px-6 py-3.5 overflow-hidden text-ellipsis whitespace-nowrap">
                                <span class="font-extrabold text-primary text-base group-hover:underline transition-all inline-block max-w-full truncate align-middle" title="<?php echo htmlspecialchars($name); ?>">
                                    <?php echo htmlspecialchars($display_name); ?>
                                </span>
                            </td>
                            
                            <!-- 구분 -->
                            <td class="py-3.5 whitespace-nowrap">
                                <span class="badge badge-ghost border-base-content/15 text-xs font-semibold py-2.5 px-3 rounded-lg whitespace-nowrap inline-flex items-center justify-center">
                                    <?php echo htmlspecialchars($row['prj_type']); ?>
                                </span>
                            </td>
                            
                            <!-- 물량 -->
                            <td class="font-mono font-semibold text-base-content/80 whitespace-nowrap py-3.5">
                                <?php echo number_format($row['prj_volume']); ?>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="4" class="py-24 text-base-content/40 font-bold text-sm">
                                생성된 프로젝트가 없습니다.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 우측 하단 시스템 버전 태그 -->
    <div class="w-full max-w-5xl flex justify-end relative z-10">
        <div class="badge shadow-md px-3 py-2 text-[10px] font-mono border border-base-content/10 bg-base-100/70 backdrop-blur text-base-content/60 rounded-xl">
            🛠️ VER : <strong class="text-primary ml-1">v1.0.0-RELEASE</strong>
        </div>
    </div>

    <!-- 프로젝트 생성 모달 -->
    <dialog id="create_modal" class="modal modal-bottom sm:modal-middle z-[200]">
        <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-md">
            <h3 class="font-black text-lg mb-4 text-base-content flex items-center gap-2">
                ✨ 새 프로젝트 만들기
            </h3>
            
            <form method="post" action="project_insert.php" class="space-y-4">
                <div class="form-control">
                    <label class="label py-1"><span class="label-text font-bold text-xs text-base-content/70">사업명 (폴더명)</span></label>
                    <input type="text" name="prj_name" placeholder="사업명을 입력하세요" class="input input-bordered rounded-xl w-full focus:input-primary font-medium text-sm" required autocomplete="off">
                </div>
                
                <div class="form-control">
                    <label class="label py-1"><span class="label-text font-bold text-xs text-base-content/70">구분</span></label>
                    <select name="prj_type" class="select select-bordered rounded-xl w-full font-medium text-sm">
                        <option value="항공사진촬영">항공사진촬영</option>
                        <option value="정사영상">정사영상</option>
                    </select>
                </div>

                <div class="modal-action mt-6">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="create_modal.close()">취소</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-6">생성하기</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <script>
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            const wrapper = document.getElementById('main-theme-wrapper');
            if (wrapper) wrapper.setAttribute('data-theme', theme);
            localStorage.setItem('app-theme', theme);
            
            const controller = document.getElementById('theme-controller');
            if (controller) {
                controller.checked = (theme !== 'dark');
            }
        }

        function toggleThemeMode() {
            const currentTheme = localStorage.getItem('app-theme') || 'dark';
            const nextTheme = (currentTheme === 'dark') ? 'light' : 'dark';
            applyTheme(nextTheme);
        }

        (function initTheme() {
            const savedTheme = localStorage.getItem('app-theme') || 'dark';
            applyTheme(savedTheme);
        })();
    </script>
</body>
</html>