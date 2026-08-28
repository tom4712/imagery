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

            <div class="form-control">
                <label class="label py-1"><span class="label-text font-bold text-xs">코스당 설계매수</span></label>
                <input type="number" name="design_per_course" min="0" placeholder="선택 입력" class="input input-bordered rounded-xl w-full text-sm font-medium">
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
            <span>💡 <code>블럭명 [탭] 코스번호 [탭] 설계매수</code> 형식으로 복사해 넣으세요. 설계매수 없이 2열만 넣어도 등록됩니다.</span>
        </div>
        
        <form method="post" action="action.php" class="space-y-3">
            <input type="hidden" name="action" value="add_block_bulk">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            
            <div class="form-control">
                <textarea name="bulk_text" rows="10" placeholder="1BL	1	10&#10;1BL	2	12&#10;3BL	13	10&#10;3BL	14	10" class="textarea textarea-bordered rounded-xl font-mono text-xs leading-relaxed focus:textarea-primary" required></textarea>
            </div>

            <div class="modal-action mt-4">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_add_block_bulk.close()">취소</button>
                <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-6">일괄 등록 실행 🚀</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 3. 블럭별 인덱스 생성 모달 -->
<dialog id="modal_create_block_index" class="modal z-[220]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-2xl p-6">
        <h3 class="font-black text-lg text-base-content flex items-center gap-2 mb-2">
            <span>🗺️ 블럭 인덱스 생성</span>
        </h3>

        <form id="form_create_block_index" method="post" action="action.php" class="flex flex-col gap-3">
            <input type="hidden" name="action" value="generate_block_index_dwg">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            <input type="hidden" name="block_id" id="bi_block_id" value="">
            <input type="hidden" name="selected_sheets" id="bi_selected_sheets" value="">

            <div id="bi_step1">
                <p class="text-xs text-base-content/60 mb-3">
                    블럭에 포함된 코스의 정상 EO 주점만 모아 5만 도곽과 함께 DXF를 생성합니다.
                </p>
                <div class="form-control">
                    <label class="label py-0.5"><span class="label-text font-bold text-[11px]">생성 파일명 (.dxf)</span></label>
                    <input type="text" name="index_name" id="bi_index_name" class="input input-bordered input-sm rounded-lg font-mono text-xs" required>
                </div>
                <div class="form-control mt-2">
                    <label class="label py-0.5"><span class="label-text font-bold text-[11px]">기준 좌표계</span></label>
                    <select name="crs_type" id="bi_crs_type" class="select select-bordered select-sm rounded-lg text-xs font-semibold">
                        <option value="EPSG:5186" selected>EPSG:5186 (중부원점 - mid_50k)</option>
                        <option value="EPSG:5187">EPSG:5187 (동부원점 - east_50k)</option>
                        <option value="EPSG:5185">EPSG:5185 (서부원점 - west_50k)</option>
                    </select>
                </div>
                <div class="modal-action pt-3 border-t border-base-content/10 flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_create_block_index.close()">취소</button>
                    <button type="button" id="bi_btn_next" class="btn btn-primary btn-sm rounded-xl font-bold px-6" onclick="biLoadSheetPreview()">도곽 확인</button>
                </div>
            </div>

            <div id="bi_step2" class="hidden">
                <div class="stats stats-horizontal shadow bg-base-200 w-full mb-3 text-center">
                    <div class="stat py-2 px-3">
                        <div class="stat-title text-[10px]">주점 수</div>
                        <div class="stat-value text-primary text-lg" id="bi_stat_points">0</div>
                    </div>
                    <div class="stat py-2 px-3">
                        <div class="stat-title text-[10px]">교차 도곽</div>
                        <div class="stat-value text-secondary text-lg" id="bi_stat_direct">0</div>
                    </div>
                    <div class="stat py-2 px-3">
                        <div class="stat-title text-[10px]">선택됨</div>
                        <div class="stat-value text-accent text-lg" id="bi_stat_selected">0</div>
                    </div>
                </div>

                <svg id="bi_minimap" viewBox="0 0 400 300" class="w-full bg-slate-950 rounded-lg border border-base-content/10 mb-3" style="height:220px"></svg>

                <div class="flex gap-2 mb-2">
                    <button type="button" class="btn btn-xs btn-outline" onclick="biSelectMode('direct')">교차 도곽만</button>
                    <button type="button" class="btn btn-xs btn-outline" onclick="biSelectMode('all')">전체 선택</button>
                    <button type="button" class="btn btn-xs btn-outline" onclick="biSelectMode('none')">전체 해제</button>
                </div>

                <div id="bi_sheet_list" class="max-h-52 overflow-y-auto space-y-1 border border-base-content/10 rounded-lg p-2"></div>

                <div class="modal-action pt-3 border-t border-base-content/10 flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="biBackToStep1()">이전</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-6 shadow-md shadow-primary/25">도면 생성</button>
                </div>
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

<script>
let biSheets = [];
let biSelected = new Set();
let biBBox = null;

function openBlockIndexModal(blockId, blockName) {
    document.getElementById('bi_block_id').value = blockId;
    document.getElementById('bi_index_name').value = `${blockName}_INDEX.dxf`;
    document.getElementById('bi_step1').classList.remove('hidden');
    document.getElementById('bi_step2').classList.add('hidden');
    biSheets = [];
    biSelected = new Set();
    biBBox = null;
    modal_create_block_index.showModal();
}

async function biLoadSheetPreview() {
    const btn = document.getElementById('bi_btn_next');
    btn.disabled = true;
    btn.textContent = '불러오는 중...';
    try {
        const blockId = document.getElementById('bi_block_id').value;
        const crs = document.getElementById('bi_crs_type').value;
        const res = await fetch(`action.php?action=preview_block_index_data&prj_id=<?php echo (int)$prj_id; ?>&block_id=${encodeURIComponent(blockId)}&crs_type=${encodeURIComponent(crs)}`);
        const data = await res.json();
        if (!data.success) {
            alert(data.message || '블럭 EO 데이터를 불러오지 못했습니다.');
            return;
        }
        biSheets = data.map_sheets || [];
        biBBox = data.bbox;
        biSelected = new Set(biSheets.filter(s => s.is_direct).map(s => s.sheet_no));

        document.getElementById('bi_stat_points').textContent = data.total ?? 0;
        document.getElementById('bi_stat_direct').textContent = biSheets.filter(s => s.is_direct).length;

        biRenderList();
        biRenderMinimap();

        document.getElementById('bi_step1').classList.add('hidden');
        document.getElementById('bi_step2').classList.remove('hidden');
    } catch (e) {
        alert('오류: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '도곽 확인';
    }
}

function biBackToStep1() {
    document.getElementById('bi_step2').classList.add('hidden');
    document.getElementById('bi_step1').classList.remove('hidden');
}

function biRenderList() {
    const wrap = document.getElementById('bi_sheet_list');
    wrap.innerHTML = biSheets.map(s => `
        <label class="flex items-center gap-2 text-xs px-1.5 py-1 rounded hover:bg-base-200 cursor-pointer">
            <input type="checkbox" class="checkbox checkbox-xs" data-block-sheet="${s.sheet_no}"
                   ${biSelected.has(s.sheet_no) ? 'checked' : ''} onchange="biToggleSheet('${s.sheet_no}', this.checked)">
            <span class="font-bold">${s.sheet_name}</span>
            <span class="text-base-content/50 font-mono">(${s.sheet_no})</span>
            ${s.is_direct ? '<span class="badge badge-primary badge-xs ml-auto">교차</span>' : ''}
        </label>
    `).join('');
    document.getElementById('bi_stat_selected').textContent = biSelected.size;
    document.getElementById('bi_selected_sheets').value = Array.from(biSelected).join(',');
}

function biToggleSheet(sheetNo, checked) {
    if (checked) biSelected.add(sheetNo); else biSelected.delete(sheetNo);
    document.getElementById('bi_stat_selected').textContent = biSelected.size;
    document.getElementById('bi_selected_sheets').value = Array.from(biSelected).join(',');
    biRenderMinimap();
}

function biSelectMode(mode) {
    if (mode === 'direct') biSelected = new Set(biSheets.filter(s => s.is_direct).map(s => s.sheet_no));
    else if (mode === 'all') biSelected = new Set(biSheets.map(s => s.sheet_no));
    else biSelected = new Set();
    biRenderList();
    biRenderMinimap();
}

function biRenderMinimap() {
    const svg = document.getElementById('bi_minimap');
    if (!biSheets.length) { svg.innerHTML = ''; return; }

    const allX = biSheets.flatMap(s => [s.bounds.min_x, s.bounds.max_x]);
    const allY = biSheets.flatMap(s => [s.bounds.min_y, s.bounds.max_y]);
    const minX = Math.min(...allX), maxX = Math.max(...allX);
    const minY = Math.min(...allY), maxY = Math.max(...allY);
    const pad = 10, W = 400, H = 300;
    const sx = (W - pad * 2) / (maxX - minX || 1);
    const sy = (H - pad * 2) / (maxY - minY || 1);
    const scale = Math.min(sx, sy);
    const tx = x => pad + (x - minX) * scale;
    const ty = y => H - pad - (y - minY) * scale;

    let svgContent = '';
    biSheets.forEach(s => {
        const b = s.bounds;
        const x = tx(b.min_x), y = ty(b.max_y);
        const w = (b.max_x - b.min_x) * scale, h = (b.max_y - b.min_y) * scale;
        const selected = biSelected.has(s.sheet_no);
        const fill = selected ? 'rgba(99,102,241,0.45)' : (s.is_direct ? 'rgba(99,102,241,0.15)' : 'transparent');
        const stroke = selected ? '#818cf8' : '#475569';
        svgContent += `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${fill}" stroke="${stroke}" stroke-width="0.6"
                        style="cursor:pointer" onclick="biToggleSheet('${s.sheet_no}', ${!selected}); const el=document.querySelector('[data-block-sheet=\\'${s.sheet_no}\\']'); if(el) el.checked=${!selected};" />`;
    });

    if (biBBox) {
        const x = tx(biBBox.min_x), y = ty(biBBox.max_y);
        const w = (biBBox.max_x - biBBox.min_x) * scale, h = (biBBox.max_y - biBBox.min_y) * scale;
        svgContent += `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="none" stroke="#ef4444" stroke-width="1" stroke-dasharray="3,2" />`;
    }

    svg.innerHTML = svgContent;
}
</script>
