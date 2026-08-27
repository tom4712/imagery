<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
include_once(G5_THEME_PATH.'/head.sub.php');
?>

<div class="min-h-screen bg-base-200 flex flex-col">
  <!-- Top Navbar -->
  <header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-50 px-4">
    <div class="flex-1 gap-2">
      <label for="app-drawer" class="btn btn-square btn-ghost lg:hidden">
        <i class="fa-solid fa-bars text-lg"></i>
      </label>
      <a href="<?php echo G5_URL ?>" class="flex items-center gap-2 font-bold text-lg text-primary tracking-tight">
        <i class="fa-solid fa-plane-departure text-xl"></i>
        <span>항공사진 촬영사업 관리시스템</span>
      </a>
    </div>

    <!-- User Profile & Quick Action -->
    <div class="flex-none gap-3">
      <?php if ($is_member) { ?>
        <div class="dropdown dropdown-end">
          <label tabindex="0" class="btn btn-ghost btn-sm gap-2">
            <div class="avatar placeholder">
              <div class="bg-neutral text-neutral-content rounded-full w-7">
                <span class="text-xs"><?php echo mb_substr($member['mb_name'] ? $member['mb_name'] : $member['mb_nick'], 0, 1, 'utf-8'); ?></span>
              </div>
            </div>
            <span class="text-sm font-medium"><?php echo $member['mb_name'] ? $member['mb_name'] : $member['mb_nick']; ?>님</span>
            <i class="fa-solid fa-chevron-down text-xs opacity-60"></i>
          </label>
          <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-52 border border-base-200">
            <?php if ($is_admin) { ?>
              <li><a href="<?php echo G5_ADMIN_URL ?>"><i class="fa-solid fa-gear"></i> 관리자 모드</a></li>
            <?php } ?>
            <li><a href="<?php echo G5_BBS_URL ?>/member_confirm.php?url=register_form.php"><i class="fa-solid fa-user-pen"></i> 정보수정</a></li>
            <li><a href="<?php echo G5_BBS_URL ?>/logout.php" class="text-error"><i class="fa-solid fa-right-from-bracket"></i> 로그아웃</a></li>
          </ul>
        </div>
      <?php } else { ?>
        <a href="<?php echo G5_BBS_URL ?>/login.php" class="btn btn-sm btn-primary">로그인</a>
      <?php } ?>
    </div>
  </header>

  <!-- Main Drawer Layout -->
  <div class="drawer lg:drawer-open flex-1">
    <input id="app-drawer" type="checkbox" class="drawer-toggle" />
    
    <!-- Page Content Container -->
    <main class="drawer-content flex flex-col p-4 lg:p-6">
      <div class="w-full max-w-7xl mx-auto space-y-6">