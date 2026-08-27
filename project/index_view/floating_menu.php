<?php
if (!defined('_GNUBOARD_')) exit;
?>
<!-- 좌측 상단 컨트롤 바 -->
<div class="fixed top-5 left-5 z-50 flex items-center gap-2">
    <div class="glass-float rounded-2xl p-1.5 flex items-center gap-1.5 shadow-2xl">
        <a href="view.php?id=<?php echo $prj_id; ?>&tab=tab-flight" 
           class="btn btn-sm btn-ghost hover:bg-base-100/60 rounded-xl text-xs font-bold gap-1.5 px-3 text-base-content/90 transition-all active:scale-95">
            <i class="fa-solid fa-arrow-left text-primary text-[11px]"></i>
            <span>돌아가기</span>
        </a>
        
        <div class="divider divider-horizontal mx-0.5 my-1.5 w-[1px] bg-base-content/10"></div>

        <button type="button" class="btn btn-sm btn-primary rounded-xl text-xs font-bold gap-1.5 px-3 shadow-md shadow-primary/25 hover:scale-[1.02] active:scale-95 transition-all" onclick="modal_create_index.showModal()">
            <span>🗺️</span>
            <span>인덱스 생성</span>
        </button>

        <button type="button" class="btn btn-sm btn-ghost hover:bg-base-100/60 rounded-xl text-xs font-bold gap-1.5 px-3 text-base-content/90 transition-all" onclick="modal_index_list.showModal()">
            <i class="fa-solid fa-list-ul text-info text-[11px]"></i>
            <span>인덱스 리스트</span>
            <?php if (!empty($dwg_files)) { ?>
                <span class="badge badge-neutral badge-xs font-mono font-bold"><?php echo count($dwg_files); ?></span>
            <?php } ?>
        </button>
    </div>

    <?php if (!empty($active_dwg_file)) { ?>
        <div class="glass-float rounded-2xl px-3.5 py-2 flex items-center gap-2 text-xs font-mono">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success"></span>
            </span>
            <span class="font-bold text-base-content/80 text-[11px]">LOADED:</span>
            <span class="font-bold text-primary truncate max-w-xs" title="<?php echo htmlspecialchars($active_dwg_file); ?>"><?php echo htmlspecialchars($active_dwg_file); ?></span>
        </div>
    <?php } ?>
</div>

<!-- 우측 상단 줌 컨트롤 & 레이어 패널 -->
<div class="fixed top-5 right-5 z-40 flex flex-col items-end gap-2">
    <div class="flex items-center gap-2">
        <div class="glass-float rounded-2xl px-4 py-2 flex items-center gap-2 text-xs font-bold">
            <span class="text-base">📁</span>
            <span class="text-base-content/90"><?php echo htmlspecialchars($prj['prj_name']); ?></span>
            <span class="badge badge-primary badge-sm font-mono"><?php echo htmlspecialchars($flight['flight_date']); ?></span>
        </div>
        
        <div class="glass-float rounded-2xl p-1 flex items-center gap-1">
            <button class="btn btn-xs btn-circle btn-ghost" onclick="zoomIn()" title="확대 (+)"><i class="fa-solid fa-plus"></i></button>
            <button class="btn btn-xs btn-circle btn-ghost" onclick="zoomOut()" title="축소 (-)"><i class="fa-solid fa-minus"></i></button>
            <button class="btn btn-xs btn-circle btn-ghost text-primary" onclick="fitView(true)" title="화면맞춤 (휠 더블클릭)"><i class="fa-solid fa-expand"></i></button>
            <button class="btn btn-xs btn-circle btn-ghost text-accent" onclick="toggleLayerPanel()" title="레이어 관리"><i class="fa-solid fa-layer-group"></i></button>
        </div>
    </div>

    <!-- DaisyUI 드롭다운 레이어 매니저 패널 -->
    <div id="layer_manager_panel" class="glass-float rounded-2xl p-3 w-64 shadow-2xl border border-base-content/10 flex flex-col gap-2 transition-all duration-200 origin-top-right">
        <div class="flex items-center justify-between border-b border-base-content/10 pb-1.5 px-1">
            <span class="font-black text-xs text-base-content/90 flex items-center gap-1.5">
                <i class="fa-solid fa-layer-group text-primary text-[11px]"></i>
                <span>레이어 관리</span>
            </span>
            <span class="badge badge-neutral badge-xs font-mono" id="layer_count_badge">0</span>
        </div>
        <div id="layer_list_container" class="flex flex-col gap-1 max-h-60 overflow-y-auto custom-scrollbar pr-1">
            <!-- 레이어 체크박스/동결 아이콘 동적 렌더링 -->
        </div>
    </div>
</div>