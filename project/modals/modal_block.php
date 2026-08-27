<?php if (!defined('_GNUBOARD_')) exit; ?>

<!-- 1. 블럭 수동 단일 등록 모달 -->
<dialog id="modal_add_block_single" class="modal z-[200]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-sm">
        <h3 class="font-black text-lg mb-2 text-base-content flex items-center gap-2">🧩 블럭 수동 등록</h3>
        <p class="text-xs text-base-content/60 mb-4">블럭명과 시작/종료 코스를 입력하면 폴더와 DB가 생성됩니다.</p>
        
        <form method="post" action="action.php" class="space-y-4">
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

<!-- 2. 블럭 텍스트 일괄 등록 모달 -->
<dialog id="modal_add_block_bulk" class="modal z-[200]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-lg">
        <h3 class="font-black text-lg mb-2 text-base-content flex items-center gap-2">📄 블럭 텍스트 일괄 등록</h3>
        <div class="alert bg-base-200/70 border-none rounded-xl py-2 px-3 text-xs mb-3">
            <span>💡 <code>블럭명 [탭/공백] 코스번호</code> 형식으로 복사해 넣으세요. 코스 범위와 개수가 자동 분석됩니다.</span>
        </div>
        
        <form method="post" action="action.php" class="space-y-3">
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

<!-- 3. 블럭 삭제 확인 모달 -->
<dialog id="modal_confirm_delete_block" class="modal z-[210]">
    <div class="modal-box bg-base-100 border border-error/20 shadow-2xl rounded-2xl max-w-sm text-center">
        <div class="w-14 h-14 rounded-full bg-error/10 text-error flex items-center justify-center text-2xl mx-auto mb-3">
            ⚠️
        </div>
        <h3 class="font-black text-lg text-base-content">선택한 블럭을 정말 삭제합니까?</h3>
        <div class="text-xs text-base-content/70 mt-2 space-y-1">
            <p>DB 기록뿐만 아니라 <strong class="text-error font-bold">E 드라이브의 실제 폴더</strong>까지 영구 삭제됩니다.</p>
            <div id="delete_target_list" class="badge badge-ghost mt-2 py-2 px-3 font-mono text-xs"></div>
        </div>
        <div class="modal-action justify-center gap-2 mt-6">
            <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_confirm_delete_block.close()">취소</button>
            <button type="button" class="btn btn-error btn-sm rounded-xl font-bold px-6 text-white" onclick="executeBlockDelete()">영구 삭제</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>