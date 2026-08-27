<?php if (!defined('_GNUBOARD_')) exit; ?>

<div id="tab-qa" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar hidden">
    <table class="table table-fixed w-full text-center">
        <thead class="bg-base-200/50 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
            <tr>
                <th class="w-16"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded"></th>
                <th class="w-24">차수</th>
                <th>검수일자</th>
                <th>합격률(%)</th>
                <th>최종상태</th>
            </tr>
        </thead>
        <tbody class="text-xs divide-y divide-base-content/5">
            <?php 
            $has_qa = false;
            while($row = sql_fetch_array($qa_checks)) { 
                $has_qa = true;
            ?>
            <tr class="hover:bg-base-200/40">
                <td><input type="checkbox" name="chk_qa[]" value="<?php echo $row['qa_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded"></td>
                <td class="font-bold font-mono"><?php echo $row['round_no']; ?>차</td>
                <td class="font-mono"><?php echo $row['qa_date']; ?></td>
                <td class="font-mono font-bold text-primary"><?php echo $row['pass_rate']; ?>%</td>
                <td>
                    <span class="badge <?php echo $row['qa_status'] == '합격' ? 'badge-success' : ($row['qa_status'] == '불합격' ? 'badge-error' : 'badge-warning'); ?> badge-xs py-2 px-2.5 rounded-lg">
                        <?php echo $row['qa_status']; ?>
                    </span>
                </td>
            </tr>
            <?php } if(!$has_qa) { ?>
            <tr><td colspan="5" class="py-16 text-base-content/40 font-bold">등록된 품질검수 차수가 없습니다.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>