<?php
if (!defined('_GNUBOARD_')) exit;
?>
<dialog id="modal_create_index" class="modal z-[220]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-md p-6">
        <h3 class="font-black text-lg text-base-content flex items-center gap-2 mb-2">
            <span>🗺️ 인덱스 도면 원클릭 생성</span>
        </h3>
        <p class="text-xs text-base-content/60 mb-4">
            EO 주점과 주변 <strong>5만 도곽(도곽선 + 도엽명/번호)</strong>을 자동으로 매핑하여 AutoCAD DXF 도면을 생성합니다.
        </p>

        <form method="post" action="action.php" class="flex flex-col gap-3">
            <input type="hidden" name="action" value="generate_index_dwg">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            <input type="hidden" name="date_id" value="<?php echo $date_id; ?>">

            <div class="form-control">
                <label class="label py-0.5"><span class="label-text font-bold text-[11px]">생성 파일명 (.dxf)</span></label>
                <input type="text" name="index_name" value="<?php echo htmlspecialchars($prj['prj_name'] . '_' . str_replace('-', '', $flight['flight_date']) . '_INDEX.dxf'); ?>" class="input input-bordered input-sm rounded-lg font-mono text-xs" required>
            </div>

            <div class="form-control">
                <label class="label py-0.5"><span class="label-text font-bold text-[11px]">기준 좌표계 (Base DXF)</span></label>
                <select name="crs_type" class="select select-bordered select-sm rounded-lg text-xs font-semibold">
                    <option value="EPSG:5186" selected>EPSG:5186 (중부원점 - mid_50k)</option>
                    <option value="EPSG:5187">EPSG:5187 (동부원점 - east_50k)</option>
                    <option value="EPSG:5185">EPSG:5185 (서부원점 - west_50k)</option>
                </select>
            </div>

            <div class="modal-action pt-3 border-t border-base-content/10 flex justify-end gap-2">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_create_index.close()">취소</button>
                <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-6 shadow-md shadow-primary/25">
                    도면 생성 및 렌더링 🚀
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>