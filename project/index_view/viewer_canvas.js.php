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

// 레이어 관리 UI 렌더링
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

// 바운딩 박스 계산
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

// 뷰포트 맞춤
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

// 메인 렌더링 루프
function draw() {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    ctx.save();
    ctx.translate(canvas.width / 2 + panX, canvas.height / 2 + panY);
    ctx.scale(scale, -scale); // CAD Y축 상향 반전

    // 1. 코스 선형 렌더링
    const courses = {};
    rawEntities.forEach(e => {
        if (e.type === 'TEXT' && (!e.layer.includes('INDEX') && !e.layer.includes('G50'))) {
            const l = layerState[e.layer];
            if (!l || !l.visible || l.frozen) return;
            const cNo = e.text.includes('_') ? e.text.split('_')[0] : e.text.substring(0, 4);
            if (!courses[cNo]) courses[cNo] = [];
            courses[cNo].push(e);
        }
    });

    ctx.lineWidth = Math.max(1, 1.5 / scale);
    ctx.strokeStyle = 'rgba(99, 102, 241, 0.45)';
    ctx.setLineDash([8 / scale, 6 / scale]);
    Object.values(courses).forEach(pts => {
        if (pts.length > 1) {
            ctx.beginPath();
            ctx.moveTo(pts[0].x - centerX, pts[0].y - centerY);
            for (let i = 1; i < pts.length; i++) {
                ctx.lineTo(pts[i].x - centerX, pts[i].y - centerY);
            }
            ctx.stroke();
        }
    });
    ctx.setLineDash([]);

    // 2. CAD 엔티티 렌더링
    const screenTextSize = 150 * scale;
    const showPhotoText = screenTextSize > 4;
    const dotRadius = Math.max(30, 2 / scale);

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
            ctx.beginPath();
            ctx.arc(e.x - centerX, e.y - centerY, dotRadius, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.fill();
        } else if (e.type === 'TEXT') {
            const px = e.x - centerX;
            const py = e.y - centerY;

            if (e.layer === 'INDEX_50K') {
                ctx.save();
                ctx.translate(px, py);
                ctx.scale(1 / scale, -1 / scale);
                ctx.font = 'bold 14px sans-serif';
                ctx.fillStyle = color;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(e.text, 0, 0);
                ctx.restore();
            } else if (showPhotoText) {
                ctx.save();
                ctx.translate(px, py);
                ctx.scale(1 / scale, -1 / scale);
                ctx.rotate(-Math.PI / 4);
                const displayFontSize = Math.max(9, Math.min(22, screenTextSize));
                ctx.font = `bold ${displayFontSize}px monospace`;
                ctx.fillStyle = color;
                ctx.fillText(e.text, 10, -4);
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

// 즉시 실행 트리거
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