<?php
include_once('./_common.php');

if (!isset($is_member) || !$is_member) {
    goto_url(G5_BBS_URL.'/login.php');
}

$prj_id  = isset($_GET['prj_id']) ? (int)$_GET['prj_id'] : 0;
$date_id = isset($_GET['date_id']) ? (int)$_GET['date_id'] : 0;
$block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
$index_mode = $block_id ? 'block' : 'date';

if (!$prj_id || (!$date_id && !$block_id)) {
    alert('잘못된 접근입니다.', G5_URL.'/index.php');
}

$prj = sql_fetch(" SELECT * FROM IMG_PROJECT WHERE prj_id = {$prj_id} ") ?: [
    'prj_name' => '프로젝트',
    'prj_id'   => $prj_id
];

$flight = null;
$block = null;
$viewer_label = '';

if ($index_mode === 'block') {
    $block = sql_fetch(" SELECT * FROM IMG_BLOCK WHERE block_id = {$block_id} AND prj_id = {$prj_id} ");
    if (!$block) alert('블럭 정보를 찾을 수 없습니다.', G5_URL.'/project/view.php?id='.$prj_id.'&tab=tab-block');
    $viewer_label = $block['block_name'];
} else {
    $flight = sql_fetch(" SELECT * FROM IMG_FLIGHT_DATE WHERE date_id = {$date_id} AND prj_id = {$prj_id} ") ?: [
        'flight_date' => date('Y-m-d'),
        'flight_name' => '촬영일 미등록',
        'date_id'     => $date_id
    ];
    $viewer_label = $flight['flight_date'] ?? '';
}

$g5['title'] = htmlspecialchars($prj['prj_name']) . ' - INDEX 도면 뷰어 [' . $viewer_label . ']';

// 1. 파서 모듈 로드 (물리 파일 스캔 및 $entities 파싱)
require_once(__DIR__ . '/index_view/parser.php');
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

    <!-- 상단 플로팅 메뉴 & 줌 컨트롤 바 -->
    <?php require_once(__DIR__ . '/index_view/floating_menu.php'); ?>

    <!-- 메인 뷰어 캔버스 영역 -->
    <div class="relative w-full h-full canvas-grid flex items-center justify-center overflow-hidden">
        <?php if (!empty($active_dwg_file)) { ?>
            <canvas id="cad_canvas" class="w-full h-full cursor-grab active:cursor-grabbing"></canvas>
            
            <div class="fixed bottom-6 left-6 z-30 pointer-events-none">
                <div class="glass-float rounded-xl px-4 py-2.5 text-[11px] font-medium text-base-content/70 flex items-center gap-4">
                    <span>🖱️ <strong>휠/좌클릭 드래그</strong>: 이동</span>
                    <span>🔍 <strong>휠 스크롤</strong>: 줌</span>
                    <span>🎯 <strong class="text-primary">마우스 휠 더블클릭</strong>: 화면 맞춤</span>
                    <span class="flex items-center gap-1.5 ml-2"><span class="w-2.5 h-2.5 rounded-sm bg-indigo-500/20 border border-indigo-400 inline-block"></span> 5만 도곽</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span> 본촬영</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400 inline-block"></span> 재촬영(_A)</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-2 border border-yellow-400 inline-block"></span> 재촬영구간</span>
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
                        현재 <?php echo $index_mode === 'block' ? '블럭' : '촬영일'; ?>(<code><?php echo htmlspecialchars($viewer_label); ?></code>)의 INDEX 도면이 등록되지 않았습니다.<br>
                        <?php echo $index_mode === 'block' ? '블럭 탭에서 INDEX를 생성해 주세요.' : '좌측 상단의 <strong>[인덱스 생성]</strong> 버튼을 눌러 도면을 생성해 주세요.'; ?>
                    </p>
                </div>
                <?php if ($index_mode === 'date') { ?>
                <div class="flex gap-2 mt-2">
                    <button type="button" class="btn btn-primary btn-sm rounded-xl font-bold px-5" onclick="modal_create_index.showModal()">
                        <span>🚀 지금 인덱스 생성하기</span>
                    </button>
                </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <!-- 모달 컴포넌트 -->
    <?php if ($index_mode === 'date') require_once(__DIR__ . '/index_view/modal_create.php'); ?>
    <?php require_once(__DIR__ . '/index_view/modal_list.php'); ?>

    <!-- 캔버스 인터랙션 및 렌더링 엔진 -->
    <?php require_once(__DIR__ . '/index_view/viewer_canvas.js.php'); ?>

</body>
</html>
