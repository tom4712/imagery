<?php
if (!defined('_GNUBOARD_')) exit;
?>
<script>
const rawEntities = <?php echo $entities_json ?: '[]'; ?>;
let layerState    = <?php echo $layers_json ?: '{}'; ?>;
const canvas      = document.getElementById('cad_canvas');

console.log("[CAD V2] 로드된 엔티티 총 개수:", rawEntities.length);
console.log("[CAD V2] 로드된 레이어 테이블:", layerState);

let scale = 1;
let panX = 0, panY = 0;
let isDragging = false;
let startX = 0, startY = 0;
let lastWheelClickTime = 0;

let minX = 0, maxX = 0, minY = 0, maxY = 0, centerX = 0, centerY = 0;

// 레이어 관리 UI 렌더링 (기존 유지)
function initLayerManagerUI() {
    const container = document.getElementById('layer_list_container');
    if (!container) return;

    const layerKeys = Object.keys(layerState);
    const badge = document.getElementById('layer_count_badge');
    if (badge) badge.innerText = layerKeys.length;

    container.innerHTML = '';
    layerKeys.forEach(name => {
        const l = layerState[name];
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between p-1.5 rounded-lg hover:bg-base-200/50 text-xs transition-colors';
        
        row.innerHTML = `
            <div class="flex items-center gap-2 truncate max-w-[130px]" title="${l.name}">
                <span class="w-2.5 h-2.5 rounded-full border border-white/20 flex-shrink-0" style="background-color: ${l.color_hex};"></span>
                <span class="font-mono truncate ${l.frozen ? 'opacity-30 line-through' : (l.visible ? 'text-base-content font-bold' : 'opacity-40')}">${l.name}</span>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
                <button type="button" class="btn btn-ghost btn-xs btn-circle ${l.visible ? 'text-warning' : 'text-base-content/20'}" 
                        onclick="toggleLayerVisibility('${l.name}')" title="${l.visible ? '레이어 끄기' : '레이어 켜기'}">
                    <i class="fa-solid fa-lightbulb text-[11px]"></i>
                </button>
                <button type="button" class="btn btn-ghost btn-xs btn-circle ${l.frozen ? 'text-info' : 'text-base-content/20'}" 
                        onclick="toggleLayerFreeze('${l.name}')" title="${l.frozen ? '동결 해제' : '레이어 동결'}">
                    <i class="fa-solid fa-snowflake text-[11px]"></i>
                </button>
            </div>
        `;
        container.appendChild(row);
    });
}

function toggleLayerVisibility(name) {
    if (layerState[name]) {
        layerState[name].visible = !layerState[name].visible;
        initLayerManagerUI();
        draw();
    }
}

function toggleLayerFreeze(name) {
    if (layerState[name]) {
        layerState[name].frozen = !layerState[name].frozen;
        initLayerManagerUI();
        draw();
    }
}

function toggleLayerPanel() {
    const panel = document.getElementById('layer_manager_panel');
    if (panel) panel.classList.toggle('hidden');
}

// 바운딩 박스 계산 (기존 유지)
function calculateBounds() {
    let pts = [];
    rawEntities.forEach(e => {
        if (e.type === 'LINE') { pts.push({x: e.x1, y: e.y1}); pts.push({x: e.x2, y: e.y2}); }
        else if (e.type === 'LWPOLYLINE' && e.points) { e.points.forEach(p => pts.push(p)); }
        else if ((e.type === 'CIRCLE' || e.type === 'TEXT') && e.x !== undefined) { pts.push({x: e.x, y: e.y}); }
    });

    if (pts.length === 0) {
        console.warn("[CAD V2] 좌표 엔티티가 없습니다.");
        return false;
    }

    minX = pts[0].x; maxX = pts[0].x;
    minY = pts[0].y; maxY = pts[0].y;

    pts.forEach(p => {
        if (p.x < minX) minX = p.x;
        if (p.x > maxX) maxX = p.x;
        if (p.y < minY) minY = p.y;
        if (p.y > maxY) maxY = p.y;
    });

    centerX = (minX + maxX) / 2;
    centerY = (minY + maxY) / 2;
    
    console.log(`[CAD V2] Bounding Box: X(${minX} ~ ${maxX}), Y(${minY} ~ ${maxY}), Center(${centerX}, ${centerY})`);
    return true;
}

// 뷰포트 맞춤 (기존 유지)
function fitView(animate = false) {
    if (!canvas) return;

    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const padding = 120;
    const w = Math.max(100, canvas.width - padding);
    const h = Math.max(100, canvas.height - padding);

    const dx = Math.max(10, maxX - minX);
    const dy = Math.max(10, maxY - minY);

    const targetScale = Math.min(w / dx, h / dy);
    const targetPanX = 0;
    const targetPanY = 0;

    scale = targetScale;
    panX = targetPanX;
    panY = targetPanY;

    draw();
}

// 메인 렌더링 루프 (텍스트 회전 및 개별 크기 보정 완료)
function draw() {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    ctx.save();
    ctx.translate(canvas.width / 2 + panX, canvas.height / 2 + panY);
    ctx.scale(scale, -scale); // CAD Y축 상향 좌표계 설정

    rawEntities.forEach(e => {
        const l = layerState[e.layer];
        if (l && (!l.visible || l.frozen)) return;

        const color = (l && l.color_hex) ? l.color_hex : '#ffffff';

        if (e.type === 'LINE') {
            ctx.beginPath();
            ctx.moveTo(e.x1 - centerX, e.y1 - centerY);
            ctx.lineTo(e.x2 - centerX, e.y2 - centerY);
            ctx.lineWidth = Math.max(1, 1.5 / scale);
            ctx.strokeStyle = color;
            ctx.stroke();
        } else if (e.type === 'LWPOLYLINE' && e.points && e.points.length > 0) {
            ctx.beginPath();
            ctx.moveTo(e.points[0].x - centerX, e.points[0].y - centerY);
            for (let j = 1; j < e.points.length; j++) {
                ctx.lineTo(e.points[j].x - centerX, e.points[j].y - centerY);
            }
            ctx.lineWidth = Math.max(1, 1.5 / scale);
            ctx.strokeStyle = color;
            ctx.stroke();
        } else if (e.type === 'CIRCLE') {
            // CAD 객체의 실제 반지름(e.radius 또는 e.r) 반영
            const realRadius = parseFloat(e.radius || e.r || 50.0);
            ctx.beginPath();
            ctx.arc(e.x - centerX, e.y - centerY, realRadius, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.fill();
            ctx.lineWidth = Math.max(1, 1.0 / scale);
            ctx.strokeStyle = color;
            ctx.stroke();
        } else if (e.type === 'TEXT' || e.type === 'MTEXT') {
            // 1. DXF 정렬 좌표(align_x, align_y) 존재 시 해당 좌표를 기준점으로 사용
            const hasAlign = (e.align_x !== undefined && e.align_x !== null && (e.align_x !== 0 || e.align_y !== 0));
            const px = (hasAlign ? parseFloat(e.align_x) : parseFloat(e.x)) - centerX;
            const py = (hasAlign ? parseFloat(e.align_y) : parseFloat(e.y)) - centerY;

            // 2. CAD 도면에 정의된 객체별 각각의 높이(e.height)와 회전 각도(e.rotation) 추출
            const cadHeight   = parseFloat(e.height) || 150.0;
            const cadRotation = parseFloat(e.rotation) || 0.0; // CAD 회전각 (Degree)

            // 화면 표시 크기가 유효할 때만 렌더링
            if (cadHeight * scale > 0.5) {
                ctx.save();
                
                // [핵심] CAD 원본 좌표 위치로 이동
                ctx.translate(px, py);

                // [핵심 연산 보정] 
                // Canvas Y축 반전(-scale) 상황에서 CAD 회전을 시계 반대 방향(+) 그대로 맞추기 위해 -rot 연산 적용
                const rad = -cadRotation * (Math.PI / 180.0);
                ctx.rotate(rad);

                // 글자가 상하 반전되어 뒤집히는 것을 방지하기 위해 텍스트 렌더링 직전 Y축 -1 스케일링
                ctx.scale(1, -1);

                // 객체 본연의 CAD 문자 높이(cadHeight)를 그대로 적용
                ctx.font = `bold ${cadHeight}px "Malgun Gothic", sans-serif`;
                ctx.fillStyle = color;

                // 3. DXF Group 72, 73 정렬 코드 1:1 매핑
                let alignH = 'left';
                let baselineV = 'bottom';

                const hCode = parseInt(e.align_h || 0);
                const vCode = parseInt(e.align_v || 0);

                if (hCode === 1 || hCode === 4) alignH = 'center';
                else if (hCode === 2) alignH = 'right';

                // Y축을 -1로 뒤집었으므로 top과 bottom 매핑 반전 보정
                if (vCode === 1) baselineV = 'bottom';
                else if (vCode === 2 || hCode === 4) baselineV = 'middle';
                else if (vCode === 3) baselineV = 'top';

                ctx.textAlign = alignH;
                ctx.textBaseline = baselineV;

                // 기준점 (0,0)에 원본 문자를 그대로 그리기
                ctx.fillText(e.text, 0, 0);
                ctx.restore();
            }
        }
    });

    ctx.restore();
}

function initViewer() {
    if (!canvas || rawEntities.length === 0) return;
    if (calculateBounds()) {
        fitView(false);
        initLayerManagerUI();
    }
}

window.addEventListener('load', initViewer);
window.addEventListener('resize', () => fitView(false));

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(initViewer, 50);
}

if (canvas) {
    canvas.addEventListener('mousedown', (e) => {
        if (e.button === 1) {
            e.preventDefault();
            const now = Date.now();
            if (now - lastWheelClickTime < 350) {
                fitView(true);
                lastWheelClickTime = 0;
                return;
            }
            lastWheelClickTime = now;
        }
        isDragging = true;
        startX = e.clientX - panX;
        startY = e.clientY - panY;
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        panX = e.clientX - startX;
        panY = e.clientY - startY;
        draw();
    });

    window.addEventListener('mouseup', () => isDragging = false);

    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const mouseX = e.clientX - canvas.width / 2;
        const mouseY = e.clientY - canvas.height / 2;

        const zoomFactor = e.deltaY < 0 ? 1.15 : 0.87;
        const newScale = Math.min(Math.max(0.00001, scale * zoomFactor), 100);

        panX -= mouseX * (newScale / scale - 1);
        panY -= mouseY * (newScale / scale - 1);
        scale = newScale;

        draw();
    }, { passive: false });
}

function zoomIn() { if (canvas) { scale *= 1.25; draw(); } }
function zoomOut() { if (canvas) { scale /= 1.25; draw(); } }
function selectActiveIndex(filename) {
    location.href = `index_view.php?prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $date_id; ?>&active_file=${encodeURIComponent(filename)}`;
}
</script>