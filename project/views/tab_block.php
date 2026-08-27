<?php if (!defined('_GNUBOARD_')) exit; ?>

<div id="tab-block" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar hidden">
    <form id="form_block_delete" method="post" action="action.php">
        <input type="hidden" name="action" value="delete_blocks">
        <input type="hidden" name="prj_id" value="<?php echo $prj_id; ?>">
        
        <table class="table table-fixed w-full text-center">
            <thead class="bg-base-200/50 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
                <tr>
                    <th class="w-16"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded" onclick="toggleAllCheckboxes(this, 'block_ids')"></th>
                    <th class="w-28 text-left px-4">블럭명</th>
                    <th class="w-40 text-left px-4">포함 코스 (범위)</th>
                    <th class="w-24">코스 수</th>
                    <th class="w-24">사진 매수</th>
                    <th class="w-28">등록자</th>
                    <th class="w-32">등록일자</th>
                </tr>
            </thead>
            <tbody class="text-xs divide-y divide-base-content/5">
                <?php 
                $has_blocks = false;
                while($row = sql_fetch_array($blocks)) { 
                    $has_blocks = true;
                ?>
                <tr class="hover:bg-base-200/40">
                    <td><input type="checkbox" name="block_ids[]" value="<?php echo $row['block_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded chk-block-item" data-name="<?php echo htmlspecialchars($row['block_name']); ?>"></td>
                    <td class="text-left px-4 font-bold text-primary font-mono text-sm">
                        <?php echo htmlspecialchars($row['block_name']); ?>
                    </td>
                    <td class="text-left px-4 font-mono font-medium text-base-content/80 truncate" title="<?php echo htmlspecialchars($row['line_list']); ?>코스">
                        <span class="badge badge-neutral badge-sm rounded-lg font-mono"><?php echo htmlspecialchars($row['line_range']); ?></span>
                    </td>
                    <td class="font-mono font-bold"><?php echo number_format($row['line_count']); ?> 코스</td>
                    <td class="font-mono text-base-content/70"><?php echo number_format($row['photo_count']); ?> 장</td>
                    <td class="font-medium text-base-content/80">👤 <?php echo htmlspecialchars($row['mb_name']); ?></td>
                    <td class="font-mono text-base-content/60 text-[11px]"><?php echo substr($row['created_at'], 0, 10); ?></td>
                </tr>
                <?php } if(!$has_blocks) { ?>
                <tr><td colspan="7" class="py-20 text-base-content/40 font-bold text-sm">등록된 블럭 DB가 없습니다. 상단의 [+ 등록] 버튼을 눌러 추가해주세요.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </form>
</div>