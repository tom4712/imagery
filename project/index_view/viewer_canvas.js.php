<?php
if (!defined('_GNUBOARD_')) exit; // 그누보드 개별 호출 방지

/**
 * DXF 내장 정밀 파서 함수 (별도 include 파일 없이 단일 처리)
 * DXF Group 40(높이/반지름), 50(회전각), 11/21(정렬좌표), 72/73(정렬방식)을 직접 추출
 */
if (!function_exists('parseDxfEntitiesDirect')) {
    function parseDxfEntitiesDirect($filePath) {
        if (!file_exists($filePath)) return ['entities' => [], 'layers' => []];

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entities = [];
        $layers = [];

        $inEntities = false;
        $inTables = false;
        $inLayerTable = false;
        $currentEntity = null;
        $currentLayer = null;

        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount - 1; $i += 2) {
            $code = intval(trim($lines[$i]));
            $val = trim($lines[$i + 1]);

            if ($code === 0 && $val === 'SECTION') {
                $nextCode = intval(trim($lines[$i + 2] ?? -1));
                $nextVal = trim($lines[$i + 3] ?? '');
                if ($nextCode === 2 && $nextVal === 'ENTITIES') $inEntities = true;
                if ($nextCode === 2 && $nextVal === 'TABLES') $inTables = true;
                continue;
            }

            if ($code === 0 && $val === 'ENDSEC') {
                $inEntities = false;
                $inTables = false;
                $inLayerTable = false;
                continue;
            }

            // 1. LAYER 테이블 정보 추출
            if ($inTables) {
                if ($code === 2 && $val === 'LAYER') { $inLayerTable = true; continue; }
                if ($inLayerTable) {
                    if ($code === 0 && $val === 'LAYER') {
                        if ($currentLayer) $layers[$currentLayer['name']] = $currentLayer;
                        $currentLayer = ['name' => '0', 'color_hex' => '#ffffff', 'visible' => true, 'frozen' => false];
                    } elseif ($code === 2 && $currentLayer) {
                        $currentLayer['name'] = $val;
                    } elseif ($code === 62 && $currentLayer) {
                        $cIdx = abs(intval($val));
                        $colors = [1=>'#ff0000', 2=>'#ffff00', 3=>'#00ff00', 4=>'#00ffff', 5=>'#0000ff', 6=>'#ff00ff', 7=>'#ffffff', 8=>'#808080', 9=>'#c0c0c0'];
                        $currentLayer['color_hex'] = $colors[$cIdx] ?? '#38bdf8';
                        if (intval($val) < 0) $currentLayer['visible'] = false;
                    } elseif ($code === 70 && $currentLayer) {
                        if (intval($val) & 1) $currentLayer['frozen'] = true;
                    }
                }
            }

            // 2. ENTITIES 추출 (회전각, 높이, 정렬좌표 완벽 읽기)
            if ($inEntities) {
                if ($code === 0) {
                    if ($currentEntity) $entities[] = $currentEntity;
                    $entityType = strtoupper($val);

                    if (in_array($entityType, ['LINE', 'LWPOLYLINE', 'POLYLINE', 'CIRCLE', 'TEXT', 'MTEXT'])) {
                        $currentEntity = [
                            'type' => $entityType,
                            'layer' => '0',
                            'x' => 0, 'y' => 0,
                            'height' => 150.0, // DXF Group 40 (원본 CAD 높이)
                            'rotation' => 0.0, // DXF Group 50 (원본 CAD 회전각)
                            'radius' => 0.0,   // DXF Group 40 (CIRCLE)
                            'align_x' => 0, 'align_y' => 0, // DXF Group 11, 21 (정렬 기준 좌표)
                            'align_h' => 0, 'align_v' => 0  // DXF Group 72, 73 (정렬 코드)
                        ];
                    } else {
                        $currentEntity = null;
                    }
                } elseif ($currentEntity) {
                    switch ($code) {
                        case 8:  $currentEntity['layer'] = $val; break;
                        case 10:
                            if ($currentEntity['type'] === 'LINE') $currentEntity['x1'] = floatval($val);
                            else $currentEntity['x'] = floatval($val);
                            break;
                        case 20:
                            if ($currentEntity['type'] === 'LINE') $currentEntity['y1'] = floatval($val);
                            else $currentEntity['y'] = floatval($val);
                            break;
                        case 11: // 💡 LINE x2 또는 TEXT/MTEXT 정렬 X 좌표
                            if ($currentEntity['type'] === 'LINE') $currentEntity['x2'] = floatval($val);
                            else $currentEntity['align_x'] = floatval($val);
                            break;
                        case 21: // 💡 LINE y2 또는 TEXT/MTEXT 정렬 Y 좌표
                            if ($currentEntity['type'] === 'LINE') $currentEntity['y2'] = floatval($val);
                            else $currentEntity['align_y'] = floatval($val);
                            break;
                        case 40: // 💡 문자 높이 (TEXT) 또는 원 반지름 (CIRCLE)
                            if ($currentEntity['type'] === 'CIRCLE') $currentEntity['radius'] = floatval($val);
                            else $currentEntity['height'] = floatval($val);
                            break;
                        case 50: // 💡 원본 CAD 회전 각도 (Degree)
                            $currentEntity['rotation'] = floatval($val);
                            break;
                        case 72: // 💡 가로 정렬 방식 (Group 72)
                            $currentEntity['align_h'] = intval($val);
                            break;
                        case 73: // 💡 세로 정렬 방식 (Group 73)
                            $currentEntity['align_v'] = intval($val);
                            break;
                        case 1:  $currentEntity['text'] = $val; break;
                    }

                    if ($currentEntity['type'] === 'LWPOLYLINE') {
                        if (!isset($currentEntity['points'])) $currentEntity['points'] = [];
                        if ($code === 10) $currentEntity['points'][] = ['x' => floatval($val), 'y' => 0];
                        elseif ($code === 20 && count($currentEntity['points']) > 0) {
                            $currentEntity['points'][count($currentEntity['points']) - 1]['y'] = floatval($val);
                        }
                    }
                }
            }
        }
        if ($currentEntity) $entities[] = $currentEntity;
        if ($currentLayer) $layers[$currentLayer['name']] = $currentLayer;

        return ['entities' => $entities, 'layers' => $layers];
    }
}

// $entities_json이 외부에서 전달되지 않았거나 빈 경우 파서 자동 트리거
if (empty($entities_json) && !empty($active_file)) {
    $dxf_target_path = G5_DATA_PATH . '/dxf/' . $active_file;
    $parsed_data = parseDxfEntitiesDirect($dxf_target_path);
    $entities_json = json_encode($parsed_data['entities'], JSON_UNESCAPED_UNICODE);
    if (empty($layers_json)) {
        $layers_json = json_encode($parsed_data['layers'], JSON_UNESCAPED_UNICODE);
    }
}
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

// DaisyUI 레이어 관리 UI 렌더링 (기존 유지)
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
        else if ((e.type === 'CIRCLE' || e.type === 'TEXT' || e.type === 'MTEXT') && e.x !== undefined) { pts.push({x: e.x, y: e.y}); }
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

// 메인 렌더링 루프 (원본 CAD 1:1 보정)
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
            const realRadius = parseFloat(e.radius || 50.0);
            ctx.beginPath();
            ctx.arc(e.x - centerX, e.y - centerY, realRadius, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.fill();
            ctx.lineWidth = Math.max(1, 1.0 / scale);
            ctx.strokeStyle = color;
            ctx.stroke();
        } else if (e.type === 'TEXT' || e.type === 'MTEXT') {
            // 1. DXF 정렬 좌표(align_x, align_y) 우선 적용
            const hasAlign = (e.align_x !== undefined && e.align_x !== null && (e.align_x !== 0 || e.align_y !== 0));
            const px = (hasAlign ? parseFloat(e.align_x) : parseFloat(e.x)) - centerX;
            const py = (hasAlign ? parseFloat(e.align_y) : parseFloat(e.y)) - centerY;

            // 2. DXF 객체별 각각의 원본 CAD 높이(Group 40) 및 회전 각도(Group 50)
            const cadHeight   = parseFloat(e.height) || 150.0;
            const cadRotation = parseFloat(e.rotation) || 0.0;

            if (cadHeight * scale > 0.5) {
                ctx.save();
                
                // 원본 CAD 좌표 위치로 이동
                ctx.translate(px, py);

                // Y축 반전(-scale) 좌표계에 맞춘 회전 처리
                const rad = -cadRotation * (Math.PI / 180.0);
                ctx.rotate(rad);

                // 글자가 뒤집히지 않도록 Y축 상하 반전 연산
                ctx.scale(1, -1);

                // CAD 객체 실측 문자 높이(cadHeight) 적용
                ctx.font = `bold ${cadHeight}px "Malgun Gothic", sans-serif`;
                ctx.fillStyle = color;

                // 3. DXF Group 72, 73 정렬 코드 1:1 매핑
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