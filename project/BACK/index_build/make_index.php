<!-- /project/index_build/make_index.php -->
<div class="flex h-screen bg-base-300">
    <!-- 좌측 사이드바: 도엽 선택 및 생성 패널 -->
    <div class="w-96 bg-base-100 p-4 flex flex-col justify-between shadow-xl z-10">
        <div>
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-map-location-dot text-primary text-xl"></i>
                <h2 class="text-lg font-bold">인덱스 자동 생성</h2>
            </div>

            <div class="stats stats-vertical shadow bg-base-200 w-full mb-4">
                <div class="stat py-2">
                    <div class="stat-title text-xs">해당 일자 주점 수</div>
                    <div class="stat-value text-primary text-xl" id="stat_point_count">0 개</div>
                </div>
                <div class="stat py-2">
                    <div class="stat-title text-xs">포함된 5만 도엽</div>
                    <div class="stat-value text-secondary text-xl" id="stat_sheet_count">0 개</div>
                </div>
            </div>

            <div class="form-control mb-4">
                <label class="label"><span class="label-text font-bold">선택된 도엽 목록</span></label>
                <div class="max-h-60 overflow-y-auto space-y-1" id="selected_sheet_list">
                    <!-- 선택된 도엽 뱃지 리스트 동적 삽입 -->
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <button class="btn btn-outline btn-sm w-full" onclick="autoSelectIntersectedSheets()">
                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> 주점 영역 자동 선택
            </button>
            <button class="btn btn-primary w-full" onclick="buildAndSaveDxf()">
                <i class="fa-solid fa-file-export mr-1"></i> 도곽 합치기 및 DXF 생성
            </button>
        </div>
    </div>

    <!-- 우측 뷰어: 전체 5만 망 + 간이 주점 렌더링 캔버스 -->
    <div class="flex-1 relative overflow-hidden bg-slate-950">
        <canvas id="index_builder_canvas" class="w-full h-full cursor-crosshair"></canvas>
        <div class="absolute top-4 right-4 bg-base-100/80 backdrop-blur rounded-lg p-2 flex gap-2">
            <button class="btn btn-xs btn-square" onclick="zoomIn()"><i class="fa-solid fa-plus"></i></button>
            <button class="btn btn-xs btn-square" onclick="zoomOut()"><i class="fa-solid fa-minus"></i></button>
            <button class="btn btn-xs" onclick="fitView()">전체 보기</button>
        </div>
    </div>
</div>