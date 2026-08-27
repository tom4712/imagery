<?php 
if (!defined('_GNUBOARD_')) exit; 
if (!isset($base_dir)) {
    $base_dir = 'E:\#KYS_IMAGERY_SERVER\\' . ($prj['prj_name'] ?? '');
}
?>

<style>
/* 컬럼 세로줄 리사이저 핸들 스타일 */
.resizable-th {
    position: relative;
    user-select: none;
}
.col-resizer {
    position: absolute;
    top: 0;
    right: 0;
    width: 6px;
    cursor: col-resize;
    user-select: none;
    height: 100%;
    z-index: 20;
    transition: background 0.15s ease;
}
.col-resizer:hover, .col-resizer.resizing {
    background: rgba(99, 102, 241, 0.6) !important;
}

/* 드롭다운 아코디언 애니메이션 */
.sub-panel-row {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.sub-panel-row.hidden {
    display: none !important;
}
</style>

<div id="tab-flight" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar">
    <div class="overflow-x-auto">
        <form id="form_flight_delete" method="post" action="action.php">
            <input type="hidden" name="action" value="delete_flight_dates">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            
            <table id="table_flight_resizable" class="table w-full text-center select-none dynamic-density-table table-sm border-separate border-spacing-0">
                <thead class="bg-base-200/80 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10 font-sans">
                    <tr>
                        <th class="w-12 bg-base-200/90 text-center"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded" onclick="toggleAllCheckboxes(this, 'flight_ids')"></th>
                        
                        <!-- 마우스 드래그 리사이징 컬럼 헤더 -->
                        <th class="resizable-th text-left px-3 min-w-[100px] bg-base-200/90" data-col="flight_date">
                            <span>촬영일자</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th text-left px-3 min-w-[110px] bg-base-200/90" data-col="flight_name">
                            <span>이름 (구분명)</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th px-2 min-w-[80px] bg-base-200/90" data-col="sensor_name">
                            <span>센서명</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th text-left px-3 min-w-[140px] bg-base-200/90" data-col="matched_blocks">
                            <span>연관 블럭</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th px-2 min-w-[70px] bg-base-200/90" data-col="total_shots">
                            <span>전체</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th px-2 min-w-[70px] bg-base-200/90" data-col="used_shots">
                            <span class="text-success">사용</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th px-2 min-w-[70px] bg-base-200/90" data-col="unused_shots">
                            <span class="text-warning">미사용</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th px-2 min-w-[70px] bg-base-200/90" data-col="reshoot_shots">
                            <span class="text-error">재촬영</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th px-2 min-w-[90px] bg-base-200/90" data-col="mb_name">
                            <span>등록자</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="w-16 bg-base-200/90 text-center">상태</th>
                        <th class="w-12 bg-base-200/90 text-center">상세</th>
                    </tr>
                </thead>
                <tbody class="text-xs divide-y divide-base-content/5 font-sans">
                    <?php 
                    $has_dates = false;
                    while($row = sql_fetch_array($flight_dates)) { 
                        $has_dates = true;
                        $date_str = trim($row['flight_date']);
                        $is_active = ($row['status'] === 'ACTIVE');

                        $date_dir = $base_dir . '\\date\\' . $date_str;
                        
                        // 1. EO 파일 스캔 (PHP 8.2 순수 UTF-8)
                        $eo_dir = $date_dir . '\\EO';
                        $eo_files = [];
                        if (is_dir($eo_dir)) {
                            $sc = scandir($eo_dir);
                            foreach ($sc as $f) {
                                if ($f !== '.' && $f !== '..' && is_file($eo_dir . '\\' . $f)) {
                                    $eo_files[] = $f;
                                }
                            }
                        }
                        $eo_filename = !empty($eo_files) ? reset($eo_files) : ($row['eo_file_name'] ?: '');
                        $eo_count = count($eo_files);

                        // 2. INDEX 도면 스캔 (PHP 8.2 순수 UTF-8)
                        $idx_dir = $date_dir . '\\INDEX';
                        $idx_files = [];
                        if (is_dir($idx_dir)) {
                            $sc = scandir($idx_dir);
                            foreach ($sc as $f) {
                                if ($f !== '.' && $f !== '..' && is_file($idx_dir . '\\' . $f)) {
                                    $idx_files[] = $f;
                                }
                            }
                        }
                        $idx_filename = !empty($idx_files) ? reset($idx_files) : '';
                        $idx_count = count($idx_files);

                        // 3. 문서 파일 스캔 (PHP 8.2 순수 UTF-8)
                        $doc_dir = $date_dir . '\\문서';
                        $doc_files = [];
                        if (is_dir($doc_dir)) {
                            $sc = scandir($doc_dir);
                            foreach ($sc as $f) {
                                if ($f !== '.' && $f !== '..' && is_file($doc_dir . '\\' . $f)) {
                                    $doc_files[] = $f;
                                }
                            }
                        }
                        $doc_count = count($doc_files);
                    ?>
                    
                    <!-- 메인 촬영일 마스터 행 -->
                    <tr class="hover:bg-base-200/50 transition-colors cursor-pointer group flight-master-row" 
                        data-target="sub_row_<?php echo $row['date_id']; ?>">
                        <td onclick="event.stopPropagation();" class="text-center">
                            <input type="checkbox" name="flight_ids[]" value="<?php echo $row['date_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded chk-flight-item" data-date="<?php echo htmlspecialchars($row['flight_date']); ?>">
                        </td>
                        <td class="font-mono font-bold text-left px-3 text-primary whitespace-nowrap">
                            <i class="fa-solid fa-plane-departure text-[10px] opacity-70 mr-1"></i>
                            <span><?php echo $row['flight_date']; ?></span>
                        </td>
                        <td class="text-left px-3 font-medium text-base-content/90 truncate max-w-[150px]" title="<?php echo htmlspecialchars($row['flight_name']); ?>">
                            <?php echo htmlspecialchars($row['flight_name'] ?: '-'); ?>
                        </td>
                        <td class="font-mono text-base-content/70 truncate px-2"><?php echo htmlspecialchars($row['sensor_name'] ?: '-'); ?></td>
                        <td class="text-left px-3 truncate max-w-[180px]">
                            <?php if($row['matched_blocks']) { 
                                $blks = explode(',', $row['matched_blocks']);
                                foreach($blks as $b) {
                                    echo '<span class="badge badge-neutral badge-xs font-mono py-0.5 px-1.5 mr-1 text-[10px] whitespace-nowrap">'.trim($b).'</span>';
                                }
                            } else { echo '<span class="text-base-content/30">-</span>'; } ?>
                        </td>
                        <td class="font-mono font-bold"><?php echo number_format($row['total_shots']); ?></td>
                        <td class="font-mono text-success font-bold"><?php echo number_format($row['used_shots']); ?></td>
                        <td class="font-mono text-warning font-semibold"><?php echo number_format($row['unused_shots']); ?></td>
                        <td class="font-mono text-error font-semibold"><?php echo number_format($row['reshoot_shots']); ?></td>
                        <td class="font-medium text-base-content/80 truncate px-2">👤 <?php echo htmlspecialchars($row['mb_name'] ?: '시스템'); ?></td>
                        
                        <!-- 활성/비활성 토글 -->
                        <td onclick="event.stopPropagation();" class="py-2 text-center">
                            <a href="action.php?action=toggle_flight_status&prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $row['date_id']; ?>" 
                               class="inline-flex items-center justify-center p-1 rounded-full hover:bg-base-content/10 transition-transform active:scale-90"
                               title="<?php echo $is_active ? '활성 (클릭하여 비활성화)' : '비활성 (클릭하여 활성화)'; ?>">
                                <?php if ($is_active) { ?>
                                    <span class="relative flex h-3.5 w-3.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-success shadow-md shadow-success/50 border border-white/20"></span>
                                    </span>
                                <?php } else { ?>
                                    <span class="relative flex h-3.5 w-3.5">
                                        <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-error shadow-md shadow-error/50 border border-white/20 opacity-80"></span>
                                    </span>
                                <?php } ?>
                            </a>
                        </td>

                        <td class="text-base-content/40 group-hover:text-primary transition-transform text-center">
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 sub-arrow-icon"></i>
                        </td>
                    </tr>

                    <!-- 아코디언 서브 패널 -->
                    <tr id="sub_row_<?php echo $row['date_id']; ?>" class="sub-panel-row hidden bg-base-200/40 border-b border-base-content/10">
                        <td colspan="12" class="p-3 text-left">
                            <div class="bg-base-100/95 rounded-xl p-3 border border-base-content/10 shadow-inner flex flex-wrap items-center justify-between gap-3">
                                
                                <div class="flex items-center gap-2 flex-wrap">
                                    <!-- 🧭 EO 배지: 더블클릭 팝업 -->
                                    <div class="flex items-center gap-1.5 bg-base-200/80 border border-base-content/10 rounded-lg px-2.5 py-1.5 shadow-sm cursor-pointer hover:border-primary transition-colors group/eo"
                                         title="더블클릭하여 폴더 내 EO 파일 목록 확인 및 교체"
                                         ondblclick="event.stopPropagation(); openEOFilePicker('<?php echo $row['date_id']; ?>', '<?php echo $row['flight_date']; ?>');">
                                        <span class="text-sm">🧭</span>
                                        <span class="font-bold text-xs text-base-content/70">EO:</span>
                                        <?php if($row['eo_file_name'] || $eo_filename) { 
                                            $display_eo = $row['eo_file_name'] ?: $eo_filename;
                                        ?>
                                            <span class="badge badge-primary badge-sm font-mono text-[11px] font-semibold flex items-center gap-1">
                                                <span><?php echo htmlspecialchars(mb_strimwidth($display_eo, 0, 24, '...')); ?></span>
                                                <span class="text-[9px] opacity-75">(총 <?php echo $eo_count; ?>개)</span>
                                            </span>
                                            <span class="text-[10px] text-primary opacity-0 group-hover/eo:opacity-100 transition-opacity font-bold ml-1">⚡ 더블클릭</span>
                                        <?php } else { ?>
                                            <span class="badge badge-ghost badge-sm text-[11px] opacity-60">미등록 (더블클릭)</span>
                                        <?php } ?>
                                    </div>

                                    <!-- 🗺️ INDEX 배지: 더블클릭 시 뷰어로 이동 -->
                                    <div class="flex items-center gap-1.5 bg-base-200/80 border border-base-content/10 rounded-lg px-2.5 py-1.5 shadow-sm cursor-pointer hover:border-secondary transition-colors group/idx"
                                         title="더블클릭하여 인덱스 도면 화면으로 이동"
                                         ondblclick="event.stopPropagation(); location.href='index_view.php?prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $row['date_id']; ?>';">
                                        <span class="text-sm">🗺️</span>
                                        <span class="font-bold text-xs text-base-content/70">INDEX:</span>
                                        <?php if($idx_filename) { ?>
                                            <span class="badge badge-secondary badge-sm font-mono text-[11px] font-semibold flex items-center gap-1" title="<?php echo htmlspecialchars($idx_filename); ?>">
                                                <span><?php echo htmlspecialchars(mb_strimwidth($idx_filename, 0, 26, '...')); ?></span>
                                                <span class="text-[9px] opacity-75">(총 <?php echo $idx_count; ?>개)</span>
                                            </span>
                                            <span class="text-[10px] text-secondary opacity-0 group-hover/idx:opacity-100 transition-opacity font-bold ml-1">⚡ 더블클릭</span>
                                        <?php } else { ?>
                                            <span class="badge badge-error badge-outline badge-sm text-[11px] font-medium flex items-center gap-1">
                                                <span>미생성</span>
                                                <span class="text-[9px] opacity-75">(더블클릭)</span>
                                            </span>
                                        <?php } ?>
                                    </div>

                                    <!-- 📑 문서 배지 -->
                                    <div class="flex items-center gap-1.5 bg-base-200/80 border border-base-content/10 rounded-lg px-2.5 py-1.5 shadow-sm">
                                        <span class="text-sm">📑</span>
                                        <span class="font-bold text-xs text-base-content/70">문서:</span>
                                        <?php if($doc_count > 0) { ?>
                                            <span class="badge badge-accent badge-sm font-mono text-[11px] font-bold"><?php echo $doc_count; ?>건 등록됨</span>
                                        <?php } else { ?>
                                            <span class="badge badge-ghost badge-sm text-[11px] opacity-60">0건</span>
                                        <?php } ?>
                                    </div>
                                </div>

                                <!-- 검수내역 입력 버튼 -->
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn btn-primary btn-xs rounded-lg font-bold gap-1 shadow-sm shadow-primary/20" 
                                            onclick="openFlightInspectModal('<?php echo $row['date_id']; ?>', '<?php echo $row['flight_date']; ?>', '<?php echo $row['total_shots']; ?>', '<?php echo $row['used_shots']; ?>', '<?php echo $row['unused_shots']; ?>', '<?php echo $row['reshoot_shots']; ?>')">
                                        <span>✍️ 검수내역 입력</span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php } if(!$has_dates) { ?>
                    <tr>
                        <td colspan="12" class="py-20 text-base-content/40 font-bold text-sm">
                            등록된 촬영일자가 없습니다. 상단의 [+ 촬영일 등록] 버튼을 눌러 추가해주세요.
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </form>
    </div>
</div>

<script>
// 1. 아코디언 서브 패널 토글 바인딩
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.flight-master-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('input') || e.target.closest('a') || e.target.closest('button')) {
                return;
            }

            const targetId = this.getAttribute('data-target');
            const subRow = document.getElementById(targetId);
            if (!subRow) return;

            const icon = this.querySelector('.sub-arrow-icon');
            const isHidden = subRow.classList.contains('hidden');

            if (isHidden) {
                subRow.classList.remove('hidden');
                if (icon) icon.classList.add('rotate-180', 'text-primary');
            } else {
                subRow.classList.add('hidden');
                if (icon) icon.classList.remove('rotate-180', 'text-primary');
            }
        });
    });

    // 2. 컬럼 너비 리사이징 핸들러
    const table = document.getElementById('table_flight_resizable');
    if (!table) return;

    const cols = table.querySelectorAll('th.resizable-th');
    const savedWidths = JSON.parse(localStorage.getItem('flight_table_col_widths') || '{}');

    cols.forEach(th => {
        const colKey = th.getAttribute('data-col');
        if (savedWidths[colKey]) {
            th.style.width = savedWidths[colKey] + 'px';
        }

        const resizer = th.querySelector('.col-resizer');
        if (!resizer) return;

        let startX, startWidth;

        resizer.addEventListener('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();

            startX = e.pageX;
            startWidth = th.offsetWidth;
            resizer.classList.add('resizing');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            function onMouseMove(e) {
                const newWidth = Math.max(60, startWidth + (e.pageX - startX));
                th.style.width = newWidth + 'px';
            }

            function onMouseUp() {
                resizer.classList.remove('resizing');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);

                const currentWidths = {};
                cols.forEach(c => {
                    const k = c.getAttribute('data-col');
                    if (k) currentWidths[k] = c.offsetWidth;
                });
                localStorage.setItem('flight_table_col_widths', JSON.stringify(currentWidths));
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
    });
});
</script>