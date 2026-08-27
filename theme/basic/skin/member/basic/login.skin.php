<?php
if (!defined('_GNUBOARD_')) exit;
?>

<!-- DaisyUI & Tailwind CDN 및 Pretendard 웹폰트 -->
<link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css" />
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css" rel="stylesheet" type="text/css" />

<style>
  /* 상단 빈 영역을 만드는 그누보드 기본 태그들을 DOM 흐름에서 강제 배제 */
  #hd, #hd_pop, #hd_login_msg, #skip_to_container, .sound_only, #wrapper {
    display: none !important;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  
  html, body {
    width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    background-color: #0b1120 !important;
    font-family: "Pretendard", -apple-system, BlinkMacSystemFont, system-ui, sans-serif !important;
  }

  :root, [data-theme] {
    --rounded-box: 1.25rem !important;
    --rounded-btn: 1rem !important;
    --rounded-badge: 1rem !important;
  }

  [data-theme="light"] {
    --login-bg: linear-gradient(135deg, #eff6ff 0%, #faf5ff 50%, #f0fdf4 100%);
    --card-bg: rgba(255, 255, 255, 0.88);
    --card-border: rgba(255, 255, 255, 0.95);
    --card-shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.12), 0 0 0 1px rgba(226, 232, 240, 0.7);
    --blob-1: rgba(99, 102, 241, 0.15);
    --blob-2: rgba(236, 72, 153, 0.12);
    --blob-3: rgba(14, 165, 233, 0.12);
  }

  [data-theme="dark"] {
    --login-bg: #0b1120;
    --card-bg: rgba(17, 24, 39, 0.88);
    --card-border: rgba(255, 255, 255, 0.08);
    --card-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.05);
    --blob-1: rgba(99, 102, 241, 0.20);
    --blob-2: rgba(168, 85, 247, 0.18);
    --blob-3: rgba(56, 189, 248, 0.15);
  }

  .login-wrapper {
    background: var(--login-bg);
    transition: background 0.4s ease;
  }

  .fixed-round-card {
    background: var(--card-bg) !important;
    border: 1px solid var(--card-border) !important;
    box-shadow: var(--card-shadow) !important;
    backdrop-filter: blur(24px) !important;
    -webkit-backdrop-filter: blur(24px) !important;
    border-radius: 1.25rem !important;
  }

  .fixed-round-input, .fixed-round-btn {
    border-radius: 0.875rem !important;
  }
</style>

<!-- relative 대신 fixed inset-0 z-50 적용: 상단 요소 간섭 원천 차단 -->
<div id="main-theme-wrapper" data-theme="dark" class="login-wrapper fixed inset-0 z-50 flex items-center justify-center p-4 select-none overflow-hidden">
    
    <!-- 배경 은은한 오로라 조명 -->
    <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full blur-[110px] pointer-events-none transition-colors duration-500" style="background: var(--blob-1);"></div>
    <div class="absolute -bottom-40 -right-40 w-[480px] h-[480px] rounded-full blur-[110px] pointer-events-none transition-colors duration-500" style="background: var(--blob-2);"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[380px] h-[380px] rounded-full blur-[120px] pointer-events-none transition-colors duration-500" style="background: var(--blob-3);"></div>

    <!-- 우측 상단 다크/화이트 테마 토글 버튼 -->
    <div class="absolute top-6 right-6 z-30">
        <label class="swap swap-rotate btn btn-circle shadow-lg border border-base-content/10 bg-base-100/90 hover:scale-105 transition-all" style="border-radius: 1rem !important;">
            <input type="checkbox" id="theme-controller" onchange="toggleThemeMode()" />
            <!-- Sun Icon (화이트 모드일 때) -->
            <svg class="swap-off fill-current w-5 h-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM6.34,6.34A1,1,0,0,0,4.93,4.93L4.22,5.64A1,1,0,0,0,5.64,7.05Zm12,.71.71-.71a1,1,0,0,0-1.41-1.41L17,5.64a1,1,0,0,0,1.41,1.41ZM20,11H19a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-3,6.34.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41L18.36,17A1,1,0,0,0,17,17.34ZM12,7a5,5,0,1,0,5,5A5,5,0,0,0,12,7Zm0,8a3,3,0,1,1,3-3A3,3,0,0,1,12,15Z"/></svg>
            <!-- Moon Icon (다크 모드일 때) -->
            <svg class="swap-on fill-current w-5 h-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.69Z"/></svg>
        </label>
    </div>

    <!-- 모서리 곡률이 영구 고정된 카드 -->
    <div class="fixed-round-card card w-full max-w-sm z-10 transition-all duration-300">
        <div class="card-body p-8 sm:p-9">
            
            <!-- 상단 헤더 & 아이콘 -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/15 text-primary text-3xl mb-3 shadow-inner border border-primary/20">
                    ✈️
                </div>
                <h2 class="text-2xl font-black tracking-tight text-base-content flex items-center justify-center gap-1.5">
                    <span>항공사진촬영 관리</span>
                </h2>
                <p class="text-xs text-base-content/65 mt-1.5 font-medium">
                    📸 비행계획 · 데이터 취득 · 검수 시스템 🗺️
                </p>
            </div>
            
            <!-- 로그인 폼 -->
            <form name="flogin" action="<?php echo $login_action_url ?>" onsubmit="return flogin_submit(this);" method="post" class="space-y-4">
                <input type="hidden" name="url" value="<?php echo $login_url ?>">
                
                <div class="form-control">
                    <label class="label py-1">
                        <span class="label-text text-xs font-bold text-base-content/80 flex items-center gap-1.5">
                            <span>👤</span> 계정 ID
                        </span>
                    </label>
                    <input type="text" name="mb_id" id="login_id" placeholder="아이디를 입력하세요" 
                           class="fixed-round-input input input-bordered w-full bg-base-200/50 focus:bg-base-100 focus:input-primary border-base-content/15 text-sm font-medium transition-all shadow-sm" 
                           required maxLength="20" autocomplete="username">
                </div>
                
                <div class="form-control">
                    <label class="label py-1">
                        <span class="label-text text-xs font-bold text-base-content/80 flex items-center gap-1.5">
                            <span>🔒</span> 비밀번호
                        </span>
                    </label>
                    <input type="password" name="mb_password" id="login_pw" placeholder="비밀번호를 입력하세요" 
                           class="fixed-round-input input input-bordered w-full bg-base-200/50 focus:bg-base-100 focus:input-primary border-base-content/15 text-sm font-medium transition-all shadow-sm" 
                           required maxLength="20" autocomplete="current-password">
                </div>

                <div class="flex items-center justify-between px-1 pt-1">
                    <label class="cursor-pointer flex items-center gap-2">
                        <input type="checkbox" name="auto_login" id="login_auto_login" class="checkbox checkbox-primary checkbox-sm rounded-lg">
                        <span class="label-text text-xs font-semibold text-base-content/75">자동로그인 ⚡</span>
                    </label>
                </div>

                <div class="form-control pt-3">
                    <button type="submit" class="fixed-round-btn btn btn-primary w-full text-base font-bold shadow-lg shadow-primary/25 hover:shadow-primary/40 hover:scale-[1.01] active:scale-[0.98] transition-all">
                        <span>로그인</span>
                        <span class="text-lg">🚀</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 우측 하단 작제 버전 태그 -->
    <div class="fixed bottom-4 right-5 text-right pointer-events-none z-20">
        <div class="badge shadow-lg px-3.5 py-3.5 text-[11px] font-mono border border-base-content/10 bg-base-100/85 backdrop-blur text-base-content/80" style="border-radius: 1rem !important;">
            <span>🛠️</span>
            <span>VER : <strong class="text-primary font-bold">v1.0.0-RELEASE</strong></span>
            <span class="text-[10px] opacity-60">(2026)</span>
        </div>
    </div>

</div>

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

function flogin_submit(f) {
    if (!f.mb_id.value.trim()) {
        alert("아이디를 입력해 주세요.");
        f.mb_id.focus();
        return false;
    }
    if (!f.mb_password.value.trim()) {
        alert("비밀번호를 입력해 주세요.");
        f.mb_password.focus();
        return false;
    }
    return true;
}
</script>