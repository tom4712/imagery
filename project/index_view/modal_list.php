<?php
if (!defined('_GNUBOARD_')) exit;
?>
<dialog id="modal_index_list" class="modal z-[200]">
    <div class="modal-box bg-base-100 border border-base-content/10 shadow-2xl rounded-2xl max-w-lg p-6 flex flex-col max-h-[80vh]">
        <div class="flex justify-between items-center pb-3 border-b border-base-content/10">
            <h3 class="font-black text-lg text-base-content flex items-center gap-2">
                <span>📑 INDEX 도면 목록</span>
                <span class="badge badge-primary font-mono text-xs"><?php echo count($dwg_files); ?>개</span>
            </h3>
            <button type="button" class="btn btn-sm btn-circle btn-ghost" onclick="modal_index_list.close()">✕</button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar my-4 rounded-xl border border-base-content/10 bg-base-200/30">
            <table class="table table-xs w-full text-center select-none font-mono">
                <thead class="bg-base-200 text-base-content font-bold sticky top-0 z-10 text-[11px]">
                    <tr>
                        <th class="text-left px-3">파일명</th>
                        <th class="w-36">수정일자</th>
                        <th class="w-20">크기</th>
                        <th class="w-20">선택</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-content/5 text-xs">
                    <?php if (!empty($dwg_files)) { 
                        foreach ($dwg_files as $f) { 
                            $is_cur = ($f['filename'] === $active_dwg_file);
                    ?>
                        <tr class="hover:bg-base-200/60 <?php echo $is_cur ? 'bg-primary/10' : ''; ?>">
                            <td class="text-left px-3 font-bold <?php echo $is_cur ? 'text-primary' : ''; ?> truncate max-w-xs" title="<?php echo htmlspecialchars($f['filename']); ?>">
                                <?php echo htmlspecialchars($f['filename']); ?>
                            </td>
                            <td class="text-base-content/60 text-[11px]"><?php echo $f['mtime']; ?></td>
                            <td class="text-base-content/60 text-[11px]"><?php echo $f['size']; ?></td>
                            <td>
                                <?php if ($is_cur) { ?>
                                    <span class="badge badge-primary badge-xs py-1 px-1.5 font-bold font-sans">활성화됨</span>
                                <?php } else { ?>
                                    <button class="btn btn-xs btn-outline btn-primary rounded-lg font-sans font-bold" onclick="selectActiveIndex('<?php echo htmlspecialchars($f['filename'], ENT_QUOTES); ?>')">로드</button>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } } else { ?>
                        <tr><td colspan="4" class="py-10 text-base-content/40 font-bold font-sans">생성된 도면 파일이 없습니다.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="modal-action pt-2 border-t border-base-content/10 justify-end">
            <button type="button" class="btn btn-ghost btn-sm rounded-xl font-bold" onclick="modal_index_list.close()">닫기</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>