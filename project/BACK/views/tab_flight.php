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
.flight-detail-shell {
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.62), rgba(15, 23, 42, 0.42));
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 10px;
    padding: 8px 10px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
}
.flight-detail-grid {
    display: grid;
    grid-template-columns: minmax(280px, 1.4fr) minmax(220px, 1fr) minmax(140px, 0.55fr) auto;
    gap: 6px;
    align-items: center;
}
.flight-detail-item {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 7px;
    height: 28px;
    padding: 0 8px;
    border: 1px solid transparent;
    border-radius: 7px;
    background: transparent;
    font-family: Pretendard, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    cursor: pointer;
    outline: none;
}
.flight-detail-item:hover {
    background: rgba(148, 163, 184, 0.08);
}
.flight-detail-item:focus-visible {
    border-color: rgba(129, 140, 248, 0.45);
}
.flight-detail-label {
    flex: 0 0 auto;
    width: 42px;
    color: rgba(148, 163, 184, 0.72);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0;
    text-align: left;
}
.flight-detail-value {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: Pretendard, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 12px;
    font-weight: 700;
    color: rgba(226, 232, 240, 0.88);
}
.flight-detail-count {
    flex: 0 0 auto;
    height: 17px;
    min-width: 17px;
    padding: 0 5px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 900;
    background: rgba(148, 163, 184, 0.13);
    color: rgba(226, 232, 240, 0.72);
}
.flight-detail-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.flight-detail-actions .btn {
    height: 28px;
    min-height: 28px;
    border-radius: 7px;
    font-family: Pretendard, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 11px;
    font-weight: 800;
}
.flight-action-secondary {
    border-color: rgba(148, 163, 184, 0.22) !important;
    color: rgba(226, 232, 240, 0.76) !important;
    background: rgba(15, 23, 42, 0.22) !important;
}
.flight-action-secondary:hover {
    border-color: rgba(56, 189, 248, 0.55) !important;
    background: rgba(14, 165, 233, 0.12) !important;
    color: rgb(125, 211, 252) !important;
}
.flight-action-primary {
    border: 1px solid rgba(129, 140, 248, 0.35) !important;
    background: rgba(99, 102, 241, 0.18) !important;
    color: rgba(224, 231, 255, 0.92) !important;
}
.flight-action-primary:hover {
    background: rgba(99, 102, 241, 0.28) !important;
}
@media (max-width: 1100px) {
    .flight-detail-grid {
        grid-template-columns: 1fr 1fr;
    }
    .flight-detail-actions {
        grid-column: 1 / -1;
        justify-content: flex-start;
    }
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
                    <tr id="sub_row_<?php echo $row['date_id']; ?>" class="sub-panel-row hidden bg-base-200/20 border-b border-base-content/10">
                        <td colspan="13" class="px-6 py-2 text-left">
                            <?php 
                                $display_eo = ($row['eo_file_name'] ?: $eo_filename) ?: '미등록';
                                $display_idx = $idx_filename ?: '미생성';
                                $has_inspect_result = (mb_strpos((string)$row['eo_file_name'], '_검수완료') !== false);
                            ?>
                            <div class="flight-detail-shell">
                                <div class="flight-detail-grid">
                                    <button type="button"
                                            class="flight-detail-item text-left hover:border-primary/60 transition-colors"
                                            title="EO 파일 목록 확인 및 교체"
                                            ondblclick="event.stopPropagation(); openEOFilePicker('<?php echo $row['date_id']; ?>', '<?php echo $row['flight_date']; ?>');"
                                            onclick="event.stopPropagation(); openEOFilePicker('<?php echo $row['date_id']; ?>', '<?php echo $row['flight_date']; ?>');">
                                        <span class="flight-detail-label">EO</span>
                                        <span class="flight-detail-value"><?php echo htmlspecialchars($display_eo); ?></span>
                                        <?php if($eo_count > 0) { ?><span class="flight-detail-count"><?php echo $eo_count; ?></span><?php } ?>
                                    </button>

                                    <button type="button"
                                            class="flight-detail-item text-left hover:border-secondary/60 transition-colors"
                                            title="인덱스 도면 화면으로 이동"
                                            onclick="event.stopPropagation(); location.href='index_view.php?prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $row['date_id']; ?>';">
                                        <span class="flight-detail-label">INDEX</span>
                                        <span class="flight-detail-value <?php echo $idx_filename ? '' : 'text-error/80'; ?>"><?php echo htmlspecialchars($display_idx); ?></span>
                                    </button>

                                    <button type="button"
                                            class="flight-detail-item text-left hover:border-accent/60 transition-colors"
                                            title="촬영기록부 및 코스별검사표 관리"
                                            onclick="event.stopPropagation(); openDocManagerModal(<?php echo (int)$prj_id; ?>, <?php echo (int)$row['date_id']; ?>, '<?php echo htmlspecialchars($date_str, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($prj['prj_name'] ?? '', ENT_QUOTES); ?>');">
                                        <span class="flight-detail-label">문서</span>
                                        <span class="flight-detail-value"><?php echo $doc_count > 0 ? '등록됨' : '미등록'; ?></span>
                                        <span class="flight-detail-count"><?php echo $doc_count; ?></span>
                                    </button>

                                    <div class="flight-detail-actions">
                                        <button type="button"
                                                class="btn btn-xs btn-outline flight-action-secondary gap-1 <?php echo $duplicate_video_shots > 0 ? '' : 'btn-disabled opacity-45'; ?>"
                                                <?php echo $duplicate_video_shots > 0 ? '' : 'disabled'; ?>
                                                title="<?php echo $duplicate_video_shots > 0 ? '다른 촬영일과 겹치는 EO 사진번호를 확인하고 사용할 일자를 선택합니다.' : '중복영상이 없습니다.'; ?>"
                                                onclick="event.stopPropagation(); openDuplicateVideoModal(<?php echo (int)$row['date_id']; ?>, '<?php echo htmlspecialchars($date_str, ENT_QUOTES); ?>');">
                                            <i class="fa-solid fa-code-compare text-[10px]"></i>
                                            <span>중복확인</span>
                                            <?php if ($duplicate_video_shots > 0) { ?>
                                                <span class="font-mono text-[10px]"><?php echo number_format($duplicate_video_shots); ?></span>
                                            <?php } ?>
                                        </button>
                                        <button type="button" class="btn btn-xs flight-action-primary gap-1" 
                                                onclick="event.stopPropagation(); openFlightInspectModal('<?php echo $row['date_id']; ?>', '<?php echo $row['flight_date']; ?>', '<?php echo $row['total_shots']; ?>', '<?php echo $row['used_shots']; ?>', '<?php echo $row['unused_shots']; ?>', '<?php echo $row['reshoot_shots']; ?>')">
                                            <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                            <span><?php echo $has_inspect_result ? '검수수정' : '검수입력'; ?></span>
                                        </button>
                                    </div>
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
