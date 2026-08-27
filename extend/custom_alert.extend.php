<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * 모던 Alert 창 렌더러 (SweetAlert2 + DaisyUI 스타일 연동)
 */
function modern_alert($msg='', $url='', $error=true, $post=false) {
    global $g5, $config, $member;

    if (!$msg) $msg = '올바른 방법으로 이용해 주십시오.';

    $header_title = $error ? '주의 및 알림' : '알림';
    $icon = $error ? 'warning' : 'success';
    $icon_emoji = $error ? '⚠️' : '✨';
    $theme = isset($_COOKIE['app-theme']) ? $_COOKIE['app-theme'] : 'dark';

    echo '<!doctype html>
    <html lang="ko" data-theme="'.$theme.'">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>'.$header_title.'</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css" rel="stylesheet" type="text/css" />
    <style>
        html, body {
            width: 100vw;
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: "Pretendard", -apple-system, system-ui, sans-serif;
        }
        [data-theme="light"] {
            --bg: linear-gradient(135deg, #eff6ff 0%, #faf5ff 50%, #f0fdf4 100%);
            --card-bg: rgba(255, 255, 255, 0.9);
            --card-border: rgba(255, 255, 255, 0.95);
            --shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.15);
        }
        [data-theme="dark"] {
            --bg: #0b1120;
            --card-bg: rgba(17, 24, 39, 0.9);
            --card-border: rgba(255, 255, 255, 0.08);
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }
        .bg-wrap { background: var(--bg); }
        .glass-box {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
            border-radius: 1.25rem;
        }
    </style>
    </head>
    <body class="bg-wrap flex items-center justify-center p-4 select-none">
    
    <div class="glass-box card w-full max-w-sm p-6 text-center animate-scaleIn">
        <div class="w-12 h-12 rounded-2xl '.($error ? 'bg-error/15 text-error' : 'bg-primary/15 text-primary').' flex items-center justify-center text-2xl mx-auto mb-3">
            '.$icon_emoji.'
        </div>
        <h3 class="text-base font-black text-base-content mb-2">'.$header_title.'</h3>
        <p class="text-xs font-medium text-base-content/80 leading-relaxed mb-6">'.nl2br(htmlspecialchars($msg)).'</p>
        
        <div>';
        
    if ($url) {
        echo '<a href="'.$url.'" class="btn btn-sm btn-primary w-full rounded-xl font-bold shadow-md shadow-primary/25">확인</a>';
    } else {
        echo '<button onclick="history.back();" class="btn btn-sm btn-primary w-full rounded-xl font-bold shadow-md shadow-primary/25">확인</button>';
    }

    echo '</div>
    </div>

    <script>
        (function() {
            const savedTheme = localStorage.getItem("app-theme") || "dark";
            document.documentElement.setAttribute("data-theme", savedTheme);
        })();
    </script>
    </body>
    </html>';
    exit;
}

// 그누보드 기본 alert() 함수 가로채기 (상단에 선언하여 재정의)
if (!function_exists('alert')) {
    function alert($msg='', $url='', $error=true, $post=false) {
        modern_alert($msg, $url, $error, $post);
    }
}
?>