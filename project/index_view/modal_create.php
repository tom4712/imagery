<?php
if (!defined('_GNUBOARD_')) exit;
?>
<dialog id="modal_create_index" class="modal z-[220]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-2xl p-6">
        <h3 class="font-black text-lg text-base-content flex items-center gap-2 mb-2">
            <span>🗺️ 인덱스 도면 생성</span>
        </h3>

        <form id="form_create_index" method="post" action="action.php" class="flex flex-col gap-3">
            <input type="hidden" name="action" value="generate_index_dwg">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            <input type="hidden" name="date_id" value="<?php echo $date_id; ?>">
            <input type="hidden" name="selected_sheets" id="input_selected_sheets" value="">

            <!-- STEP 1: 파일명 / 좌표계 -->
            <div id="ci_step1">
                <p class="text-xs text-base-content/60 mb-3">
                    EO 주점을 읽어 겹치는 <strong>5만 도곽(도곽선 + 도엽명/번호)</strong>을 확인한 뒤 병합 도면을 생성합니다.
                </p>
                <div class="form-control">
                    <label class="label py-0.5"><span class="label-text font-bold text-[11px]">생성 파일명 (.dxf)</span></label>
                    <input type="text" name="index_name" id="ci_index_name"
                           value="<?php echo htmlspecialchars($prj['prj_name'] . '_' . str_replace('-', '', $flight['flight_date']) . '_INDEX.dxf'); ?>"
                           class="input input-bordered input-sm rounded-lg font-mono text-xs" required>
                </div>
                <div class="form-control mt-2">
                    <label class="label py-0.5"><span class="label-text font-bold text-[11px]">기준 좌표계 (Base DXF)</span></label>
                    <select name="crs_type" id="ci_crs_type" class="select select-bordered select-sm rounded-lg text-xs font-semibold">
                        <option value="EPSG:5186" selected>EPSG:5186 (중부원점 - mid_50k)</option>
                        <option value="EPSG:5187">EPSG:5187 (동부원점 - east_50k)</option>
                        <option value="EPSG:5185">EPSG:5185 (서부원점 - west_50k)</option>
                    </select>
                </div>
                <div class="modal-action pt-3 border-t border-base-content/10 flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_create_index.close()">취소</button>
                    <button type="button" id="ci_btn_next" class="btn btn-primary btn-sm rounded-xl font-bold px-6" onclick="ciLoadSheetPreview()">
                        도곽 확인 →
                    </button>
                </div>
            </div>

            <!-- STEP 2: 샘플화면 (도곽 선택) -->
            <div id="ci_step2" class="hidden">
                <div class="stats stats-horizontal shadow bg-base-200 w-full mb-3 text-center">
                    <div class="stat py-2 px-3">
                        <div class="stat-title text-[10px]">주점 수</div>
                        <div class="stat-value text-primary text-lg" id="ci_stat_points">0</div>
                    </div>
                    <div class="stat py-2 px-3">
                        <div class="stat-title text-[10px]">교차 도곽</div>
                        <div class="stat-value text-secondary text-lg" id="ci_stat_direct">0</div>
                    </div>
                    <div class="stat py-2 px-3">
                        <div class="stat-title text-[10px]">선택됨</div>
                        <div class="stat-value text-accent text-lg" id="ci_stat_selected">0</div>
                    </div>
                </div>

                <svg id="ci_minimap" viewBox="0 0 400 300" class="w-full bg-slate-950 rounded-lg border border-base-content/10 mb-3" style="height:220px"></svg>

                <div class="flex gap-2 mb-2">
                    <button type="button" class="btn btn-xs btn-outline" onclick="ciSelectMode('direct')">교차 도곽만</button>
                    <button type="button" class="btn btn-xs btn-outline" onclick="ciSelectMode('all')">전체 선택</button>
                    <button type="button" class="btn btn-xs btn-outline" onclick="ciSelectMode('none')">전체 해제</button>
                </div>

                <div id="ci_sheet_list" class="max-h-52 overflow-y-auto space-y-1 border border-base-content/10 rounded-lg p-2"></div>

                <div class="modal-action pt-3 border-t border-base-content/10 flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="ciBackToStep1()">← 이전</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-6 shadow-md shadow-primary/25">
                        도면 생성 및 렌더링 🚀
                    </button>
                </div>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
let ciSheets = [];
let ciSelected = new Set();
let ciBBox = null;

async function ciLoadSheetPreview() {
    const btn = document.getElementById('ci_btn_next');
    btn.disabled = true;
    btn.textContent = '불러오는 중...';
    try {
        const crs = document.getElementById('ci_crs_type').value;
        const res = await fetch(`action.php?action=preview_index_data&prj_id=<?php echo (int)$prj_id; ?>&date_id=<?php echo (int)$date_id; ?>&crs_type=${encodeURIComponent(crs)}`);
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'EO 데이터를 불러오지 못했습니다.');
            return;
        }
        ciSheets = data.map_sheets || [];
        ciBBox = data.bbox;
        ciSelected = new Set(ciSheets.filter(s => s.is_direct).map(s => s.sheet_no));

        document.getElementById('ci_stat_points').textContent = data.total ?? 0;
        document.getElementById('ci_stat_direct').textContent = ciSheets.filter(s => s.is_direct).length;

        ciRenderList();
        ciRenderMinimap();

        document.getElementById('ci_step1').classList.add('hidden');
        document.getElementById('ci_step2').classList.remove('hidden');
    } catch (e) {
        alert('오류: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '도곽 확인 →';
    }
}

function ciBackToStep1() {
    document.getElementById('ci_step2').classList.add('hidden');
    document.getElementById('ci_step1').classList.remove('hidden');
}

function ciRenderList() {
    const wrap = document.getElementById('ci_sheet_list');
    wrap.innerHTML = ciSheets.map(s => `
        <label class="flex items-center gap-2 text-xs px-1.5 py-1 rounded hover:bg-base-200 cursor-pointer">
            <input type="checkbox" class="checkbox checkbox-xs" data-sheet="${s.sheet_no}"
                   ${ciSelected.has(s.sheet_no) ? 'checked' : ''} onchange="ciToggleSheet('${s.sheet_no}', this.checked)">
            <span class="font-bold">${s.sheet_name}</span>
            <span class="text-base-content/50 font-mono">(${s.sheet_no})</span>
            ${s.is_direct ? '<span class="badge badge-primary badge-xs ml-auto">교차</span>' : ''}
        </label>
    `).join('');
    document.getElementById('ci_stat_selected').textContent = ciSelected.size;
    document.getElementById('input_selected_sheets').value = Array.from(ciSelected).join(',');
}

function ciToggleSheet(sheetNo, checked) {
    if (checked) ciSelected.add(sheetNo); else ciSelected.delete(sheetNo);
    document.getElementById('ci_stat_selected').textContent = ciSelected.size;
    document.getElementById('input_selected_sheets').value = Array.from(ciSelected).join(',');
    ciRenderMinimap();
}

function ciSelectMode(mode) {
    if (mode === 'direct') ciSelected = new Set(ciSheets.filter(s => s.is_direct).map(s => s.sheet_no));
    else if (mode === 'all') ciSelected = new Set(ciSheets.map(s => s.sheet_no));
    else ciSelected = new Set();
    ciRenderList();
    ciRenderMinimap();
}

function ciRenderMinimap() {
    const svg = document.getElementById('ci_minimap');
    if (!ciSheets.length) { svg.innerHTML = ''; return; }

    const allX = ciSheets.flatMap(s => [s.bounds.min_x, s.bounds.max_x]);
    const allY = ciSheets.flatMap(s => [s.bounds.min_y, s.bounds.max_y]);
    const minX = Math.min(...allX), maxX = Math.max(...allX);
    const minY = Math.min(...allY), maxY = Math.max(...allY);
    const pad = 10, W = 400, H = 300;
    const sx = (W - pad * 2) / (maxX - minX || 1);
    const sy = (H - pad * 2) / (maxY - minY || 1);
    const scale = Math.min(sx, sy);
    const tx = x => pad + (x - minX) * scale;
    const ty = y => H - pad - (y - minY) * scale; // Y축 반전

    let svgContent = '';
    ciSheets.forEach(s => {
        const b = s.bounds;
        const x = tx(b.min_x), y = ty(b.max_y);
        const w = (b.max_x - b.min_x) * scale, h = (b.max_y - b.min_y) * scale;
        const selected = ciSelected.has(s.sheet_no);
        const fill = selected ? 'rgba(99,102,241,0.45)' : (s.is_direct ? 'rgba(99,102,241,0.15)' : 'transparent');
        const stroke = selected ? '#818cf8' : '#475569';
        svgContent += `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${fill}" stroke="${stroke}" stroke-width="0.6"
                        style="cursor:pointer" onclick="ciToggleSheet('${s.sheet_no}', ${!selected}); document.querySelector('[data-sheet=\\'${s.sheet_no}\\']').checked = ${!selected};" />`;
    });

    if (ciBBox) {
        const x = tx(ciBBox.min_x), y = ty(ciBBox.max_y);
        const w = (ciBBox.max_x - ciBBox.min_x) * scale, h = (ciBBox.max_y - ciBBox.min_y) * scale;
        svgContent += `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="none" stroke="#ef4444" stroke-width="1" stroke-dasharray="3,2" />`;
    }

    svg.innerHTML = svgContent;
}
</script>