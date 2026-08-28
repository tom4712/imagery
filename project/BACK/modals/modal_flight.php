<?php if (!defined('_GNUBOARD_')) exit; ?>

<!-- 1. 신규 촬영일 및 EO 등록 모달 -->
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

<!-- 2. [더블클릭 시 호출] EO 폴더 내 단일 파일 선택 모달 -->
<dialog id="modal_eo_file_picker" class="modal z-[210]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-lg p-6 flex flex-col max-h-[80vh]">
        <div class="flex justify-between items-center pb-3 border-b border-base-content/10">
            <div>
                <h3 class="font-black text-lg text-base-content flex items-center gap-2">
                    <span>📁 EO 성과 파일 선택</span>
                    <span id="picker_flight_date" class="badge badge-primary font-mono text-xs font-bold"></span>
                </h3>
                <p class="text-xs text-base-content/60 mt-0.5">검증하고 반영할 EO 성과 파일 한 개를 선택하세요.</p>
            </div>
            <button type="button" class="btn btn-sm btn-circle btn-ghost" onclick="modal_eo_file_picker.close()">✕</button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar my-4 rounded-xl border border-base-content/10 bg-base-200/30">
            <table class="table table-xs w-full text-center select-none">
                <thead class="bg-base-200 text-base-content font-bold sticky top-0 z-10 text-[11px]">
                    <tr>
                        <th class="w-12">선택</th>
                        <th class="text-left px-3">파일명</th>
                        <th class="w-36">수정일자</th>
                        <th class="w-20">크기</th>
                        <th class="w-16">상태</th>
                    </tr>
                </thead>
                <tbody id="eo_picker_tbody" class="divide-y divide-base-content/5 text-xs font-mono">
                    <tr><td colspan="5" class="py-10 text-base-content/40 font-bold">파일을 불러오는 중...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center pt-2 border-t border-base-content/10">
            <span class="text-xs text-base-content/60">💡 한 번에 한 개 파일만 검증·반영할 수 있습니다.</span>
            <div class="flex gap-2">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_eo_file_picker.close()">닫기</button>
                <button type="button" id="btn_proceed_eo_preview" class="btn btn-primary btn-sm rounded-xl font-bold px-5" onclick="proceedToEOPreview()" disabled>
                    <span id="btn_proceed_text">파일 검증하기 🔍</span>
                </button>
            </div>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 3. EO 데이터 검증 & 엑셀 화면 프리뷰 모달 -->
<dialog id="modal_eo_preview" class="modal z-[220]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-5xl w-11/12 h-[85vh] flex flex-col p-6">
        <div class="flex justify-between items-center pb-3 border-b border-base-content/10">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📊</span>
                <div class="max-w-[400px]">
                    <h3 class="font-black text-lg text-base-content flex items-center gap-2">
                        <span>EO 데이터 검증 및 엑셀 뷰어</span>
                        <span id="preview_total_badge" class="badge badge-primary font-mono font-bold text-xs py-2 px-2.5">0 매</span>
                    </h3>
                    <p class="text-[11px] text-base-content/60 truncate" id="preview_filename">파일명: -</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap justify-end" id="preview_matched_blocks"></div>
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
                        <th>내역</th>
                    </tr>
                </thead>
                <tbody id="eo_preview_tbody" class="divide-y divide-base-content/5 text-[11px]"></tbody>
            </table>
        </div>

        <form id="form_apply_eo_to_db" method="post" action="action.php">
            <input type="hidden" name="action" value="apply_selected_eo_file">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            <input type="hidden" name="date_id" id="apply_eo_date_id" value="">
            <input type="hidden" name="filename" id="apply_eo_filename" value="">
            <input type="hidden" name="parsed_shots" id="apply_eo_shots" value="0">
            <input type="hidden" name="matched_blocks" id="apply_eo_matched_blocks" value="">
        </form>

        <div class="flex justify-between items-center pt-2 border-t border-base-content/10">
            <span class="text-xs text-base-content/60 font-medium" id="preview_footer_desc">
                💡 ID의 앞 4자리 코스 번호를 기준으로 프로젝트 블럭과 자동 매칭되었습니다.
            </span>
            <div class="flex gap-2">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_eo_preview.close()">취소</button>
                <button type="button" id="btn_confirm_apply_eo" class="btn btn-primary btn-sm rounded-xl font-bold px-6 shadow-md shadow-primary/25" onclick="executeEOApply()">
                    <span>이 파일로 활성화 및 반영 🚀</span>
                </button>
            </div>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 4. 검수내역 입력 모달 -->
<dialog id="modal_flight_inspect" class="modal z-[210]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-6xl w-11/12 h-[86vh] flex flex-col p-6">
        <div class="flex justify-between items-center pb-3 border-b border-base-content/10">
            <div>
                <h3 class="font-black text-lg text-base-content flex items-center gap-2">
                    ✍️ 촬영 검수내역 입력
                </h3>
                <p class="text-xs text-base-content/60 mt-0.5 font-mono" id="inspect_target_date">촬영일: -</p>
            </div>
            <div class="grid grid-cols-4 gap-2 text-center min-w-[420px]">
                <div class="bg-base-200/60 rounded-xl px-3 py-2 border border-base-content/10">
                    <p class="text-[10px] font-bold text-base-content/50">전체</p>
                    <p class="font-mono font-black text-primary" id="inspect_summary_total">0</p>
                </div>
                <div class="bg-base-200/60 rounded-xl px-3 py-2 border border-base-content/10">
                    <p class="text-[10px] font-bold text-base-content/50">사용</p>
                    <p class="font-mono font-black text-success" id="inspect_summary_used">0</p>
                </div>
                <div class="bg-base-200/60 rounded-xl px-3 py-2 border border-base-content/10">
                    <p class="text-[10px] font-bold text-base-content/50">재촬영</p>
                    <p class="font-mono font-black text-error" id="inspect_summary_reshoot">0</p>
                </div>
                <div class="bg-base-200/60 rounded-xl px-3 py-2 border border-base-content/10">
                    <p class="text-[10px] font-bold text-base-content/50">중복미사용</p>
                    <p class="font-mono font-black text-success" id="inspect_summary_duplicate">0</p>
                </div>
            </div>
        </div>

        <form id="form_flight_inspect" method="post" action="action.php" class="flex-1 min-h-0 flex flex-col" onsubmit="event.preventDefault(); submitFlightInspection(); return false;">
            <input type="hidden" name="action" value="apply_flight_inspection">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            <input type="hidden" name="date_id" id="inspect_date_id" value="">
            <input type="hidden" name="inspection_json" id="inspect_payload" value="[]">
            <input type="hidden" name="manual_reason" id="inspect_manual_reason" value="">

            <div class="grid grid-cols-[1fr_340px] gap-4 flex-1 min-h-0 py-4">
                <div class="rounded-xl border border-base-content/10 bg-base-200/20 overflow-hidden flex flex-col min-h-0">
                    <div class="px-3 py-2 border-b border-base-content/10 flex items-center justify-between">
                        <span class="text-xs font-bold text-base-content/70">EO 사진번호</span>
                        <span class="text-[11px] text-base-content/50 font-mono" id="inspect_eo_filename">EO: -</span>
                    </div>
                    <div class="overflow-auto custom-scrollbar flex-1 min-h-0">
                        <table class="table table-xs table-pin-rows w-full text-center">
                            <thead>
                                <tr class="bg-base-200 text-[11px]">
                                    <th class="w-14">No</th>
                                    <th class="w-40 text-left">사진번호</th>
                                    <th class="w-24">코스</th>
                                    <th class="w-32">구분</th>
                                    <th class="text-left">사유</th>
                                </tr>
                            </thead>
                            <tbody id="inspect_eo_tbody" class="text-xs font-mono divide-y divide-base-content/5">
                                <tr><td colspan="5" class="py-20 text-base-content/40 font-bold">EO를 불러오는 중...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-base-content/10 bg-base-200/20 p-3 flex flex-col min-h-0">
                    <div role="tablist" class="tabs tabs-boxed bg-base-300/50 p-1 rounded-xl mb-3">
                        <button type="button" id="inspect_tab_manual" class="tab tab-active text-xs font-bold" onclick="switchInspectInputMode('manual')">수동</button>
                        <button type="button" id="inspect_tab_list" class="tab text-xs font-bold" onclick="switchInspectInputMode('list')">리스트</button>
                    </div>

                    <div id="inspect_panel_manual" class="space-y-3">
                        <div class="form-control">
                            <label class="label py-1"><span class="label-text font-bold text-xs">선택 사유</span></label>
                            <select id="inspect_reason_select" name="inspect_reason_select" class="select select-bordered select-sm rounded-xl text-xs font-bold" onchange="toggleCustomInspectReason()">
                                <option value="직접입력">직접입력</option>
                                <option value="구름">구름</option>
                                <option value="그림자">그림자</option>
                                <option value="연무">연무</option>
                                <option value="빛반사">빛반사</option>
                                <option value="중복미사용">중복미사용</option>
                            </select>
                        </div>
                        <div class="form-control">
                            <label class="label py-1"><span class="label-text font-bold text-xs">직접 입력 사유</span></label>
                            <input type="text" id="inspect_custom_reason" name="inspect_custom_reason" class="input input-bordered input-sm rounded-xl text-xs" placeholder="사유 입력">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm rounded-xl font-bold w-full" onclick="applyManualInspectionReason()">선택 항목에 적용</button>
                        <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold w-full" onclick="clearManualInspectionReason()">선택 항목 해제</button>
                    </div>

                    <div id="inspect_panel_list" class="hidden flex-1 min-h-0 flex flex-col">
                        <label class="label py-1"><span class="label-text font-bold text-xs">리스트 붙여넣기</span></label>
                        <textarea id="inspect_bulk_text" class="textarea textarea-bordered rounded-xl text-xs font-mono flex-1 min-h-[260px]" placeholder="사진번호	사유&#10;0001_0009	구름&#10;0001_0015	중복미사용"></textarea>
                        <button type="button" class="btn btn-primary btn-sm rounded-xl font-bold mt-3" onclick="applyBulkInspectionList()">EO와 매칭하기</button>
                    </div>

                    <div class="mt-auto pt-3 border-t border-base-content/10 text-[11px] text-base-content/60 leading-relaxed">
                        재촬영 사유는 빨간색, 같은 코스 내 앞뒤 3장은 하늘색, 중복미사용은 초록색으로 표시됩니다.
                    </div>
                </div>
            </div>

            <div class="modal-action mt-0 pt-3 border-t border-base-content/10">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_flight_inspect.close()">취소</button>
                <button type="button" class="btn btn-primary btn-sm rounded-xl font-bold px-6" onclick="submitFlightInspection()">검수완료 EO 생성</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 5. 중복영상 확인 모달 -->
<dialog id="modal_duplicate_video" class="modal z-[215]">
    <div class="modal-box bg-base-100 border border-info/20 shadow-2xl rounded-2xl max-w-7xl w-[96vw] h-[86vh] flex flex-col p-4">
        <div class="flex items-center justify-between gap-3 pb-3 border-b border-base-content/10">
            <div class="min-w-0">
                <h3 class="font-black text-base text-base-content flex items-center gap-2">
                    <i class="fa-solid fa-code-compare text-info"></i>
                    <span>중복영상 확인</span>
                    <span id="duplicate_video_count_badge" class="badge badge-info font-mono font-bold text-[11px] h-6 px-2">0건</span>
                </h3>
                <p class="text-[11px] text-base-content/60 mt-0.5 font-mono truncate" id="duplicate_video_target_date">촬영일: -</p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" class="btn btn-xs btn-outline rounded-lg font-bold" onclick="selectDuplicateKeepMode('current')">현재 일자 선택</button>
                <button type="button" class="btn btn-xs btn-outline rounded-lg font-bold" onclick="selectDuplicateKeepMode('oldest')">가장 빠른 일자</button>
                <button type="button" class="btn btn-xs btn-outline rounded-lg font-bold" onclick="selectDuplicateKeepMode('newest')">가장 늦은 일자</button>
            </div>
        </div>

        <div id="duplicate_video_empty" class="hidden flex-1 items-center justify-center text-center">
            <div>
                <div class="w-14 h-14 rounded-full bg-success/10 text-success flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-check text-2xl"></i>
                </div>
                <p class="font-black text-base text-base-content">중복영상이 없습니다.</p>
                <p class="text-xs text-base-content/60 mt-1">이미 재촬영/중복미사용 처리된 항목은 검사에서 제외했습니다.</p>
            </div>
        </div>

        <form id="form_duplicate_video_apply" method="post" action="action.php" class="flex-1 min-h-0 flex flex-col">
            <input type="hidden" name="action" value="apply_duplicate_video_selection">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            <input type="hidden" name="selection_json" id="duplicate_video_payload" value="">

            <div class="flex items-center justify-between gap-3 py-2 text-[11px] text-base-content/60">
                <div class="flex items-center gap-3 min-w-0">
                    <span>사용할 촬영일 하나를 고르면 나머지는 <strong class="text-success">중복미사용</strong>으로 반영됩니다.</span>
                    <span class="hidden md:inline">이미 재촬영/중복미사용 처리된 행은 제외됩니다.</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="rounded-lg border border-base-content/10 bg-base-200/40 px-2.5 py-1">
                        <span class="text-base-content/50">중복</span>
                        <span class="font-mono font-black text-info ml-1" id="duplicate_video_group_count">0</span>
                    </div>
                    <div class="rounded-lg border border-base-content/10 bg-base-200/40 px-2.5 py-1">
                        <span class="text-base-content/50">반영</span>
                        <span class="font-mono font-black text-warning ml-1" id="duplicate_video_apply_count">0</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-base-content/10 bg-base-200/20 overflow-hidden flex flex-col min-h-0 flex-1">
                <div class="grid grid-cols-[132px_1fr] gap-2 px-3 py-2 border-b border-base-content/10 bg-base-200/50 text-[11px] font-bold text-base-content/60">
                    <span>사진번호</span>
                    <span>사용할 촬영일 선택</span>
                </div>
                <div id="duplicate_video_list" class="overflow-auto custom-scrollbar flex-1 min-h-0 divide-y divide-base-content/5"></div>
            </div>

            <div class="modal-action mt-0 pt-3 border-t border-base-content/10">
                <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_duplicate_video.close()">취소</button>
                <button type="button" id="btn_apply_duplicate_video" class="btn btn-info btn-sm rounded-xl font-bold px-6 text-info-content" onclick="submitDuplicateVideoSelection()">반영하기</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 5. 촬영일 삭제 확인 모달 -->
<dialog id="modal_confirm_delete_flight" class="modal z-[210]">
    <div class="modal-box bg-base-100 border border-error/20 shadow-2xl rounded-2xl max-w-sm text-center">
        <div class="w-14 h-14 rounded-full bg-error/10 text-error flex items-center justify-center text-2xl mx-auto mb-3">⚠️</div>
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

<!-- 6. 촬영기록부 / 코스별검사표 통합 문서 관리 모달 -->
<dialog id="modal_doc_manager" class="modal z-[210]">
    <div class="modal-box bg-slate-900 border border-white/10 max-w-3xl rounded-2xl shadow-2xl p-6">
        <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-4">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">📋</span>
                <div>
                    <h3 class="font-bold text-base text-white">비행 성과 문서 관리</h3>
                    <p class="text-xs text-slate-400" id="doc_modal_flight_info">-</p>
                </div>
            </div>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost text-slate-400 hover:text-white">✕</button>
            </form>
        </div>

        <div class="bg-slate-950/60 p-3.5 rounded-xl border border-white/5 mb-4 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                    <span>✨</span><span>템플릿 기반 신규 문서 생성</span>
                </span>
                <div class="join">
                    <button type="button" id="tab_btn_flight_log" class="btn btn-xs join-item font-bold btn-primary" onclick="setNewDocType('flight_log')">촬영기록부</button>
                    <button type="button" id="tab_btn_course_inspect" class="btn btn-xs join-item font-bold btn-ghost text-slate-400" onclick="setNewDocType('course_inspect')">코스별검사표</button>
                </div>
            </div>
            <div class="flex gap-2">
                <input type="text" id="input_new_doc_name" class="input input-bordered input-sm rounded-xl flex-1 text-xs font-mono text-cyan-300 bg-slate-900 border-white/10" placeholder="생성할 엑셀 파일명 입력">
                <button type="button" class="btn btn-sm btn-primary rounded-xl font-bold text-xs px-4" onclick="createAndOpenDoc()">
                    <i class="fa-solid fa-plus"></i>
                    <span>생성 및 편집</span>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between mb-3">
            <div class="tabs tabs-boxed bg-slate-950/80 p-1 border border-white/5 rounded-xl">
                <button type="button" class="tab tab-xs font-bold tab-active doc-filter-tab" onclick="filterDocList('ALL', this)">전체</button>
                <button type="button" class="tab tab-xs font-bold text-cyan-400 doc-filter-tab" onclick="filterDocList('촬영기록부', this)">촬영기록부</button>
                <button type="button" class="tab tab-xs font-bold text-amber-400 doc-filter-tab" onclick="filterDocList('코스별검사표', this)">코스별검사표</button>
            </div>
            <span class="text-[11px] text-slate-500 font-mono" id="doc_total_count">총 0건</span>
        </div>

        <div class="overflow-x-auto min-h-[160px] max-h-[300px] custom-scrollbar rounded-xl border border-white/5 bg-slate-950/40">
            <table class="table table-sm w-full">
                <thead>
                    <tr class="text-slate-400 text-xs border-b border-white/10 bg-slate-950/80 sticky top-0">
                        <th class="w-28 text-center">문서 구분</th>
                        <th>파일명</th>
                        <th class="w-20">용량</th>
                        <th class="w-32">수정일시</th>
                        <th class="w-32 text-right">관리</th>
                    </tr>
                </thead>
                <tbody id="doc_manager_tbody">
                    <tr>
                        <td colspan="5" class="text-center py-10 text-slate-500 text-xs">
                            <span class="loading loading-spinner loading-sm"></span> 문서 목록을 불러오는 중...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="modal-action border-t border-white/10 pt-4 mt-4">
            <form method="dialog">
                <button class="btn btn-sm btn-ghost rounded-xl text-xs font-bold text-slate-300">닫기</button>
            </form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 7. 문서 안내/오류 모달 -->
<dialog id="modal_doc_notice" class="modal z-[230]">
    <div class="modal-box bg-slate-900 border border-white/10 shadow-2xl rounded-3xl max-w-sm text-center p-6">
        <div id="doc_notice_icon" class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl mx-auto mb-3">ℹ️</div>
        <h3 id="doc_notice_title" class="font-black text-base text-white">문서 안내</h3>
        <p id="doc_notice_message" class="text-xs text-slate-300 mt-2 leading-relaxed break-words">-</p>
        <div class="modal-action justify-center mt-5">
            <button type="button" class="btn btn-sm btn-primary rounded-xl font-bold px-6 text-xs" onclick="modal_doc_notice.close()">확인</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- 8. 문서 삭제 확인 모달 -->
<dialog id="modal_confirm_delete_doc" class="modal z-[231]">
    <div class="modal-box bg-slate-900 border border-error/30 shadow-2xl rounded-3xl max-w-sm text-center p-6">
        <div class="w-14 h-14 rounded-2xl bg-error/10 text-error flex items-center justify-center text-2xl mx-auto mb-3">🗑️</div>
        <h3 class="font-black text-base text-white">문서를 삭제하시겠습니까?</h3>
        <p class="text-xs text-slate-400 mt-1">삭제된 문서는 복구할 수 없습니다.</p>
        <div class="bg-slate-950/70 rounded-xl p-3 my-4 border border-white/5">
            <span id="display_delete_doc_filename" class="font-mono font-bold text-xs text-rose-300 break-all">-</span>
        </div>
        <div class="modal-action justify-center gap-2 mt-4">
            <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold text-xs" onclick="modal_confirm_delete_doc.close()">취소</button>
            <button type="button" class="btn btn-error btn-sm rounded-xl font-bold px-6 text-white text-xs" onclick="executeDocumentDelete()">영구 삭제</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
// --- 기존 문서 및 검수 관리 스크립트 그대로 유지 ---
let currentDocPrjId = 0;
let currentDocDateId = 0;
let currentDocFlightDate = '';
let currentDocPrjName = '';
let currentNewDocType = 'flight_log';
let loadedDocDataList = [];
let currentFilterType = 'ALL';
let pendingDeleteDocFilename = '';

function showDocumentNotice(title, message, type = 'info') {
    const modal = document.getElementById('modal_doc_notice');
    if (!modal) return;
    const styles = {
        success: ['✅', 'bg-emerald-500/10 text-emerald-400'],
        error: ['⚠️', 'bg-rose-500/10 text-rose-400'],
        warning: ['⚠️', 'bg-amber-500/10 text-amber-400'],
        info: ['ℹ️', 'bg-primary/10 text-primary']
    };
    const [icon, className] = styles[type] || styles.info;
    const iconBox = document.getElementById('doc_notice_icon');
    iconBox.textContent = icon;
    iconBox.className = `w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-3 ${className}`;
    document.getElementById('doc_notice_title').textContent = title;
    document.getElementById('doc_notice_message').textContent = message;
    modal.showModal();
}

function openDocManagerModal(prjId, dateId, flightDate, prjName) {
    currentDocPrjId = prjId;
    currentDocDateId = dateId;
    currentDocFlightDate = flightDate || '';
    currentDocPrjName = prjName || '';

    document.getElementById('doc_modal_flight_info').innerText = `촬영일자: ${flightDate} | 사업명: ${prjName}`;
    setNewDocType('flight_log');

    const modal = document.getElementById('modal_doc_manager');
    if (modal) modal.showModal();

    loadDocumentList(prjId, dateId, flightDate, prjName);
}

function setNewDocType(type) {
    currentNewDocType = type;
    const btnLog = document.getElementById('tab_btn_flight_log');
    const btnCourse = document.getElementById('tab_btn_course_inspect');
    const inputName = document.getElementById('input_new_doc_name');
    const cleanDate = (currentDocFlightDate || '').replace(/-/g, '');

    if (type === 'flight_log') {
        btnLog.className = 'btn btn-xs join-item font-bold btn-primary';
        btnCourse.className = 'btn btn-xs join-item font-bold btn-ghost text-slate-400';
        inputName.value = `촬영기록부_${cleanDate}.xlsx`;
    } else {
        btnLog.className = 'btn btn-xs join-item font-bold btn-ghost text-slate-400';
        btnCourse.className = 'btn btn-xs join-item font-bold btn-warning';
        inputName.value = `코스별검사표_${cleanDate}.xlsx`;
    }
}

async function createAndOpenDoc() {
    const rawName = document.getElementById('input_new_doc_name').value.trim();
    if (!rawName) {
        showDocumentNotice('파일명을 입력해주세요', '생성할 엑셀 파일명을 입력한 뒤 다시 시도해 주세요.', 'warning');
        return;
    }

    const filename = rawName.endsWith('.xlsx') ? rawName : rawName + '.xlsx';

    try {
        const res = await fetch(`action.php?action=create_doc_from_template&prj_id=${currentDocPrjId}&date_id=${currentDocDateId}&doc_type=${currentNewDocType}&filename=${encodeURIComponent(filename)}&flight_date=${encodeURIComponent(currentDocFlightDate)}&prj_name=${encodeURIComponent(currentDocPrjName)}`);
        const result = await res.json();

        if (!result.success) {
            showDocumentNotice('문서 생성 실패', result.message || '문서를 생성하지 못했습니다.', 'error');
            return;
        }

        location.href = `doc_editor.php?prj_id=${currentDocPrjId}&date_id=${currentDocDateId}&filename=${encodeURIComponent(filename)}`;
    } catch (err) {
        showDocumentNotice('통신 오류', '문서 생성 요청을 처리하지 못했습니다. 잠시 후 다시 시도해 주세요.', 'error');
    }
}

async function loadDocumentList(prjId, dateId, flightDate, prjName) {
    const tbody = document.getElementById('doc_manager_tbody');
    tbody.innerHTML = `<tr><td colspan="5" class="text-center py-10 text-slate-500 text-xs"><span class="loading loading-spinner loading-sm"></span> 문서 폴더를 스캔하는 중...</td></tr>`;

    const targetDate = flightDate || currentDocFlightDate;
    const targetPrjName = prjName || currentDocPrjName;

    try {
        const res = await fetch(`./ajax_get_doc_list.php?prj_id=${prjId}&date_id=${dateId}&flight_date=${encodeURIComponent(targetDate)}&prj_name=${encodeURIComponent(targetPrjName)}`);
        if (!res.ok) throw new Error(`HTTP ${res.status} (${res.statusText})`);

        const result = await res.json();
        if (result.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-10 text-rose-400 font-bold text-xs">${result.message}</td></tr>`;
            return;
        }

        loadedDocDataList = result.data || [];
        renderFilteredDocs();
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-10 text-rose-400 font-bold text-xs">${err.message}</td></tr>`;
    }
}

function filterDocList(type, btn) {
    currentFilterType = type;
    document.querySelectorAll('.doc-filter-tab').forEach(t => t.classList.remove('tab-active'));
    if (btn) btn.classList.add('tab-active');
    renderFilteredDocs();
}

function renderFilteredDocs() {
    const tbody = document.getElementById('doc_manager_tbody');
    let list = loadedDocDataList;

    if (currentFilterType !== 'ALL') {
        list = list.filter(d => d.doc_type === currentFilterType);
    }

    document.getElementById('doc_total_count').innerText = `총 ${list.length}건`;

    if (list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-10 text-slate-500 font-bold text-xs">문서가 없습니다. 상단에서 새로 생성해 주세요.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    list.forEach(doc => {
        let badgeHtml = `<span class="badge badge-neutral badge-xs font-bold py-2 px-2 text-slate-400">기타</span>`;
        let editorUrl = `./doc_editor.php?prj_id=${currentDocPrjId}&date_id=${currentDocDateId}&filename=${encodeURIComponent(doc.filename)}`;

        if (doc.doc_type === '촬영기록부') {
            badgeHtml = `<span class="badge badge-info badge-xs font-bold py-2 px-2 text-cyan-300 bg-cyan-950 border-cyan-800">촬영기록부</span>`;
        } else if (doc.doc_type === '코스별검사표') {
            badgeHtml = `<span class="badge badge-warning badge-xs font-bold py-2 px-2 text-amber-300 bg-amber-950 border-amber-800">코스별검사표</span>`;
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-800/40 border-b border-white/5';
        tr.innerHTML = `
            <td class="text-center">${badgeHtml}</td>
            <td class="font-mono text-xs font-bold text-white flex items-center gap-2 py-3">
                <span class="text-emerald-400">📊</span>
                <span>${doc.filename}</span>
            </td>
            <td class="text-xs text-slate-400 font-mono">${doc.filesize}</td>
            <td class="text-xs text-slate-400 font-mono">${doc.updated_at}</td>
            <td class="text-right">
                <div class="flex items-center justify-end gap-1.5">
                    <a href="${editorUrl}" class="btn btn-xs btn-primary rounded-lg font-bold gap-1 text-[11px] shadow-sm shadow-primary/20">
                        <i class="fa-regular fa-pen-to-square"></i>
                        <span>수정</span>
                    </a>
                    <button type="button" class="btn btn-xs btn-error btn-outline rounded-lg font-bold gap-1 text-[11px]" onclick="deleteDocumentItem('${doc.filename}')">
                        <i class="fa-regular fa-trash-can"></i>
                        <span>삭제</span>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function deleteDocumentItem(filename) {
    pendingDeleteDocFilename = filename;
    document.getElementById('display_delete_doc_filename').textContent = filename;
    document.getElementById('modal_confirm_delete_doc').showModal();
}

async function executeDocumentDelete() {
    if (!pendingDeleteDocFilename) return;
    const filename = pendingDeleteDocFilename;
    document.getElementById('modal_confirm_delete_doc').close();

    try {
        const res = await fetch('./ajax_delete_doc.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                prj_id: currentDocPrjId,
                date_id: currentDocDateId,
                flight_date: currentDocFlightDate,
                prj_name: currentDocPrjName,
                filename: filename
            })
        });

        const result = await res.json();
        if (result.status === 'success') {
            loadDocumentList(currentDocPrjId, currentDocDateId, currentDocFlightDate, currentDocPrjName);
            showDocumentNotice('문서 삭제 완료', '선택한 문서를 삭제했습니다.', 'success');
        } else {
            showDocumentNotice('문서 삭제 실패', result.message || '문서를 삭제하지 못했습니다.', 'error');
        }
    } catch (e) {
        showDocumentNotice('통신 오류', '문서 삭제 요청을 처리하지 못했습니다. 잠시 후 다시 시도해 주세요.', 'error');
    } finally {
        pendingDeleteDocFilename = '';
    }
}

// --- 단일 EO 파일 선택 및 검증 처리 ---
var currentParsedEO = [];
var currentMatchedBlocks = [];
var isBlockRegistered = true;
var currentPickerDateId = 0;
var currentSelectedPickerFile = ''; 
var previewMode = 'UPLOAD';

function openEOFilePicker(dateId, dateStr) {
    currentPickerDateId = dateId;
    currentSelectedPickerFile = '';
    document.getElementById('picker_flight_date').innerText = dateStr;
    updateEOPreviewButton();

    const tbody = document.getElementById('eo_picker_tbody');
    tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-base-content/40 font-bold">📁 폴더를 스캔하는 중...</td></tr>`;
    modal_eo_file_picker.showModal();

    fetch(`action.php?action=get_eo_file_list&prj_id=<?php echo $prj_id; ?>&date_id=${dateId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-error font-bold">${data.message}</td></tr>`;
                return;
            }
            if (data.files.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-base-content/40 font-bold">폴더 내 등록된 EO 성과 파일이 없습니다.</td></tr>`;
                return;
            }

            tbody.innerHTML = '';
            data.files.forEach((file, idx) => {
                const tr = document.createElement('tr');
                tr.className = `hover:bg-base-200/60 cursor-pointer ${file.is_current ? 'bg-primary/10' : ''}`;
                
                // 행 클릭 시 단일 선택 라디오 버튼 연동
                tr.onclick = (e) => {
                    if(e.target.tagName !== 'INPUT') {
                        const radio = document.getElementById(`eo_radio_${idx}`);
                        radio.checked = true;
                        updateEOPreviewButton();
                    }
                };

                tr.innerHTML = `
                    <td>
                        <input type="radio" name="eo_file_choice" class="radio radio-primary radio-xs eo-file-radio" 
                               id="eo_radio_${idx}" value="${file.filename}" ${file.is_current ? 'checked' : ''} 
                               onchange="updateEOPreviewButton()">
                    </td>
                    <td class="text-left px-3 font-bold ${file.is_current ? 'text-primary' : ''}">${file.filename}</td>
                    <td class="text-base-content/60 text-[11px]">${file.mtime}</td>
                    <td class="text-base-content/60 text-[11px]">${file.size}</td>
                    <td>
                        ${file.is_current ? '<span class="badge badge-primary badge-xs py-1 px-1.5 font-bold">🔑</span>' : '<span class="badge badge-ghost badge-xs py-1 px-1.5">보관</span>'}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            updateEOPreviewButton();
        })
        .catch(err => {
            tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-error font-bold">통신 오류: ${err.message}</td></tr>`;
        });
}

// 버튼 텍스트 업데이트
function updateEOPreviewButton() {
    const selectedFile = document.querySelector('.eo-file-radio:checked');
    const btn = document.getElementById('btn_proceed_eo_preview');
    const textSpan = document.getElementById('btn_proceed_text');

    if (selectedFile) {
        btn.disabled = false;
        textSpan.innerText = '선택한 파일 검증하기 🔍';
        currentSelectedPickerFile = selectedFile.value;
    } else {
        btn.disabled = true;
        textSpan.innerText = `파일 검증하기 🔍`;
        currentSelectedPickerFile = '';
    }
}

// 선택된 한 개 파일 비동기 로드
async function proceedToEOPreview() {
    const selectedFile = document.querySelector('.eo-file-radio:checked');
    if (!selectedFile) return;
    const filename = selectedFile.value;
    
    modal_eo_file_picker.close();
    previewMode = 'PICKER';
    
    document.getElementById('preview_filename').innerText = `파일명: ${filename}`;

    try {
        const res = await fetch(`action.php?action=read_eo_file_content&prj_id=<?php echo $prj_id; ?>&date_id=${currentPickerDateId}&filename=${encodeURIComponent(filename)}`);
        const data = await res.json();
        if (!data.success) {
            showDocumentNotice('EO 파일 읽기 실패', data.message || '선택한 EO 파일을 읽지 못했습니다.', 'error');
            return;
        }

        if (data.is_binary) {
            const binaryStr = atob(data.base64);
            const bytes = new Uint8Array(binaryStr.length);
            for (let i = 0; i < binaryStr.length; i++) bytes[i] = binaryStr.charCodeAt(i);
            const workbook = XLSX.read(bytes, { type: 'array' });
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            processRawData(XLSX.utils.sheet_to_json(worksheet, { header: 1 }));
        } else {
            const rows = data.content.split(/\r\n|\n|\r/).map(line => line.trim().split(/\t|,|\s+/).filter(v => v !== ''));
            processRawData(rows);
        }
    } catch (err) {
        showDocumentNotice('EO 파일 읽기 오류', err.message || '선택한 EO 파일을 처리하지 못했습니다.', 'error');
    }
}

function processRawData(rows) {
    currentParsedEO = [];
    const courseSet = new Set();
    const uniqueIds = new Set(); // 중복 방지 기능
    const inspectResultIdx = findInspectResultIndex(rows);

    rows.forEach(row => {
        if (!row || row.length < 8) return;
        
        const id = String(row[0]).trim();
        if (!id || id.toLowerCase().includes('id') || id.toLowerCase().includes('photo')) return;

        if (uniqueIds.has(id)) return;
        uniqueIds.add(id);

        let courseNo = 0;
        if (id.includes('_')) {
            courseNo = parseInt(id.split('_')[0], 10);
        } else if (id.length >= 8) {
            courseNo = parseInt(id.substring(0, 4), 10);
        }

        if (!isNaN(courseNo) && courseNo > 0) courseSet.add(courseNo);

        currentParsedEO.push({
            id: id,
            x: row[1] || '-',
            y: row[2] || '-',
            z: row[3] || '-',
            omega: row[5] || row[4] || '-',
            phi: row[6] || row[5] || '-',
            kappa: row[7] || row[6] || '-',
            lat: row[8] || row[7] || '-',
            lon: row[9] || row[8] || '-',
            inspectResult: readInspectResultCell(row, inspectResultIdx)
        });
    });

    if (currentParsedEO.length === 0) {
        alert('유효한 데이터 행을 추출할 수 없습니다.');
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
    modal_eo_preview.showModal();
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
        const inspectClass = item.inspectResult.startsWith('재촬영')
            ? 'text-error font-bold'
            : (item.inspectResult.startsWith('중복미사용') ? 'text-success font-bold' : 'text-base-content/40');
        tr.className = 'hover:bg-base-200/50';
        tr.innerHTML = `
            <td class="text-base-content/50">${i + 1}</td>
            <td class="text-left font-bold text-primary">${inspectEscapeHtml(item.id)}</td>
            <td>${item.x}</td>
            <td>${item.y}</td>
            <td>${item.z}</td>
            <td>${item.omega}</td>
            <td>${item.phi}</td>
            <td>${item.kappa}</td>
            <td>${item.lat}</td>
            <td>${item.lon}</td>
            <td class="text-left ${inspectClass}">${inspectEscapeHtml(item.inspectResult || '-')}</td>
        `;
        tbody.appendChild(tr);
    }
}

function executeEOApply() {
    if (previewMode === 'UPLOAD') {
        applyEODataToForm();
    } else {
        const matchedStr = currentMatchedBlocks.map(b => b.name).join(', ');
        document.getElementById('apply_eo_date_id').value = currentPickerDateId;
        document.getElementById('apply_eo_filename').value = currentSelectedPickerFile;
        document.getElementById('apply_eo_shots').value = currentParsedEO.length;
        document.getElementById('apply_eo_matched_blocks').value = matchedStr;
        document.getElementById('form_apply_eo_to_db').submit();
    }
}

function handleEOFileSelect(input) {
    const file = input.files[0];
    if (!file) return;

    previewMode = 'UPLOAD';
    document.getElementById('preview_filename').innerText = `파일명: ${file.name}`;
    const reader = new FileReader();
    const fileName = file.name.toLowerCase();

    if (fileName.endsWith('.xlsx') || fileName.endsWith('.xls')) {
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            processRawData(jsonData);
        };
        reader.readAsArrayBuffer(file);
    } else {
        reader.onload = function(e) {
            const lines = e.target.result.split(/\r\n|\n|\r/);
            const rawData = lines.map(line => line.trim().split(/\t|,|\s+/).filter(v => v !== ''));
            processRawData(rawData);
        };
        reader.readAsText(file, 'EUC-KR');
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
    }

    document.getElementById('btn_reopen_preview').classList.remove('hidden');
}

var inspectEORecords = [];
var inspectReasonMap = new Map();
var inspectCurrentDateId = 0;
var inspectCurrentDateStr = '';
var inspectCurrentEOFile = '';
var inspectLastCheckedIndex = null;

function inspectEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[ch]));
}

function normalizeInspectPhotoId(value) {
    let id = String(value || '').trim();
    id = id.replace(/^["']|["']$/g, '');
    id = id.split(/[\\/]/).pop();
    id = id.replace(/\.(jpg|jpeg|tif|tiff|png|bmp|xlsx|xls|txt|csv|tsv|dat)$/i, '');
    return id.trim();
}

function getInspectCourseNo(id) {
    id = normalizeInspectPhotoId(id);
    if (!id || id.toLowerCase().includes('id') || id.toLowerCase().includes('photo')) return 0;
    if (id.includes('_')) return parseInt(id.split('_')[0], 10) || 0;
    if (id.length >= 8) return parseInt(id.substring(0, 4), 10) || 0;
    return 0;
}

function findInspectResultIndex(rows) {
    if (rows.length > 0 && Array.isArray(rows[0])) {
        const headerRow = rows[0].map(c => String(c ?? '').trim().toLowerCase());
        const resultIdx = headerRow.findIndex(h => h === '검수결과' || h === 'inspection' || h.includes('inspect'));
        if (resultIdx !== -1) return resultIdx;

        const lonIdx = headerRow.findIndex(h => h === 'lon(deg)' || h === 'lon' || h.includes('longitude'));
        if (lonIdx !== -1) return lonIdx + 1;
    }

    return 10;
}

function readInspectResultCell(row, inspectIdx) {
    const primary = row.length > inspectIdx ? String(row[inspectIdx] ?? '').trim() : '';
    if (primary) return primary;

    for (let i = 8; i < row.length; i++) {
        const candidate = String(row[i] ?? '').trim();
        if (candidate.startsWith('재촬영') || candidate.startsWith('중복미사용')) return candidate;
    }

    return '';
}

function parseInspectRows(rows) {
    const records = [];
    const seen = new Set();
    const inspectResultIdx = findInspectResultIndex(rows);

    rows.forEach(row => {
        if (!row || row.length < 8) return;

        const id = normalizeInspectPhotoId(row[0]);
        if (!id || seen.has(id)) return;

        const courseNo = getInspectCourseNo(id);
        if (courseNo <= 0) return;

        seen.add(id);

        // Lon(deg) 오른쪽 검수결과 컬럼이 이미 있으면 "재촬영: 사유" / "중복미사용: 사유" 형식으로 파싱해서 복원
        let existingReason = null;
        const resultCell = readInspectResultCell(row, inspectResultIdx);
        if (resultCell) {
            const isDup = resultCell.startsWith('중복미사용');
            const isReshoot = resultCell.startsWith('재촬영');
            if (isDup || isReshoot) {
                const colonIdx = resultCell.indexOf(':');
                const reasonText = colonIdx !== -1 ? resultCell.slice(colonIdx + 1).trim() : (isDup ? '중복미사용' : '재촬영');
                existingReason = { reason: reasonText, type: isDup ? 'duplicate' : 'reshoot' };
            }
        }

        records.push({ id, courseNo, existingReason });
    });

    return records;
}

function renderInspectionTable() {
    const tbody = document.getElementById('inspect_eo_tbody');
    tbody.innerHTML = '';

    if (inspectEORecords.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="py-20 text-base-content/40 font-bold">활성 EO에서 사진번호를 찾지 못했습니다.</td></tr>`;
        updateInspectSummary();
        return;
    }

    inspectEORecords.forEach((rec, idx) => {
        const item = inspectReasonMap.get(rec.id);
        const kind = item ? (item.type === 'duplicate' ? '중복미사용' : '재촬영') : '-';
        const reason = item ? item.reason : '';
        const kindClass = item ? (item.type === 'duplicate' ? 'text-success' : 'text-error') : 'text-base-content/40';
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-base-200/50';
        tr.innerHTML = `
            <td class="text-base-content/50">${idx + 1}</td>
            <td class="text-left font-bold text-primary">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="inspect_selected_ids[]" class="checkbox checkbox-primary checkbox-xs inspect-row-check" value="${inspectEscapeHtml(rec.id)}" data-index="${idx}" onclick="handleInspectRowCheck(event, this)">
                    <span>${inspectEscapeHtml(rec.id)}</span>
                </label>
            </td>
            <td>${rec.courseNo}</td>
            <td class="font-bold ${kindClass}">${kind}</td>
            <td class="text-left">${inspectEscapeHtml(reason)}</td>
        `;
        tbody.appendChild(tr);
    });

    updateInspectSummary();
}

function handleInspectRowCheck(event, checkbox) {
    const currentIndex = parseInt(checkbox.dataset.index, 10);
    if (Number.isNaN(currentIndex)) return;

    if (event.shiftKey && inspectLastCheckedIndex !== null) {
        const start = Math.min(inspectLastCheckedIndex, currentIndex);
        const end = Math.max(inspectLastCheckedIndex, currentIndex);
        const checked = checkbox.checked;

        document.querySelectorAll('.inspect-row-check').forEach(chk => {
            const idx = parseInt(chk.dataset.index, 10);
            if (!Number.isNaN(idx) && idx >= start && idx <= end) {
                chk.checked = checked;
            }
        });
    }

    inspectLastCheckedIndex = currentIndex;
}

function updateInspectSummary() {
    const total = inspectEORecords.length;
    let reshoot = 0;
    let duplicate = 0;

    inspectReasonMap.forEach(item => {
        if (item.type === 'duplicate') duplicate++;
        else reshoot++;
    });

    const used = Math.max(0, total - reshoot - duplicate);
    document.getElementById('inspect_summary_total').innerText = total.toLocaleString();
    document.getElementById('inspect_summary_used').innerText = used.toLocaleString();
    document.getElementById('inspect_summary_reshoot').innerText = reshoot.toLocaleString();
    document.getElementById('inspect_summary_duplicate').innerText = duplicate.toLocaleString();
}

function switchInspectInputMode(mode) {
    const isManual = mode === 'manual';
    document.getElementById('inspect_panel_manual').classList.toggle('hidden', !isManual);
    document.getElementById('inspect_panel_list').classList.toggle('hidden', isManual);
    document.getElementById('inspect_tab_manual').classList.toggle('tab-active', isManual);
    document.getElementById('inspect_tab_list').classList.toggle('tab-active', !isManual);
}

function toggleCustomInspectReason() {
    const reason = document.getElementById('inspect_reason_select').value;
    const custom = document.getElementById('inspect_custom_reason');
    custom.disabled = reason !== '직접입력';
    if (reason !== '직접입력') custom.value = '';
}

function getSelectedInspectionIds() {
    return Array.from(document.querySelectorAll('.inspect-row-check:checked')).map(chk => chk.value);
}

function getCurrentManualInspectionReason() {
    const selectReason = document.getElementById('inspect_reason_select').value;
    const customReason = document.getElementById('inspect_custom_reason').value.trim();
    return selectReason === '직접입력' ? customReason : selectReason;
}

function isUnusedInspectionReason(reason) {
    return String(reason || '').includes('미사용');
}

function applyInspectionReasonToSelected(showToast = true) {
    const selected = getSelectedInspectionIds();
    if (selected.length === 0) {
        if (showToast) triggerToast('적용할 사진번호를 선택해주세요.', 'warning', '⚠️');
        return false;
    }

    const reason = getCurrentManualInspectionReason();
    if (!reason) {
        if (showToast) triggerToast('사유를 입력해주세요.', 'warning', '⚠️');
        return false;
    }

    selected.forEach(id => {
        inspectReasonMap.set(id, {
            reason,
            type: isUnusedInspectionReason(reason) ? 'duplicate' : 'reshoot'
        });
    });

    renderInspectionTable();
    return true;
}

function applyManualInspectionReason() {
    applyInspectionReasonToSelected(true);
}

function clearManualInspectionReason() {
    const selected = Array.from(document.querySelectorAll('.inspect-row-check:checked')).map(chk => chk.value);
    selected.forEach(id => inspectReasonMap.delete(id));
    renderInspectionTable();
}

function applyBulkInspectionList() {
    const text = document.getElementById('inspect_bulk_text').value.trim();
    if (!text) {
        triggerToast('붙여넣을 리스트를 입력해주세요.', 'warning', '⚠️');
        return;
    }

    const eoIds = new Map(inspectEORecords.map(rec => [normalizeInspectPhotoId(rec.id), rec.id]));
    let matched = 0;

    text.split(/\r\n|\n|\r/).forEach(line => {
        const raw = line.trim();
        if (!raw) return;

        let parts = raw.split(/\t+/).map(v => v.trim()).filter(Boolean);
        if (parts.length < 2) parts = raw.split(/\s+/).map(v => v.trim()).filter(Boolean);
        if (parts.length < 2) return;

        const id = normalizeInspectPhotoId(parts.shift());
        if (!id || id.toLowerCase().includes('id') || id.includes('사진')) return;

        const eoId = eoIds.get(id);
        if (!eoId) return;

        const reason = parts.join(' ').trim();
        inspectReasonMap.set(eoId, {
            reason,
            type: isUnusedInspectionReason(reason) ? 'duplicate' : 'reshoot'
        });
        matched++;
    });

    renderInspectionTable();
    triggerToast(`${matched.toLocaleString()}건을 EO와 매칭했습니다.`, matched > 0 ? 'success' : 'warning', matched > 0 ? '✍️' : '⚠️');
}

async function openFlightInspectModal(dateId, dateStr, total, used, unused, reshoot) {
    inspectEORecords = [];
    inspectReasonMap = new Map();
    inspectCurrentDateId = dateId;
    inspectCurrentDateStr = dateStr;
    inspectCurrentEOFile = '';
    inspectLastCheckedIndex = null;

    document.getElementById('inspect_date_id').value = dateId;
    document.getElementById('inspect_target_date').innerText = `촬영일자: ${dateStr}`;
    document.getElementById('inspect_eo_filename').innerText = 'EO: 불러오는 중...';
    document.getElementById('inspect_bulk_text').value = '';
    document.getElementById('inspect_eo_tbody').innerHTML = `<tr><td colspan="5" class="py-20 text-base-content/40 font-bold">EO를 불러오는 중...</td></tr>`;
    switchInspectInputMode('manual');
    toggleCustomInspectReason();
    updateInspectSummary();
    modal_flight_inspect.showModal();

    try {
        const listRes = await fetch(`action.php?action=get_eo_file_list&prj_id=<?php echo $prj_id; ?>&date_id=${dateId}`);
        const listData = await listRes.json();
        if (!listData.success) throw new Error(listData.message || 'EO 파일 목록을 불러오지 못했습니다.');

        const currentFile = (listData.files || []).find(file => file.is_current) || (listData.files || [])[0];
        if (!currentFile) throw new Error('등록된 EO 성과 파일이 없습니다.');

        inspectCurrentEOFile = currentFile.filename;
        document.getElementById('inspect_eo_filename').innerText = `EO: ${inspectCurrentEOFile}`;

        const dataRes = await fetch(`action.php?action=read_eo_file_content&prj_id=<?php echo $prj_id; ?>&date_id=${dateId}&filename=${encodeURIComponent(inspectCurrentEOFile)}`);
        const data = await dataRes.json();
        if (!data.success) throw new Error(data.message || 'EO 파일을 읽지 못했습니다.');

        if (data.is_binary) {
            const binaryStr = atob(data.base64);
            const bytes = new Uint8Array(binaryStr.length);
            for (let i = 0; i < binaryStr.length; i++) bytes[i] = binaryStr.charCodeAt(i);
            const workbook = XLSX.read(bytes, { type: 'array' });
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            inspectEORecords = parseInspectRows(XLSX.utils.sheet_to_json(worksheet, { header: 1 }));
        } else {
            const rows = data.content.split(/\r\n|\n|\r/).map(line => line.trim().split(/\t|,|\s+/).filter(v => v !== ''));
            inspectEORecords = parseInspectRows(rows);
        }

        // 이미 검수결과가 기록된(수정 모드) EO라면 기존 사유를 미리 채워둔다
        let restoredCount = 0;
        inspectEORecords.forEach(rec => {
            if (rec.existingReason) {
                inspectReasonMap.set(rec.id, rec.existingReason);
                restoredCount++;
            }
        });
        if (restoredCount > 0) {
            triggerToast(`기존 검수내역 ${restoredCount.toLocaleString()}건을 불러왔습니다.`, 'success', '📝');
        }

        renderInspectionTable();
    } catch (err) {
        document.getElementById('inspect_eo_tbody').innerHTML = `<tr><td colspan="5" class="py-20 text-error font-bold">${inspectEscapeHtml(err.message)}</td></tr>`;
    }
}

function submitFlightInspection() {
    document.getElementById('inspect_manual_reason').value = getCurrentManualInspectionReason();

    if (getSelectedInspectionIds().length > 0) {
        if (!applyInspectionReasonToSelected(false)) return;
    }

    const items = [];
    inspectReasonMap.forEach((item, id) => {
        items.push({ id, reason: item.reason, type: item.type });
    });

    if (items.length === 0) {
        triggerToast('입력된 검수내역이 없습니다.', 'warning', '⚠️');
        return;
    }

    const jsonStr = JSON.stringify(items);
    const b64Str = btoa(unescape(encodeURIComponent(jsonStr))); // UTF-8 안전 Base64 인코딩 (그누보드의 POST 값 이스케이프/필터링으로 JSON이 깨지는 것을 방지)
    document.getElementById('inspect_payload').value = b64Str;
    document.getElementById('form_flight_inspect').submit();
}

let duplicateVideoGroups = [];
let duplicateVideoTargetDateId = 0;

async function openDuplicateVideoModal(dateId, flightDate) {
    duplicateVideoGroups = [];
    duplicateVideoTargetDateId = parseInt(dateId, 10) || 0;
    document.getElementById('duplicate_video_target_date').innerText = `촬영일: ${flightDate}`;
    document.getElementById('duplicate_video_count_badge').innerText = '확인 중';
    document.getElementById('duplicate_video_group_count').innerText = '0';
    document.getElementById('duplicate_video_apply_count').innerText = '0';
    document.getElementById('duplicate_video_list').innerHTML = `
        <div class="h-full flex items-center justify-center py-14 text-xs text-base-content/50 font-bold">
            중복영상을 확인하는 중입니다...
        </div>
    `;
    document.getElementById('duplicate_video_empty').classList.add('hidden');
    document.getElementById('form_duplicate_video_apply').classList.remove('hidden');
    document.getElementById('btn_apply_duplicate_video').disabled = true;
    modal_duplicate_video.showModal();

    try {
        const res = await fetch(`action.php?action=get_duplicate_video_groups&prj_id=<?php echo $prj_id; ?>&date_id=${duplicateVideoTargetDateId}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || '중복영상 정보를 불러오지 못했습니다.');

        duplicateVideoGroups = Array.isArray(data.groups) ? data.groups : [];
        renderDuplicateVideoGroups();
    } catch (err) {
        document.getElementById('duplicate_video_list').innerHTML = `
            <div class="py-20 text-center text-error font-bold text-sm">${inspectEscapeHtml(err.message)}</div>
        `;
        document.getElementById('duplicate_video_count_badge').innerText = '오류';
    }
}

function renderDuplicateVideoGroups() {
    const list = document.getElementById('duplicate_video_list');
    const groupCount = duplicateVideoGroups.length;
    const applyCount = duplicateVideoGroups.reduce((sum, group) => sum + Math.max((group.items || []).length - 1, 0), 0);

    document.getElementById('duplicate_video_count_badge').innerText = `${groupCount.toLocaleString()}건`;
    document.getElementById('duplicate_video_group_count').innerText = groupCount.toLocaleString();
    document.getElementById('duplicate_video_apply_count').innerText = applyCount.toLocaleString();
    document.getElementById('btn_apply_duplicate_video').disabled = groupCount === 0;

    if (groupCount === 0) {
        document.getElementById('duplicate_video_empty').classList.remove('hidden');
        document.getElementById('duplicate_video_empty').classList.add('flex');
        document.getElementById('form_duplicate_video_apply').classList.add('hidden');
        return;
    }

    document.getElementById('duplicate_video_empty').classList.add('hidden');
    document.getElementById('form_duplicate_video_apply').classList.remove('hidden');
    list.innerHTML = '';

    duplicateVideoGroups.forEach((group, groupIdx) => {
        const items = Array.isArray(group.items) ? group.items : [];
        const currentItem = items.find(item => parseInt(item.date_id, 10) === duplicateVideoTargetDateId);
        const defaultDateId = currentItem ? duplicateVideoTargetDateId : (items[0] ? parseInt(items[0].date_id, 10) : 0);
        const row = document.createElement('div');
        row.className = 'grid grid-cols-[132px_1fr] gap-2 px-3 py-1.5 hover:bg-base-200/35 items-center';

        const options = items.map(item => {
            const itemDateId = parseInt(item.date_id, 10) || 0;
            const checked = itemDateId === defaultDateId ? 'checked' : '';
            const dateText = inspectEscapeHtml(item.flight_date || '-');
            const nameText = inspectEscapeHtml(item.flight_name || '-');
            const eoText = inspectEscapeHtml(item.eo_file_name || '-');
            const currentBadge = itemDateId === duplicateVideoTargetDateId
                ? '<span class="badge badge-info badge-xs border-0 font-bold h-4 px-1.5">현재</span>'
                : '<span></span>';

            return `
                <label class="grid grid-cols-[18px_92px_42px_minmax(46px,80px)_minmax(180px,1fr)] items-center gap-2 rounded-md border border-base-content/10 bg-base-100/60 px-2 py-1 cursor-pointer hover:border-info transition-colors min-w-0">
                    <input type="radio" class="radio radio-info radio-xs duplicate-video-radio" name="dup_keep_${groupIdx}" value="${itemDateId}" ${checked} onchange="updateDuplicateVideoApplyCount()">
                    <span class="font-mono font-black text-base-content text-[12px]">${dateText}</span>
                    ${currentBadge}
                    <span class="text-[11px] text-base-content/65 truncate">${nameText}</span>
                    <span class="text-[10px] text-base-content/45 font-mono truncate min-w-0">${eoText}</span>
                </label>
            `;
        }).join('');

        row.innerHTML = `
            <div class="font-mono font-black text-primary text-[12px] whitespace-nowrap overflow-visible">${inspectEscapeHtml(group.id || '-')}</div>
            <div class="grid gap-1">${options}</div>
        `;
        list.appendChild(row);
    });

    updateDuplicateVideoApplyCount();
}

function updateDuplicateVideoApplyCount() {
    let applyCount = 0;
    duplicateVideoGroups.forEach((group, groupIdx) => {
        const items = Array.isArray(group.items) ? group.items : [];
        const checked = document.querySelector(`input[name="dup_keep_${groupIdx}"]:checked`);
        if (!checked) return;
        applyCount += items.filter(item => parseInt(item.date_id, 10) !== parseInt(checked.value, 10)).length;
    });
    document.getElementById('duplicate_video_apply_count').innerText = applyCount.toLocaleString();
}

function selectDuplicateKeepMode(mode) {
    duplicateVideoGroups.forEach((group, groupIdx) => {
        const items = Array.isArray(group.items) ? group.items.slice() : [];
        if (items.length === 0) return;

        let target = items[0];
        if (mode === 'current') {
            target = items.find(item => parseInt(item.date_id, 10) === duplicateVideoTargetDateId) || target;
        } else {
            items.sort((a, b) => String(a.flight_date || '').localeCompare(String(b.flight_date || '')));
            target = mode === 'newest' ? items[items.length - 1] : items[0];
        }

        const radio = document.querySelector(`input[name="dup_keep_${groupIdx}"][value="${parseInt(target.date_id, 10)}"]`);
        if (radio) radio.checked = true;
    });
    updateDuplicateVideoApplyCount();
}

function submitDuplicateVideoSelection() {
    if (duplicateVideoGroups.length === 0) return;

    const keepMap = {};
    duplicateVideoGroups.forEach((group, groupIdx) => {
        const checked = document.querySelector(`input[name="dup_keep_${groupIdx}"]:checked`);
        if (checked && group.id) keepMap[String(group.id).toUpperCase()] = parseInt(checked.value, 10);
    });

    const applyCount = parseInt(document.getElementById('duplicate_video_apply_count').innerText.replace(/,/g, ''), 10) || 0;
    if (applyCount <= 0) {
        triggerToast('반영할 중복영상이 없습니다.', 'warning', '⚠️');
        return;
    }

    const ok = confirm(`${applyCount.toLocaleString()}건을 중복미사용으로 EO에 반영합니다.\n\n선택하지 않은 날짜의 EO는 새 검수완료 파일로 생성되고 활성 EO가 변경됩니다.\n계속 진행할까요?`);
    if (!ok) return;

    const jsonStr = JSON.stringify(keepMap);
    document.getElementById('duplicate_video_payload').value = btoa(unescape(encodeURIComponent(jsonStr)));
    document.getElementById('form_duplicate_video_apply').submit();
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
    document.getElementById('form_flight_delete').submit();
}
</script>
