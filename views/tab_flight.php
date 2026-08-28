<?php 
if (!defined('_GNUBOARD_')) exit; 
if (!isset($base_dir)) {
    $base_dir = img_project_path($prj['prj_name'] ?? '');
}
?>

<style>
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
                            <span class="text-warning">중복미사용</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th px-2 min-w-[70px] bg-base-200/90" data-col="reshoot_shots">
                            <span class="text-error">재촬영</span>
                            <div class="col-resizer"></div>
                        </th>
                        <th class="resizable-th px-2 min-w-[80px] bg-base-200/90" data-col="duplicate_video_shots">
                            <span class="text-info">중복영상</span>
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
                        $duplicate_video_shots = isset($flight_duplicate_video_counts[(int)$row['date_id']]) ? (int)$flight_duplicate_video_counts[(int)$row['date_id']] : 0;

                        $date_dir = $base_dir . '\\date\\' . $date_str;
                        
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

                        // 문서 폴더 스캔 (오직 문서 폴더만 카운트)
                        $doc_dir = $date_dir . '\\문서';
                        $doc_files = [];
                        if (is_dir($doc_dir)) {
                            $sc = scandir($doc_dir);
                            foreach ($sc as $f) {
                                if ($f !== '.' && $f !== '..' && is_file($doc_dir . '\\' . $f)) {
                                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                                    if (in_array($ext, ['xlsx', 'xls', 'xlsm'])) {
                                        $doc_files[] = $f;
                                    }
                                }
                            }
                        }
                        $doc_count = count($doc_files);
                    ?>
                    
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
                        <td class="font-mono text-info font-semibold"><?php echo number_format($duplicate_video_shots); ?></td>
                        <td class="font-medium text-base-content/80 truncate px-2">👤 <?php echo htmlspecialchars($row['mb_name'] ?: '시스템'); ?></td>
                        
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
                        <td colspan="13" class="p-3 text-left">
                            <div class="bg-base-100/95 rounded-xl p-3 border border-base-content/10 shadow-inner flex flex-wrap items-center justify-between gap-3">
                                
                                <div class="flex items-center gap-2 flex-wrap">
                                    <!-- EO 배지 -->
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
                                        <?php } else { ?>
                                            <span class="badge badge-ghost badge-sm text-[11px] opacity-60">미등록</span>
                                        <?php } ?>
                                    </div>

                                    <!-- INDEX 배지 -->
                                    <div class="flex items-center gap-1.5 bg-base-200/80 border border-base-content/10 rounded-lg px-2.5 py-1.5 shadow-sm cursor-pointer hover:border-secondary transition-colors group/idx"
                                         title="더블클릭하여 인덱스 도면 화면으로 이동"
                                         ondblclick="event.stopPropagation(); location.href='index_view.php?prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $row['date_id']; ?>';">
                                        <span class="text-sm">🗺️</span>
                                        <span class="font-bold text-xs text-base-content/70">INDEX:</span>
                                        <?php if($idx_filename) { ?>
                                            <span class="badge badge-secondary badge-sm font-mono text-[11px] font-semibold flex items-center gap-1">
                                                <span><?php echo htmlspecialchars(mb_strimwidth($idx_filename, 0, 26, '...')); ?></span>
                                            </span>
                                        <?php } else { ?>
                                            <span class="badge badge-error badge-outline badge-sm text-[11px]">미생성</span>
                                        <?php } ?>
                                    </div>

                                    <!-- 💡 문서 배지: 더블클릭 시 문서 관리 모달 오픈 -->
                                    <div class="flex items-center gap-1.5 bg-base-200/80 border border-base-content/10 rounded-lg px-2.5 py-1.5 shadow-sm cursor-pointer hover:border-accent transition-colors group/doc"
                                         title="더블클릭하여 촬영기록부 및 코스별검사표 관리"
                                         ondblclick="event.stopPropagation(); openDocManagerModal(<?php echo (int)$prj_id; ?>, <?php echo (int)$row['date_id']; ?>, '<?php echo htmlspecialchars($date_str, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($prj['prj_name'] ?? '', ENT_QUOTES); ?>');">
                                        <span class="text-sm">📑</span>
                                        <span class="font-bold text-xs text-base-content/70">문서:</span>
                                        <?php if($doc_count > 0) { ?>
                                            <span class="badge badge-accent badge-sm font-mono text-[11px] font-bold"><?php echo $doc_count; ?>건 등록됨</span>
                                            <span class="text-[10px] text-accent opacity-0 group-hover/doc:opacity-100 transition-opacity font-bold ml-1">⚡ 더블클릭</span>
                                        <?php } else { ?>
                                            <span class="badge badge-ghost badge-sm text-[11px] opacity-60">0건 (더블클릭)</span>
                                        <?php } ?>
                                    </div>
                                </div>

                                <?php $has_inspect_result = (mb_strpos((string)$row['eo_file_name'], '_검수완료') !== false); ?>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn btn-xs rounded-lg font-bold gap-1 shadow-sm <?php echo $has_inspect_result ? 'btn-warning shadow-warning/20' : 'btn-primary shadow-primary/20'; ?>" 
                                            onclick="openFlightInspectModal('<?php echo $row['date_id']; ?>', '<?php echo $row['flight_date']; ?>', '<?php echo $row['total_shots']; ?>', '<?php echo $row['used_shots']; ?>', '<?php echo $row['unused_shots']; ?>', '<?php echo $row['reshoot_shots']; ?>')">
                                        <?php if ($has_inspect_result) { ?>
                                            <span>✍️ 검수내역 수정</span>
                                        <?php } else { ?>
                                            <span>✍️ 검수내역 입력</span>
                                        <?php } ?>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php } if(!$has_dates) { ?>
                    <tr>
                        <td colspan="13" class="py-20 text-base-content/40 font-bold text-sm">
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
