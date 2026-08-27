<?php if (!defined('_GNUBOARD_')) exit; ?>

<!-- 1. 항공사진 이름변경 모달 -->
<dialog id="modal_rename" class="modal z-[200]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-lg">
        <h3 class="font-black text-lg mb-4 text-base-content flex items-center gap-2">🏷️ 항공사진 일괄 이름변경</h3>
        <div class="space-y-3 text-xs">
            <div class="alert bg-base-200/70 border-none rounded-xl">
                <span>💡 <code>E:\#KYS_IMAGERY_SERVER\[사업명]</code> 내 원본 사진 파일명을 표준 형식으로 변경합니다.</span>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text font-bold">네이밍 규칙 템플릿</span></label>
                <input type="text" value="{사업명}_{코스}_{촬영번호}.tif" class="input input-bordered input-sm rounded-xl font-mono" />
            </div>
        </div>
        <div class="modal-action mt-6">
            <button class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_rename.close()">닫기</button>
            <button class="btn btn-primary btn-sm rounded-xl font-bold px-5">이름변경 실행</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 2. 전체 인덱스 확인 모달 -->
<dialog id="modal_index" class="modal z-[200]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-2xl">
        <h3 class="font-black text-lg mb-4 text-base-content flex items-center gap-2">🗺️ 전체 촬영 인덱스 맵</h3>
        <div class="w-full h-64 bg-base-200/50 rounded-2xl border border-dashed border-base-content/20 flex flex-col items-center justify-center text-base-content/50 gap-2">
            <span class="text-4xl">🛰️</span>
            <span class="text-xs font-bold font-mono">E:\#KYS_IMAGERY_SERVER\<?php echo htmlspecialchars($prj['prj_name']); ?>\EO 엑셀 파싱 뷰어</span>
        </div>
        <div class="modal-action mt-6">
            <button class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_index.close()">닫기</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>