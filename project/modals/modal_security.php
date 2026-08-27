<?php if (!defined('_GNUBOARD_')) exit; ?>

<!-- 보안성검토 등록 모달 -->
<dialog id="modal_add_sec" class="modal z-[200]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-sm">
        <h3 class="font-black text-lg mb-2 text-base-content flex items-center gap-2">🛡️ 보안성검토 차수 등록</h3>
        <p class="text-xs text-base-content/60 mb-4"><code>security\[N차]\(EO, INDEX, 문서)</code> 폴더가 자동 생성됩니다.</p>
        
        <form method="post" action="action.php" class="space-y-4">
            <input type="hidden" name="action" value="add_security">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            
            <div class="form-control">
                <label class="label py-1"><span class="label-text font-bold text-xs">차수 (숫자)</span></label>
                <input type="number" name="round_no" value="1" min="1" class="input input-bordered rounded-xl w-full text-sm font-medium" required>
            </div>
            <div class="form-control">
                <label class="label py-1"><span class="label-text font-bold text-xs">검토 신청일</span></label>
                <input type="date" name="check_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered rounded-xl w-full text-sm font-medium" required>
            </div>
            <div class="form-control">
                <label class="label py-1"><span class="label-text font-bold text-xs">비고</span></label>
                <input type="text" name="remarks" placeholder="신청 내역 메모" class="input input-bordered rounded-xl w-full text-sm font-medium">
            </div>
            
            <div class="modal-action mt-6">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_add_sec.close()">취소</button>
                <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-5">등록</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>