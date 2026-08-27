<?php
if (!defined('_GNUBOARD_')) exit;
?>
      </div>
    </main>

    <!-- Sidebar Navigation -->
    <aside class="drawer-side z-40">
      <label for="app-drawer" class="drawer-overlay"></label>
      <ul class="menu p-4 w-64 min-h-full bg-base-100 text-base-content border-r border-base-300 gap-1 font-medium">
        <li class="menu-title text-xs uppercase tracking-wider text-base-content/50">사업 및 비행 관리</li>
        <li><a href="<?php echo G5_URL ?>/project_list.php" class="active:bg-primary"><i class="fa-solid fa-folder-open w-5"></i> 사업 목록 (Projects)</a></li>
        <li><a href="<?php echo G5_URL ?>/flight_plan.php"><i class="fa-solid fa-route w-5"></i> 비행계획 / 코스관리</a></li>
        <li><a href="<?php echo G5_URL ?>/flight_log.php"><i class="fa-solid fa-clipboard-list w-5"></i> 촬영 일지 (Log)</a></li>

        <li class="menu-title text-xs uppercase tracking-wider text-base-content/50 mt-4">데이터 및 성과품</li>
        <li><a href="<?php echo G5_URL ?>/raw_data.php"><i class="fa-solid fa-hard-drive w-5"></i> 원시데이터 (RAW/LiDAR)</a></li>
        <li><a href="<?php echo G5_URL ?>/qa_status.php"><i class="fa-solid fa-square-check w-5"></i> 검수 / AT 성과</a></li>

        <li class="menu-title text-xs uppercase tracking-wider text-base-content/50 mt-4">게시판 및 커뮤니케이션</li>
        <li><a href="<?php echo G5_BBS_URL ?>/board.php?bo_table=notice"><i class="fa-solid fa-bullhorn w-5"></i> 공지사항</a></li>
        <li><a href="<?php echo G5_BBS_URL ?>/board.php?bo_table=free"><i class="fa-solid fa-comments w-5"></i> 업무 공유 게시판</a></li>
      </ul>
    </aside>
  </div>
</div>

<?php
include_once(G5_THEME_PATH.'/tail.sub.php');