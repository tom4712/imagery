<?php 
if (!defined('_GNUBOARD_')) exit; 
if (!isset($base_dir)) {
    $base_dir = 'E:\#KYS_IMAGERY_SERVER\\' . ($prj['prj_name'] ?? '');
}
?>

<div id="tab-flight" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar">
    <div class="overflow-x-auto">
        <!-- 삭제 처리 전용 폼 -->
        <form id="form_flight_delete" method="post" action="action.php">
            <input type="hidden" name="action" value="delete_flight_dates">
            <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
            
            <table class="table table-fixed w-full text-center select-none">
                <thead class="bg-base-200/60 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
                    <tr>
                        <th class="w-12">
                            <input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded" onclick="toggleAllCheckboxes(this, 'flight_ids')">
                        </th>
                        <th class="w-28 text-left px-3">촬영일자</th>
                        <th class="w-36 text-left px-3">이름 (구분명)</th>
                        <th class="w-24">센서명</th>
                        <th class="text-left px-3">연관 블럭</th>
                        <th class="w-20">전체매수</th>
                        <th class="w-20">사용매수</th>
                        <th class="w-20">상태</th>
                        <th class="w-12">상세</th>
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
                        
                        $eo_dir = $date_dir . '\\EO';
                        $eo_enc = iconv('UTF-8', 'CP949//IGNORE', $eo_dir);
                        $eo_files = (is_dir($eo_enc)) ? array_diff(scandir($eo_enc), ['.', '..']) : [];
                        $eo_filename = !empty($eo_files) ? iconv('CP949', 'UTF-8//IGNORE', reset($eo_files)) : ($row['eo_file_name'] ?: '');

                        $idx_dir = $date_dir . '\\INDEX';
                        $idx_enc = iconv('UTF-8', 'CP949//IGNORE', $idx_dir);
                        $idx_files = (is_dir($idx_enc)) ? array_diff(scandir($idx_enc), ['.', '..']) : [];
                        $idx_filename = !empty($idx_files) ? iconv('CP949', 'UTF-8//IGNORE', reset($idx_files)) : '';

                        $doc_dir = $date_dir . '\\문서';
                        $doc_enc = iconv('UTF-8', 'CP949//IGNORE', $doc_dir);
                        $doc_files = (is_dir($doc_enc)) ? array_diff(scandir($doc_enc), ['.', '..']) : [];
                        $doc_count = count($doc_files);
                    ?>
                    
                    <tr class="hover:bg-base-200/50 transition-colors cursor-pointer group" onclick="toggleFlightSubRow('flight_sub_<?php echo $row['date_id']; ?>', this)">
                        <td onclick="event.stopPropagation();">
                            <input type="checkbox" name="flight_ids[]" value="<?php echo $row['date_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded chk-flight-item" data-date="<?php echo htmlspecialchars($row['flight_date']); ?>">
                        </td>
                        <td class="font-mono font-bold text-left px-3 text-primary flex items-center gap-1.5">
                            <i class="fa-solid fa-plane-departure text-[11px] opacity-70"></i>
                            <span><?php echo $row['flight_date']; ?></span>
                        </td>
                        <td class="text-left px-3 font-medium text-base-content/90 truncate" title="<?php echo htmlspecialchars($row['flight_name']); ?>">
                            <?php echo htmlspecialchars($row['flight_name'] ?: '-'); ?>
                        </td>
                        <td class="font-mono text-base-content/70"><?php echo htmlspecialchars($row['sensor_name'] ?: '-'); ?></td>
                        <td class="text-left px-3">
                            <?php if($row['matched_blocks']) { 
                                $blks = explode(',', $row['matched_blocks']);
                                foreach($blks as $b) {
                                    echo '<span class="badge badge-neutral badge-xs font-mono py-1 px-1.5 mr-1 text-[10px]">'.trim($b).'</span>';
                                }
                            } else { echo '<span class="text-base-content/30">-</span>'; } ?>
                        </td>
                        <td class="font-mono font-bold"><?php echo number_format($row['total_shots']); ?></td>
                        <td class="font-mono text-success font-bold"><?php echo number_format($row['used_shots']); ?></td>
                        
                        <td onclick="event.stopPropagation();" class="py-2">
                            <a href="action.php?action=toggle_flight_status&prj_id=<?php echo $prj_id; ?>&date_id=<?php echo $row['date_id']; ?>" 
                               class="inline-flex items-center justify-center p-1.5 rounded-full hover:bg-base-content/10 transition-transform active:scale-90"
                               title="<?php echo $is_active ? '현재 활성 (클릭하여 비활성화)' : '현재 비활성 (클릭하여 활성화)'; ?>">
                                <?php if ($is_active) { ?>
                                    <span class="relative flex h-4 w-4">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-4 w-4 bg-success shadow-md shadow-success/50 border border-white/20"></span>
                                    </span>
                                <?php } else { ?>
                                    <span class="relative flex h-4 w-4">
                                        <span class="relative inline-flex rounded-full h-4 w-4 bg-error shadow-md shadow-error/50 border border-white/20 opacity-80"></span>
                                    </span>
                                <?php } ?>
                            </a>
                        </td>

                        <td class="text-base-content/40 group-hover:text-primary transition-transform">
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 sub-arrow-icon"></i>
                        </td>
                    </tr>

                    <tr id="flight_sub_<?php echo $row['date_id']; ?>" class="hidden bg-base-200/30 border-b border-base-content/10 transition-all">
                        <td colspan="9" class="p-4 text-left">
                            <div class="bg-base-100/80 rounded-xl p-3.5 border border-base-content/10 shadow-inner flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="flex items-center gap-1.5 bg-base-200/70 border border-base-content/10 rounded-lg px-3 py-1.5 shadow-sm">
                                        <span class="text-sm">🧭</span>
                                        <span class="font-bold text-xs text-base-content/70">EO:</span>
                                        <?php if($eo_filename) { ?>
                                            <span class="badge badge-primary badge-sm font-mono text-xs font-semibold" title="<?php echo htmlspecialchars($eo_filename); ?>">
                                                <?php echo htmlspecialchars(mb_strimwidth($eo_filename, 0, 22, '...')); ?>
                                            </span>
                                        <?php } else { ?>
                                            <span class="badge badge-ghost badge-sm text-xs opacity-60">미등록</span>
                                        <?php } ?>
                                    </div>

                                    <div class="flex items-center gap-1.5 bg-base-200/70 border border-base-content/10 rounded-lg px-3 py-1.5 shadow-sm">
                                        <span class="text-sm">🗺️</span>
                                        <span class="font-bold text-xs text-base-content/70">INDEX:</span>
                                        <?php if($idx_filename) { ?>
                                            <span class="badge badge-secondary badge-sm font-mono text-xs font-semibold" title="<?php echo htmlspecialchars($idx_filename); ?>">
                                                <?php echo htmlspecialchars(mb_strimwidth($idx_filename, 0, 22, '...')); ?>
                                            </span>
                                        <?php } else { ?>
                                            <span class="badge badge-error badge-outline badge-sm text-xs font-medium">미생성</span>
                                        <?php } ?>
                                    </div>

                                    <div class="flex items-center gap-1.5 bg-base-200/70 border border-base-content/10 rounded-lg px-3 py-1.5 shadow-sm">
                                        <span class="text-sm">📑</span>
                                        <span class="font-bold text-xs text-base-content/70">문서:</span>
                                        <?php if($doc_count > 0) { ?>
                                            <span class="badge badge-accent badge-sm font-mono text-xs font-bold"><?php echo $doc_count; ?>건 등록됨</span>
                                        <?php } else { ?>
                                            <span class="badge badge-ghost badge-sm text-xs opacity-60">0건</span>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn btn-primary btn-xs rounded-lg font-bold gap-1 shadow-sm shadow-primary/20" 
                                            onclick="openFlightInspectModal('<?php echo $row['date_id']; ?>', '<?php echo $row['flight_date']; ?>', '<?php echo $row['total_shots']; ?>', '<?php echo $row['used_shots']; ?>', '<?php echo $row['reshoot_shots']; ?>')">
                                        <span>✍️ 검수내역 입력</span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php } if(!$has_dates) { ?>
                    <tr>
                        <td colspan="9" class="py-20 text-base-content/40 font-bold text-sm">
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