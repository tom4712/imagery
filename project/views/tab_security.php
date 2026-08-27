<?php if (!defined('_GNUBOARD_')) exit; ?>

<div id="tab-security" class="tab-content-panel flex-1 overflow-y-auto custom-scrollbar hidden">
    <table class="table table-fixed w-full text-center">
        <thead class="bg-base-200/50 text-xs text-base-content/80 sticky top-0 backdrop-blur z-10 border-b border-base-content/10">
            <tr>
                <th class="w-16"><input type="checkbox" class="checkbox checkbox-primary checkbox-xs rounded"></th>
                <th class="w-24">차수</th>
                <th>검토 신청일</th>
                <th>결과 상태</th>
                <th>비고</th>
            </tr>
        </thead>
        <tbody class="text-xs divide-y divide-base-content/5">
            <?php 
            $has_sec = false;
            while($row = sql_fetch_array($sec_checks)) { 
                $has_sec = true;
            ?>
            <tr class="hover:bg-base-200/40">
                <td><input type="checkbox" name="chk_sec[]" value="<?php echo $row['sec_id']; ?>" class="checkbox checkbox-primary checkbox-xs rounded"></td>
                <td class="font-bold font-mono"><?php echo $row['round_no']; ?>차</td>
                <td class="font-mono"><?php echo $row['check_date']; ?></td>
                <td>
                    <span class="badge <?php echo $row['result_status'] == '승인' ? 'badge-success' : ($row['result_status'] == '보완' ? 'badge-error' : 'badge-info'); ?> badge-xs py-2 px-2.5 rounded-lg">
                        <?php echo $row['result_status']; ?>
                    </span>
                </td>
                <td class="text-left px-4 text-base-content/70"><?php echo htmlspecialchars($row['remarks']); ?></td>
            </tr>
            <?php } if(!$has_sec) { ?>
            <tr><td colspan="5" class="py-16 text-base-content/40 font-bold">등록된 보안성검토 차수가 없습니다.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>