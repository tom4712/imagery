<?php
if (!defined('_GNUBOARD_')) exit;
?>
<script>
const rawEntities = <?php echo $entities_json ?: '[]'; ?>;
let layerState    = <?php echo $layers_json ?: '{}'; ?>;
const canvas      = document.getElementById('cad_canvas');

const aciColors = {
    1: '#ef4444', 2: '#eab308', 3: '#22c55e', 4: '#06b6d4',
    5: '#60a5fa', 6: '#d946ef', 7: '#ffffff', 8: '#94a3b8', 9: '#cbd5e1'
};

let scale = 1;
let panX = 0, panY = 0;
let isDragging = false;
let dragMoved = false;
let startX = 0, startY = 0;
let startClientX = 0, startClientY = 0;
let lastWheelClickTime = 0;
let selectedEntityIndex = -1;

let minX = 0, maxX = 0, minY = 0, maxY = 0, centerX = 0, centerY = 0;

function canvasMousePoint(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY
    };
}

function canvasToCadPoint(canvasX, canvasY) {
    return {
        x: ((canvasX - canvas.width / 2 - panX) / scale) + centerX,
        y: (-(canvasY - canvas.height / 2 - panY) / scale) + centerY
    };
}

function distancePointToSegment(px, py, ax, ay, bx, by) {
    const dx = bx - ax;
    const dy = by - ay;
    if (dx === 0 && dy === 0) return Math.hypot(px - ax, py - ay);
    const t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy)));
    const x = ax + t * dx;
    const y = ay + t * dy;
    return Math.hypot(px - x, py - y);
}

function entityHitDistance(e, pt) {
    if (e.type === 'CIRCLE') {
        return Math.abs(Math.hypot(pt.x - e.x, pt.y - e.y) - parseFloat(e.radius || 50.0));
    }
    if (e.type === 'TEXT' || e.type === 'MTEXT') {
        const x = (e.align_x !== null && e.align_x !== undefined && (e.align_x !== 0 || e.align_y !== 0)) ? e.align_x : e.x;
        const y = (e.align_x !== null && e.align_x !== undefined && (e.align_x !== 0 || e.align_y !== 0)) ? e.align_y : e.y;
        return Math.hypot(pt.x - x, pt.y - y);
    }
    if (e.type === 'LINE') {
        return distancePointToSegment(pt.x, pt.y, e.x1, e.y1, e.x2, e.y2);
    }
    if (e.type === 'LWPOLYLINE' && e.points && e.points.length > 1) {
        let best = Infinity;
        for (let i = 1; i < e.points.length; i++) {
            best = Math.min(best, distancePointToSegment(pt.x, pt.y, e.points[i - 1].x, e.points[i - 1].y, e.points[i].x, e.points[i].y));
        }
        return best;
    }
    return Infinity;
}

function findEntityAt(canvasX, canvasY) {
    const pt = canvasToCadPoint(canvasX, canvasY);
    const threshold = Math.max(60, 14 / scale);
    let bestIdx = -1;
    let bestDist = threshold;

    rawEntities.forEach((e, idx) => {
        const l = layerState[e.layer];
        if (l && (!l.visible || l.frozen)) return;
        const dist = entityHitDistance(e, pt);
        if (dist <= bestDist) {
            bestDist = dist;
            bestIdx = idx;
        }
    });

    return bestIdx;
}

function showLayerPopup(entity, canvasX, canvasY) {
    let popup = document.getElementById('object_layer_popup');
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'object_layer_popup';
        popup.className = 'fixed z-[90] pointer-events-none glass-float rounded-xl px-3 py-2 text-xs font-bold text-base-content shadow-2xl border border-base-content/10';
        document.body.appendChild(popup);
    }

    const layer = entity?.layer || '-';
    const label = entity?.text ? `${layer} · ${entity.text}` : layer;
    popup.innerHTML = `<span class="text-base-content/50 mr-1.5">레이어</span><span class="font-mono text-primary">${label}</span>`;
    popup.style.left = `${Math.min(window.innerWidth - 280, Math.max(12, canvasX + 18))}px`;
    popup.style.top = `${Math.min(window.innerHeight - 60, Math.max(12, canvasY + 18))}px`;
    popup.classList.remove('hidden');

    clearTimeout(showLayerPopup.timer);
    showLayerPopup.timer = setTimeout(() => popup.classList.add('hidden'), 2800);
}

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

function calculateBounds() {
    let pts = [];
    rawEntities.forEach((e, idx) => {
        if (e.type === 'LINE') { pts.push({x: e.x1, y: e.y1}); pts.push({x: e.x2, y: e.y2}); }
        else if (e.type === 'LWPOLYLINE' && e.points) { e.points.forEach(p => pts.push(p)); }
        else if ((e.type === 'CIRCLE' || e.type === 'TEXT' || e.type === 'MTEXT') && e.x !== undefined) { pts.push({x: e.x, y: e.y}); }
    });

    if (pts.length === 0) return false;

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
    return true;
}

function fitView(animate = false) {
    if (!canvas) return;

    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const padding = 120;
    const w = Math.max(100, canvas.width - padding);
    const h = Math.max(100, canvas.height - padding);

    const dx = Math.max(10, maxX - minX);
    const dy = Math.max(10, maxY - minY);

    scale = Math.min(w / dx, h / dy);
    panX = 0;
    panY = 0;

    draw();
}

function draw() {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    ctx.save();
    ctx.translate(canvas.width / 2 + panX, canvas.height / 2 + panY);
    ctx.scale(scale, -scale); // CAD 상향 Y축 좌표계

    rawEntities.forEach((e, idx) => {
        const l = layerState[e.layer];
        if (l && (!l.visible || l.frozen)) return;

        // 색상 결정: 엔티티 개별 색상 > 레이어 기본 색상
        let color = '#ffffff';
        if (e.color && aciColors[e.color]) {
            color = aciColors[e.color];
        } else if (l && l.color_hex) {
            color = l.color_hex;
        }

        if (e.type === 'LINE') {
            ctx.beginPath();
            ctx.moveTo(e.x1 - centerX, e.y1 - centerY);
            ctx.lineTo(e.x2 - centerX, e.y2 - centerY);
            ctx.lineWidth = idx === selectedEntityIndex ? Math.max(3, 3.6 / scale) : Math.max(1, 1.2 / scale);
            ctx.strokeStyle = color;
            ctx.stroke();
        } else if (e.type === 'LWPOLYLINE' && e.points && e.points.length > 0) {
            ctx.beginPath();
            ctx.moveTo(e.points[0].x - centerX, e.points[0].y - centerY);
            for (let j = 1; j < e.points.length; j++) {
                ctx.lineTo(e.points[j].x - centerX, e.points[j].y - centerY);
            }
            ctx.lineWidth = idx === selectedEntityIndex ? Math.max(3, 3.6 / scale) : Math.max(1, 1.2 / scale);
            ctx.strokeStyle = color;
            ctx.stroke();
        } else if (e.type === 'CIRCLE') {
            const realRadius = parseFloat(e.radius || 50.0);
            ctx.beginPath();
            ctx.arc(e.x - centerX, e.y - centerY, realRadius, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.fill();
            ctx.lineWidth = idx === selectedEntityIndex ? Math.max(3, 3.4 / scale) : Math.max(1, 1.0 / scale);
            ctx.strokeStyle = color;
            ctx.stroke();
        } else if (e.type === 'TEXT' || e.type === 'MTEXT') {
            const cadHeight   = parseFloat(e.height) || 150.0;
            const cadRotation = parseFloat(e.rotation) || 0.0;

            const hasAlign = (e.align_x !== null && e.align_x !== undefined && (e.align_x !== 0 || e.align_y !== 0));
            const px = (hasAlign ? parseFloat(e.align_x) : parseFloat(e.x)) - centerX;
            const py = (hasAlign ? parseFloat(e.align_y) : parseFloat(e.y)) - centerY;

            if (cadHeight * scale > 0.5) {
                ctx.save();
                ctx.translate(px, py);

                // CAD 회전 적용 (도면 좌표계 반전 대응)
                const rad = cadRotation * (Math.PI / 180.0);
                ctx.rotate(rad);
                ctx.scale(1, -1); // 텍스트 상하 반전 방지

                ctx.font = `bold ${cadHeight}px "Pretendard", "Malgun Gothic", sans-serif`;
                ctx.fillStyle = color;

                // 정렬 모드 매핑 (AutoCAD Group 72 / 73)
                let alignH = 'left';
                let baselineV = 'bottom';

                const hCode = parseInt(e.align_h || 0);
                const vCode = parseInt(e.align_v || 0);

                if (hCode === 1 || hCode === 4) alignH = 'center';
                else if (hCode === 2) alignH = 'right';

                if (vCode === 1) baselineV = 'bottom';
                else if (vCode === 2 || hCode === 4) baselineV = 'middle';
                else if (vCode === 3) baselineV = 'top';

                ctx.textAlign = alignH;
                ctx.textBaseline = baselineV;

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
        dragMoved = false;
        startX = e.clientX - panX;
        startY = e.clientY - panY;
        startClientX = e.clientX;
        startClientY = e.clientY;
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        if (Math.abs(e.clientX - startClientX) > 2 || Math.abs(e.clientY - startClientY) > 2) {
            dragMoved = true;
        }
        panX = e.clientX - startX;
        panY = e.clientY - startY;
        draw();
    });

    window.addEventListener('mouseup', () => isDragging = false);

    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        const worldX = (mouseX - canvas.width / 2 - panX) / scale;
        const worldY = -(mouseY - canvas.height / 2 - panY) / scale;

        const zoomFactor = e.deltaY < 0 ? 1.15 : 0.87;
        const newScale = Math.min(Math.max(0.00001, scale * zoomFactor), 100);

        panX = mouseX - canvas.width / 2 - worldX * newScale;
        panY = mouseY - canvas.height / 2 + worldY * newScale;
        scale = newScale;

        draw();
    }, { passive: false });

    canvas.addEventListener('click', (e) => {
        if (dragMoved) return;

        const mouse = canvasMousePoint(e);
        const idx = findEntityAt(mouse.x, mouse.y);
        selectedEntityIndex = idx;
        if (idx >= 0) {
            showLayerPopup(rawEntities[idx], e.clientX, e.clientY);
        }
        draw();
    });
}

function zoomIn() { if (canvas) { scale *= 1.25; draw(); } }
function zoomOut() { if (canvas) { scale /= 1.25; draw(); } }
function selectActiveIndex(filename) {
    const modeQuery = <?php echo (($index_mode ?? 'date') === 'block')
        ? '`&block_id=' . (int)($block_id ?? 0) . '`'
        : '`&date_id=' . (int)$date_id . '`'; ?>;
    location.href = `index_view.php?prj_id=<?php echo (int)$prj_id; ?>${modeQuery}&active_file=${encodeURIComponent(filename)}`;
}
</script>
