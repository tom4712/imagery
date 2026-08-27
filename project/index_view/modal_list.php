<?php
if (!defined('_GNUBOARD_')) exit;
?>
<!-- 1. INDEX 목록 모달 -->
<dialog id="modal_index_list" class="modal z-[200]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-3xl max-w-xl p-6 flex flex-col max-h-[85vh]">
        
        <!-- 헤더 -->
        <div class="flex justify-between items-center pb-4 border-b border-base-content/10">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-2xl bg-primary/10 flex items-center justify-center text-primary text-base">
                    📑
                </div>
                <div>
                    <h3 class="font-black text-base text-base-content flex items-center gap-2">
                        <span>INDEX 도면 목록</span>
                        <span class="badge badge-primary badge-sm font-mono font-bold"><?php echo count($dwg_files); ?>개</span>
                    </h3>
                    <p class="text-[11px] text-base-content/60">생성된 DXF 인덱스 도면을 확인하고 관리합니다.</p>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-circle btn-ghost text-base-content/60 hover:text-base-content" onclick="modal_index_list.close()">✕</button>
        </div>

        <!-- 카드형 리스트 -->
        <div class="flex-1 overflow-y-auto custom-scrollbar my-4 space-y-2.5 pr-1">
            <?php if (!empty($dwg_files)) { 
                foreach ($dwg_files as $f) { 
                    $is_cur = ($f['filename'] === $active_dwg_file);
            ?>
                <div class="group relative flex items-center justify-between p-3.5 rounded-2xl border transition-all duration-200 
                            <?php echo $is_cur 
                                ? 'bg-primary/10 border-primary/40 shadow-lg shadow-primary/5' 
                                : 'bg-base-200/40 border-base-content/10 hover:bg-base-200/80 hover:border-base-content/20'; ?>">
                    
                    <div class="flex items-center gap-3 min-w-0 pr-2">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-xs flex-shrink-0
                                    <?php echo $is_cur ? 'bg-primary text-primary-content shadow-md shadow-primary/30' : 'bg-base-300 text-base-content/70'; ?>">
                            DXF
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs truncate <?php echo $is_cur ? 'text-primary' : 'text-base-content'; ?>" title="<?php echo htmlspecialchars($f['filename']); ?>">
                                    <?php echo htmlspecialchars($f['filename']); ?>
                                </span>
                                <?php if ($is_cur) { ?>
                                    <span class="badge badge-primary badge-xs py-1 px-1.5 font-bold font-sans flex-shrink-0">현재 뷰어</span>
                                <?php } ?>
                            </div>
                            <div class="flex items-center gap-3 mt-1 text-[11px] font-mono text-base-content/50">
                                <span class="flex items-center gap-1"><i class="fa-regular fa-clock text-[10px]"></i> <?php echo $f['mtime']; ?></span>
                                <span class="flex items-center gap-1"><i class="fa-solid fa-hard-drive text-[10px]"></i> <?php echo $f['size']; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <?php if (!$is_cur) { ?>
                            <button type="button" 
                                    class="btn btn-xs btn-primary rounded-xl font-bold px-3 shadow-sm hover:scale-105 active:scale-95 transition-transform" 
                                    onclick="selectActiveIndex('<?php echo htmlspecialchars($f['filename'], ENT_QUOTES); ?>')">
                                로드
                            </button>
                        <?php } ?>
                        
                        <button type="button" 
                                class="btn btn-xs btn-ghost btn-circle text-error/60 hover:text-error hover:bg-error/10 transition-colors" 
                                title="도면 파일 삭제"
                                onclick="openDeleteIndexModal('<?php echo htmlspecialchars($f['filename'], ENT_QUOTES); ?>')">
                            <i class="fa-regular fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            <?php } } else { ?>
                <div class="py-16 text-center text-base-content/40 space-y-2">
                    <span class="text-3xl block">🗺️</span>
                    <p class="font-bold text-xs">생성된 INDEX 도면 파일이 없습니다.</p>
                </div>
            <?php } ?>
        </div>

        <!-- 푸터 -->
        <div class="modal-action pt-3 border-t border-base-content/10 flex justify-between items-center mt-0">
            <button type="button" class="btn btn-sm btn-primary rounded-xl font-bold text-xs gap-1.5 shadow-md shadow-primary/20" onclick="modal_index_list.close(); modal_create_index.showModal();">
                <span>+</span> 새 인덱스 생성
            </button>
            <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold text-xs" onclick="modal_index_list.close()">닫기</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 2. 커스텀 삭제 확인 모달 (Glassmorphism & DaisyUI) -->
<dialog id="modal_confirm_delete_index" class="modal z-[220]">
    <div class="modal-box bg-base-100 border border-error/20 shadow-2xl rounded-3xl max-w-sm text-center p-6">
        <div class="w-14 h-14 rounded-2xl bg-error/10 text-error flex items-center justify-center text-2xl mx-auto mb-3 shadow-inner">
            🗑️
        </div>
        <h3 class="font-black text-base text-base-content">인덱스 도면을 삭제하시겠습니까?</h3>
        <p class="text-xs text-base-content/60 mt-1">삭제된 DXF 파일과 DB 기록은 복구할 수 없습니다.</p>
        
        <div class="bg-base-200/60 rounded-xl p-2.5 my-4 border border-base-content/5">
            <span id="display_delete_filename" class="font-mono font-bold text-xs text-error break-all">-</span>
        </div>

        <div class="modal-action justify-center gap-2 mt-4">
            <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold text-xs" onclick="modal_confirm_delete_index.close()">취소</button>
            <button type="button" class="btn btn-error btn-sm rounded-xl font-bold px-6 text-white text-xs shadow-md shadow-error/25" onclick="executeDeleteIndexFile()">영구 삭제</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 전송 폼 -->
<form id="form_delete_index_file" method="post" action="action.php">
    <input type="hidden" name="action" value="delete_index_file">
    <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
    <input type="hidden" name="date_id" value="<?php echo $date_id; ?>">
    <input type="hidden" name="filename" id="delete_target_filename" value="">
</form>

<script>
function openDeleteIndexModal(filename) {
    document.getElementById('delete_target_filename').value = filename;
    document.getElementById('display_delete_filename').innerText = filename;
    modal_confirm_delete_index.showModal();
}

function executeDeleteIndexFile() {
    document.getElementById('form_delete_index_file').submit();
}
</script>