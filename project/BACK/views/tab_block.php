<?php if (!defined('_GNUBOARD_')) exit; ?>

<style>
.block-sub-row.hidden { display: none !important; }
.block-metric-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(120px, 1fr)) auto;
    gap: 8px;
    align-items: center;
}
.block-metric-item {
    min-width: 0;
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.38);
    padding: 8px 10px;
}
.block-metric-label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    color: rgba(148, 163, 184, 0.72);
}
.block-metric-value {
    display: block;
    margin-top: 2px;
    font-family: Pretendard, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 15px;
    font-weight: 900;
    color: rgba(226, 232, 240, 0.94);
}
.block-action-btn {
    height: 32px;
    min-height: 32px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 800;
}
@media (max-width: 1200px) {
    .block-metric-grid { grid-template-columns: repeat(3, minmax(120px, 1fr)); }
}
</style>

<div id="tab-block" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar hidden">
    <form id="form_block_delete" method="post" action="action.php">
        <input type="hidden" name="action" value="delete_blocks">
        <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
        
        <table class="table table-fixed w-full text-center">
            <thead class="bg-base-200/50 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
                <tr>
                    <th class="w-16"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded" onclick="toggleAllCheckboxes(this, 'block_ids')"></th>
                    <th class="w-28 text-left px-4">블럭명</th>
                    <th class="w-40 text-left px-4">포함 코스</th>
                    <th class="w-24">코스 수</th>
                    <th class="w-24">설계매수</th>
                    <th class="w-24">촬영매수</th>
                    <th class="w-24">공정율</th>
                    <th class="w-28">등록자</th>
                    <th class="w-32">등록일자</th>
                    <th class="w-12">상세</th>
                </tr>
            </thead>
            <tbody class="text-xs divide-y divide-base-content/5">
                <?php 
                $has_blocks = false;
                while($row = sql_fetch_array($blocks)) { 
                    $has_blocks = true;
                    $block_id = (int)$row['block_id'];
                    $metrics = $block_metrics[$block_id] ?? [
                        'design_count' => img_block_design_count($row['line_list']),
                        'shot_count' => (int)$row['photo_count'],
                        'progress' => 0,
                        'expected_count' => 0,
                        'expected_progress' => 0
                    ];
                ?>
                <tr class="hover:bg-base-200/40 cursor-pointer block-master-row" data-target="block_sub_<?php echo $block_id; ?>">
                    <td onclick="event.stopPropagation();"><input type="checkbox" name="block_ids[]" value="<?php echo $block_id; ?>" class="checkbox checkbox-primary checkbox-xs rounded chk-block-item" data-name="<?php echo htmlspecialchars($row['block_name']); ?>"></td>
                    <td class="text-left px-4 font-bold text-primary font-mono text-sm">
                        <?php echo htmlspecialchars($row['block_name']); ?>
                    </td>
                    <td class="text-left px-4 font-mono font-medium text-base-content/80 truncate" title="<?php echo htmlspecialchars($row['line_list']); ?>">
                        <span class="badge badge-neutral badge-sm rounded-lg font-mono"><?php echo htmlspecialchars($row['line_range']); ?></span>
                    </td>
                    <td class="font-mono font-bold"><?php echo number_format($row['line_count']); ?></td>
                    <td class="font-mono text-info font-semibold"><?php echo number_format($metrics['design_count']); ?></td>
                    <td class="font-mono text-success font-semibold"><?php echo number_format($metrics['shot_count']); ?></td>
                    <td class="font-mono font-bold <?php echo $metrics['progress'] >= 100 ? 'text-success' : 'text-warning'; ?>"><?php echo number_format($metrics['progress'], 1); ?>%</td>
                    <td class="font-medium text-base-content/80">👤 <?php echo htmlspecialchars($row['mb_name']); ?></td>
                    <td class="font-mono text-base-content/60 text-[11px]"><?php echo substr($row['created_at'], 0, 10); ?></td>
                    <td class="text-base-content/40"><i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 block-arrow-icon"></i></td>
                </tr>
                <tr id="block_sub_<?php echo $block_id; ?>" class="block-sub-row hidden bg-base-200/20">
                    <td colspan="10" class="px-5 py-2 text-left">
                        <div class="block-metric-grid">
                            <div class="block-metric-item">
                                <span class="block-metric-label">블럭 설계매수</span>
                                <span class="block-metric-value"><?php echo number_format($metrics['design_count']); ?> 장</span>
                            </div>
                            <div class="block-metric-item">
                                <span class="block-metric-label">촬영매수</span>
                                <span class="block-metric-value text-success"><?php echo number_format($metrics['shot_count']); ?> 장</span>
                            </div>
                            <div class="block-metric-item">
                                <span class="block-metric-label">공정율</span>
                                <span class="block-metric-value"><?php echo number_format($metrics['progress'], 1); ?>%</span>
                            </div>
                            <div class="block-metric-item">
                                <span class="block-metric-label">예상 준공매수</span>
                                <span class="block-metric-value text-warning"><?php echo number_format($metrics['expected_count']); ?> 장</span>
                            </div>
                            <div class="block-metric-item">
                                <span class="block-metric-label">예상 공정율</span>
                                <span class="block-metric-value text-accent"><?php echo number_format($metrics['expected_progress'], 1); ?>%</span>
                            </div>
                            <div class="flex justify-end gap-1.5">
                                <a href="index_view.php?prj_id=<?php echo (int)$prj_id; ?>&block_id=<?php echo $block_id; ?>"
                                   class="btn btn-ghost btn-sm block-action-btn"
                                   onclick="event.stopPropagation();">
                                    <i class="fa-regular fa-eye text-[11px]"></i>
                                    <span>인덱스 보기</span>
                                </a>
                                <button type="button" class="btn btn-primary btn-sm block-action-btn"
                                        onclick="event.stopPropagation(); openBlockIndexModal(<?php echo $block_id; ?>, '<?php echo htmlspecialchars($row['block_name'], ENT_QUOTES); ?>');">
                                    <i class="fa-solid fa-map-location-dot text-[11px]"></i>
                                    <span>인덱스 생성</span>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php } if(!$has_blocks) { ?>
                <tr><td colspan="10" class="py-20 text-base-content/40 font-bold text-sm">등록된 블럭 DB가 없습니다. 상단의 [+ 등록] 버튼을 눌러 추가해주세요.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.block-master-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('input') || e.target.closest('button') || e.target.closest('a')) return;
            const subRow = document.getElementById(this.dataset.target);
            if (!subRow) return;
            const icon = this.querySelector('.block-arrow-icon');
            const hidden = subRow.classList.contains('hidden');
            subRow.classList.toggle('hidden', !hidden);
            if (icon) icon.classList.toggle('rotate-180', hidden);
        });
    });
});
</script>
