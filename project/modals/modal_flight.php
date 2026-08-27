<?php if (!defined('_GNUBOARD_')) exit; ?>

<!-- 1. 촬영일 등록 메인 모달 -->
<dialog id="modal_add_flight" class="modal z-[200]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-md">
        <h3 class="font-black text-lg mb-1 text-base-content flex items-center gap-2">
            🛫 촬영일 및 EO 데이터 등록
        </h3>
        <p class="text-xs text-base-content/60 mb-4">
            촬영 정보 및 EO 성과 파일을 등록하면 폴더와 DB 캐시가 동기화됩니다.
        </p>
        
        <form id="form_add_flight" method="post" action="action.php" enctype="multipart/form-data" class="space-y-3.5">
            <input type="hidden" name="action" value="add_flight_date">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            <input type="hidden" name="parsed_shots" id="input_parsed_shots" value="0">
            <input type="hidden" name="matched_blocks_str" id="input_matched_blocks" value="">
            
            <div class="form-control">
                <label class="label py-1"><span class="label-text font-bold text-xs">이름 (구분명)</span></label>
                <input type="text" name="flight_name" id="flight_name" placeholder="예: 1회차 정기촬영, 전라서부 1차" class="input input-bordered input-sm rounded-xl w-full text-xs font-medium" required>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="form-control">
                    <label class="label py-1"><span class="label-text font-bold text-xs">촬영일자</span></label>
                    <input type="date" name="flight_date" id="flight_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered input-sm rounded-xl w-full text-xs font-mono font-medium" required>
                </div>
                <div class="form-control">
                    <label class="label py-1"><span class="label-text font-bold text-xs">센서명</span></label>
                    <input type="text" name="sensor_name" id="sensor_name" placeholder="예: Leica CountryMapper, DMC III" list="sensor_presets" class="input input-bordered input-sm rounded-xl w-full text-xs font-medium" required>
                    <datalist id="sensor_presets">
                        <option value="Leica CountryMapper">
                        <option value="DMC III">
                        <option value="UltraCam Osprey 4.1">
                        <option value="UltraCam Eagle">
                    </datalist>
                </div>
            </div>

            <div class="form-control">
                <label class="label py-1 flex justify-between items-center">
                    <span class="label-text font-bold text-xs">EO 데이터 등록 (.txt / .xlsx / .xls)</span>
                    <span id="eo_status_badge" class="badge badge-ghost badge-sm text-[11px] font-mono text-base-content/60">미선택</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="file" id="eo_file_input" name="eo_file" accept=".txt,.xlsx,.xls,.tsv,.csv" class="hidden" onchange="handleEOFileSelect(this)">
                    <button type="button" class="btn btn-outline btn-primary btn-sm rounded-xl font-bold flex-1 gap-2 text-xs" onclick="document.getElementById('eo_file_input').click()">
                        <span>📁 EO 파일 선택</span>
                    </button>
                    <button type="button" id="btn_reopen_preview" class="btn btn-ghost btn-sm rounded-xl font-bold text-xs hidden" onclick="modal_eo_preview.showModal()">
                        <span>🔍 뷰어</span>
                    </button>
                </div>
            </div>

            <div id="detected_blocks_container" class="hidden bg-base-200/50 p-2.5 rounded-xl border border-base-content/5 space-y-1">
                <span class="text-[11px] font-bold text-base-content/70">🧩 감지된 연관 블럭:</span>
                <div id="detected_blocks_list" class="flex flex-wrap gap-1 mt-1"></div>
            </div>

            <div class="modal-action mt-6 pt-2 border-t border-base-content/10">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_add_flight.close()">취소</button>
                <button type="submit" id="btn_submit_flight" class="btn btn-primary btn-sm rounded-xl font-bold px-6">
                    <span>업로드 및 등록</span>
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 2. EO 데이터 파싱 & 엑셀 화면 프리뷰 모달 -->
<dialog id="modal_eo_preview" class="modal z-[220]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-5xl w-11/12 h-[85vh] flex flex-col p-6">
        
        <div class="flex justify-between items-center pb-3 border-b border-base-content/10">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📊</span>
                <div>
                    <h3 class="font-black text-lg text-base-content flex items-center gap-2">
                        <span>EO 데이터 검증 및 엑셀 뷰어</span>
                        <span id="preview_total_badge" class="badge badge-primary font-mono font-bold text-xs py-2 px-2.5">0 매</span>
                    </h3>
                    <p class="text-[11px] text-base-content/60" id="preview_filename">파일명: -</p>
                </div>
            </div>

            <div class="flex items-center gap-1.5" id="preview_matched_blocks"></div>
        </div>

        <div class="flex-1 overflow-auto custom-scrollbar my-4 rounded-xl border border-base-content/15 bg-base-200/30">
            <table class="table table-xs table-pin-rows table-pin-cols font-mono w-full text-center">
                <thead>
                    <tr class="bg-base-300 text-base-content font-bold border-b border-base-content/15 text-[11px]">
                        <th class="w-12 bg-base-300">No</th>
                        <th class="w-28 text-left bg-base-300 text-primary font-bold">ID (코스_사진)</th>
                        <th>X (Easting)</th>
                        <th>Y (Northing)</th>
                        <th>Z (Height)</th>
                        <th>OMEGA (ω)</th>
                        <th>PHI (φ)</th>
                        <th>KAPPA (κ)</th>
                        <th>위도 (Latitude)</th>
                        <th>경도 (Longitude)</th>
                    </tr>
                </thead>
                <tbody id="eo_preview_tbody" class="divide-y divide-base-content/5 text-[11px]"></tbody>
            </table>
        </div>

        <div class="flex justify-between items-center pt-2 border-t border-base-content/10">
            <span class="text-xs text-base-content/60 font-medium" id="preview_footer_desc">
                💡 ID의 앞 4자리 코스 번호를 기준으로 프로젝트 블럭과 자동 매칭되었습니다.
            </span>
            <div class="flex gap-2">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_eo_preview.close()">취소</button>
                <button type="button" class="btn btn-primary btn-sm rounded-xl font-bold px-6 shadow-md shadow-primary/25" onclick="applyEODataToForm()">
                    <span>입력 완료 (적용) 🚀</span>
                </button>
            </div>
        </div>

    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 3. 검수내역 입력 모달 -->
<dialog id="modal_flight_inspect" class="modal z-[210]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-sm">
        <h3 class="font-black text-lg mb-1 text-base-content flex items-center gap-2">
            ✍️ 촬영 검수내역 입력
        </h3>
        <p class="text-xs text-base-content/60 mb-4 font-mono" id="inspect_target_date">촬영일: -</p>
        
        <form method="post" action="action.php" class="space-y-3">
            <input type="hidden" name="action" value="update_flight_inspect">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            <input type="hidden" name="date_id" id="inspect_date_id" value="">

            <div class="form-control">
                <label class="label py-1"><span class="label-text font-bold text-xs">전체 취득 매수</span></label>
                <input type="number" id="inspect_total_shots" class="input input-bordered input-sm rounded-xl font-mono text-xs bg-base-200/50" readonly>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="form-control">
                    <label class="label py-1"><span class="label-text font-bold text-xs text-success">정상 사용 매수</span></label>
                    <input type="number" name="used_shots" id="inspect_used_shots" min="0" class="input input-bordered input-sm rounded-xl font-mono text-xs focus:input-success font-bold" required oninput="calcReshootShots()">
                </div>
                <div class="form-control">
                    <label class="label py-1"><span class="label-text font-bold text-xs text-warning">재촬영(결손) 매수</span></label>
                    <input type="number" name="reshoot_shots" id="inspect_reshoot_shots" min="0" class="input input-bordered input-sm rounded-xl font-mono text-xs focus:input-warning font-bold" required>
                </div>
            </div>

            <div class="modal-action mt-5 pt-2 border-t border-base-content/10">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_flight_inspect.close()">취소</button>
                <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold px-5">저장하기</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 4. 촬영일 삭제 확인 모달 -->
<dialog id="modal_confirm_delete_flight" class="modal z-[210]">
    <div class="modal-box bg-base-100 border border-error/20 shadow-2xl rounded-2xl max-w-sm text-center">
        <div class="w-14 h-14 rounded-full bg-error/10 text-error flex items-center justify-center text-2xl mx-auto mb-3">
            ⚠️
        </div>
        <h3 class="font-black text-lg text-base-content">선택한 촬영일을 삭제합니까?</h3>
        <div class="text-xs text-base-content/70 mt-2 space-y-1">
            <p>DB 기록뿐만 아니라 <strong class="text-error font-bold">E 드라이브의 실제 촬영일 폴더(EO, INDEX, 문서)</strong>까지 완전히 영구 삭제됩니다.</p>
            <div id="delete_flight_target_list" class="badge badge-ghost mt-2 py-2 px-3 font-mono text-xs"></div>
        </div>
        <div class="modal-action justify-center gap-2 mt-6">
            <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_confirm_delete_flight.close()">취소</button>
            <button type="button" class="btn btn-error btn-sm rounded-xl font-bold px-6 text-white" onclick="executeFlightDelete()">영구 삭제</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
var currentParsedEO = [];
var currentMatchedBlocks = [];
var isBlockRegistered = true;

function handleEOFileSelect(input) {
    const file = input.files[0];
    if (!file) return;

    document.getElementById('preview_filename').innerText = `파일명: ${file.name}`;
    const reader = new FileReader();
    const fileName = file.name.toLowerCase();
    const isExcel = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');

    if (isExcel) {
        if (typeof XLSX === 'undefined') {
            alert('엑셀 파싱 라이브러리가 로드되지 않았습니다.');
            return;
        }
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                processRawData(jsonData);
            } catch(err) {
                alert('엑셀 파싱 오류: ' + err.message);
            }
        };
        reader.readAsArrayBuffer(file);
    } else {
        reader.onload = function(e) {
            try {
                const text = e.target.result;
                const lines = text.split(/\r\n|\n|\r/);
                const rawData = lines.map(line => line.trim().split(/\t|,|\s+/).filter(v => v !== ''));
                processRawData(rawData);
            } catch(err) {
                alert('텍스트 파싱 오류: ' + err.message);
            }
        };
        reader.readAsText(file, 'EUC-KR');
    }
}

function processRawData(rows) {
    currentParsedEO = [];
    const courseSet = new Set();

    rows.forEach(row => {
        if (!row || row.length < 9) return;
        
        const id = String(row[0]).trim();
        if (!id || id.toLowerCase().includes('id') || id.toLowerCase().includes('photo')) return;

        let courseNo = 0;
        if (id.includes('_')) {
            courseNo = parseInt(id.split('_')[0], 10);
        } else if (id.length >= 8) {
            courseNo = parseInt(id.substring(0, 4), 10);
        }

        if (!isNaN(courseNo) && courseNo > 0) {
            courseSet.add(courseNo);
        }

        currentParsedEO.push({
            id: id,
            x: row[1] || '-',
            y: row[2] || '-',
            z: row[3] || '-',
            omega: row[5] || row[4] || '-',
            phi: row[6] || row[5] || '-',
            kappa: row[7] || row[6] || '-',
            lat: row[8] || row[7] || '-',
            lon: row[9] || row[8] || '-'
        });
    });

    if (currentParsedEO.length === 0) {
        alert('유효한 데이터 행이 없습니다.');
        return;
    }

    currentMatchedBlocks = [];
    const detectedCourses = Array.from(courseSet);
    const blockList = (typeof PROJECT_BLOCKS !== 'undefined' && Array.isArray(PROJECT_BLOCKS)) ? PROJECT_BLOCKS : [];

    if (blockList.length === 0) {
        isBlockRegistered = false;
    } else {
        isBlockRegistered = true;
        blockList.forEach(blk => {
            if (!blk.line_list) return;
            const blkCourses = blk.line_list.split(',').map(n => parseInt(n.trim(), 10));
            const intersection = detectedCourses.filter(c => blkCourses.includes(c));
            if (intersection.length > 0) {
                currentMatchedBlocks.push({
                    name: blk.name || blk.block_name,
                    range: blk.range || blk.line_range,
                    matchedCoursesCount: intersection.length
                });
            }
        });
    }

    renderEOPreviewTable();
    const previewModal = document.getElementById('modal_eo_preview');
    if (previewModal) previewModal.showModal();
}

function renderEOPreviewTable() {
    const tbody = document.getElementById('eo_preview_tbody');
    tbody.innerHTML = '';
    document.getElementById('preview_total_badge').innerText = `${currentParsedEO.length.toLocaleString()} 매`;

    const blockBadgeBox = document.getElementById('preview_matched_blocks');
    blockBadgeBox.innerHTML = '';

    if (!isBlockRegistered) {
        blockBadgeBox.innerHTML = `<span class="badge badge-warning text-xs font-bold py-2 px-3">⚠️ 등록된 블럭이 없어서 0 으로 통일됩니다</span>`;
    } else if (currentMatchedBlocks.length > 0) {
        currentMatchedBlocks.forEach(b => {
            blockBadgeBox.innerHTML += `<span class="badge badge-accent font-mono font-bold text-xs py-2 px-2.5">🧩 ${b.name} (${b.range})</span>`;
        });
    } else {
        blockBadgeBox.innerHTML = `<span class="badge badge-ghost text-xs">일치 블럭 없음</span>`;
    }

    const renderLimit = Math.min(currentParsedEO.length, 300);
    for (let i = 0; i < renderLimit; i++) {
        const item = currentParsedEO[i];
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-base-200/50';
        tr.innerHTML = `
            <td class="text-base-content/50">${i + 1}</td>
            <td class="text-left font-bold text-primary">${item.id}</td>
            <td>${item.x}</td>
            <td>${item.y}</td>
            <td>${item.z}</td>
            <td>${item.omega}</td>
            <td>${item.phi}</td>
            <td>${item.kappa}</td>
            <td>${item.lat}</td>
            <td>${item.lon}</td>
        `;
        tbody.appendChild(tr);
    }
}

function applyEODataToForm() {
    modal_eo_preview.close();

    const count = currentParsedEO.length;
    document.getElementById('input_parsed_shots').value = count;

    const eoBadge = document.getElementById('eo_status_badge');
    eoBadge.className = 'badge badge-success font-mono font-bold text-xs text-white py-2 px-2.5';
    eoBadge.innerText = `✅ ${count.toLocaleString()}매 입력됨`;

    const blockContainer = document.getElementById('detected_blocks_container');
    const blockList = document.getElementById('detected_blocks_list');
    blockList.innerHTML = '';

    if (!isBlockRegistered) {
        document.getElementById('input_matched_blocks').value = '등록된 블럭이 없어서 0 으로 통일됩니다';
        blockList.innerHTML = `<span class="badge badge-warning text-xs font-bold py-1 px-2.5">등록된 블럭이 없어서 0 으로 통일됩니다</span>`;
        blockContainer.classList.remove('hidden');
    } else if (currentMatchedBlocks.length > 0) {
        const blockNames = currentMatchedBlocks.map(b => b.name).join(', ');
        document.getElementById('input_matched_blocks').value = blockNames;
        currentMatchedBlocks.forEach(b => {
            blockList.innerHTML += `<span class="badge badge-neutral badge-sm font-mono">${b.name} (${b.range})</span>`;
        });
        blockContainer.classList.remove('hidden');
    } else {
        document.getElementById('input_matched_blocks').value = '미매칭';
        blockContainer.classList.add('hidden');
    }

    document.getElementById('btn_reopen_preview').classList.remove('hidden');
}

function openFlightInspectModal(dateId, dateStr, total, used, reshoot) {
    document.getElementById('inspect_date_id').value = dateId;
    document.getElementById('inspect_target_date').innerText = `촬영일자: ${dateStr}`;
    document.getElementById('inspect_total_shots').value = total;
    document.getElementById('inspect_used_shots').value = used;
    document.getElementById('inspect_reshoot_shots').value = reshoot;
    modal_flight_inspect.showModal();
}

function calcReshootShots() {
    const total = parseInt(document.getElementById('inspect_total_shots').value, 10) || 0;
    const used = parseInt(document.getElementById('inspect_used_shots').value, 10) || 0;
    const reshootInput = document.getElementById('inspect_reshoot_shots');
    if (total >= used) {
        reshootInput.value = total - used;
    }
}

function confirmFlightDelete() {
    const checkedItems = document.querySelectorAll('.chk-flight-item:checked');
    if (checkedItems.length === 0) {
        triggerToast('삭제할 촬영일을 먼저 선택해주세요.', 'warning', '⚠️');
        return;
    }
    const dates = Array.from(checkedItems).map(i => i.dataset.date).join(', ');
    document.getElementById('delete_flight_target_list').innerText = `대상: ${dates}`;
    modal_confirm_delete_flight.showModal();
}

function executeFlightDelete() {
    const form = document.getElementById('form_flight_delete');
    if (form) {
        form.submit();
    }
}

function executeFlightDelete() {
    document.getElementById('form_flight_delete').submit();
}
</script>