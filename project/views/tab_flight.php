<?php 
if (!defined('_GNUBOARD_')) exit; 
if (!isset($base_dir)) {
    $base_dir = 'E:\#KYS_IMAGERY_SERVER\\' . ($prj['prj_name'] ?? '');
}
?>

<div id="tab-flight" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar">
    <div class="overflow-x-auto">
        <form id="form_flight_delete" method="post" action="action.php">
            <input type="hidden" name="action" value="delete_flight_dates">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            
            <table class="table table-fixed w-full text-center select-none">
                <thead class="bg-base-200/60 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
                    <tr>
                        <th class="w-10"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded" onclick="toggleAllCheckboxes(this, 'flight_ids')"></th>
                        <th class="w-24 text-left px-2">촬영일자</th>
                        <th class="w-28 text-left px-2">이름 (구분명)</th>
                        <th class="w-20">센서명</th>
                        <th class="text-left px-2">연관 블럭</th>
                        <th class="w-16">전체</th>
                        <th class="w-16">사용</th>
                        <th class="w-16 text-warning">미사용</th>
                        <th class="w-16 text-error">재촬영</th>
                        <th class="w-20">등록자</th>
                        <th class="w-14">상태</th>
                        <th class="w-10">상세</th>
                    </tr>
                </thead>
                <tbody class="text-xs divide-y divide-base-content/5 font-sans">
                    <?php 
                    $has_dates = false;
                    while($row = sql_fetch_array($flight_dates)) { 
                        $has_dates = true;
                        $date_str = $row['flight_date'];
                        $is_active = ($row['status'] === 'ACTIVE');

                        $date_dir = $base_dir . '\\date\\' . $date_str;
                        
                        // EO 파일 실시간 스캔
                        $eo_dir = $date_dir . '\\EO';
                        $eo_enc = iconv('UTF-8', 'CP949//IGNORE', $eo_dir);
                        $eo_files = (is_dir($eo_enc)) ? array_diff(scandir($eo_enc), ['.', '..']) : [];
                        $eo_filename = !empty($eo_files) ? iconv('CP949', 'UTF-8//IGNORE', reset($eo_files)) : ($row['eo_file_name'] ?: '');
                        $eo_count = count($eo_files);

                        // INDEX 파일 스캔
                        $idx_dir = $date_dir . '\\INDEX';
                        $idx_enc = iconv('UTF-8', 'CP949//IGNORE', $idx_dir);
                        $idx_files = (is_dir($idx_enc)) ? array_diff(scandir($idx_enc), ['.', '..']) : [];
                        $idx_filename = !empty($idx_files) ? iconv('CP949', 'UTF-8//IGNORE', reset($idx_files)) : '';

                        // 문서 스캔
                        $doc_dir = $date_dir . '\\문서';
                        $doc_enc = iconv('UTF-8', 'CP949//IGNORE', $doc_dir);
                        $doc_files = (is_dir($doc_enc)) ? array_diff(scandir($doc_enc), ['.', '..']) : [];
                        $doc_count = count($doc_files);
                    ?>
                    
                    <tr class="hover:bg-base-200/50 transition-colors cursor-pointer group" onclick="toggleFlightSubRow('flight_sub_<?php echo $row['date_id']; ?>', this)">
                        <td onclick="event.stopPropagation();">
                            <input type="checkbox" name="flight_ids[]" value="<?php echo $row['date_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded chk-flight-item" data-date="<?php echo htmlspecialchars($row['flight_date']); ?>">
                        </td>
                        <td class="font-mono font-bold text-left px-2 text-primary flex items-center gap-1.5">
                            <i class="fa-solid fa-plane-departure text-[10px] opacity-70"></i>
                            <span><?php echo $row['flight_date']; ?></span>
                        </td>
                        <td class="text-left px-2 font-medium text-base-content/90 truncate" title="<?php echo htmlspecialchars($row['flight_name']); ?>">
                            <?php echo htmlspecialchars($row['flight_name'] ?: '-'); ?>
                        </td>
                        <td class="font-mono text-base-content/70 truncate"><?php echo htmlspecialchars($row['sensor_name'] ?: '-'); ?></td>
                        <td class="text-left px-2 truncate">
                            <?php if($row['matched_blocks']) { 
                                $blks = explode(',', $row['matched_blocks']);
                                foreach($blks as $b) {
                                    echo '<span class="badge badge-neutral badge-xs font-mono py-0.5 px-1.5 mr-1 text-[10px]">'.trim($b).'</span>';
                                }
                            } else { echo '<span class="text-base-content/30">-</span>'; } ?>
                        </td>
                        <td class="font-mono font-bold"><?php echo number_format($row['total_shots']); ?></td>
                        <td class="font-mono text-success font-bold"><?php echo number_format($row['used_shots']); ?></td>
                        <td class="font-mono text-warning font-semibold"><?php echo number_format($row['unused_shots']); ?></td>
                        <td class="font-mono text-error font-semibold"><?php echo number_format($row['reshoot_shots']); ?></td>
                        <td class="font-medium text-base-content/80 truncate">👤 <?php echo htmlspecialchars($row['mb_name'] ?: '시스템'); ?></td>
                        
                        <td onclick="event.stopPropagation();" class="py-2">
                            <a href="action.php?action=toggle_flight_status&prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $row['date_id']; ?>" 
                               class="inline-flex items-center justify-center p-1 rounded-full hover:bg-base-content/10 transition-transform active:scale-90"
                               title="<?php echo $is_active ? '활성 (클릭하여 제외)' : '비활성 (클릭하여 포함)'; ?>">
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

                        <td class="text-base-content/40 group-hover:text-primary transition-transform">
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 sub-arrow-icon"></i>
                        </td>
                    </tr>

                    <!-- 아코디언 드롭다운 서브 패널 -->
                    <tr id="flight_sub_<?php echo $row['date_id']; ?>" class="hidden bg-base-200/30 border-b border-base-content/10 transition-all">
                        <td colspan="12" class="p-3 text-left">
                            <div class="bg-base-100/90 rounded-xl p-3 border border-base-content/10 shadow-inner flex flex-wrap items-center justify-between gap-3">
                                
                                <div class="flex items-center gap-2 flex-wrap">
                                    <!-- 🧭 EO 배지: 더블클릭 이벤트 바인딩 -->
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
                                            <span class="text-[10px] text-primary opacity-0 group-hover/eo:opacity-100 transition-opacity font-bold">⚡ 더블클릭</span>
                                        <?php } else { ?>
                                            <span class="badge badge-ghost badge-sm text-[11px] opacity-60">미등록 (더블클릭)</span>
                                        <?php } ?>
                                    </div>

                                    <!-- 🗺️ INDEX 확인 -->
                                    <div class="flex items-center gap-1.5 bg-base-200/80 border border-base-content/10 rounded-lg px-2.5 py-1.5 shadow-sm">
                                        <span class="text-sm">🗺️</span>
                                        <span class="font-bold text-xs text-base-content/70">INDEX:</span>
                                        <?php if($idx_filename) { ?>
                                            <span class="badge badge-secondary badge-sm font-mono text-[11px] font-semibold" title="<?php echo htmlspecialchars($idx_filename); ?>">
                                                <?php echo htmlspecialchars(mb_strimwidth($idx_filename, 0, 24, '...')); ?>
                                            </span>
                                        <?php } else { ?>
                                            <span class="badge badge-error badge-outline badge-sm text-[11px] font-medium">미생성</span>
                                        <?php } ?>
                                    </div>

                                    <!-- 📑 문서 확인 -->
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
function toggleFlightSubRow(subRowId, masterRow) {
    const subRow = document.getElementById(subRowId);
    if (!subRow) return;

    const icon = masterRow.querySelector('.sub-arrow-icon');
    if (subRow.classList.contains('hidden')) {
        subRow.classList.remove('hidden');
        if (icon) icon.classList.add('rotate-180', 'text-primary');
    } else {
        subRow.classList.add('hidden');
        if (icon) icon.classList.remove('rotate-180', 'text-primary');
    }
}
</script>