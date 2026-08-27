<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);
?>

<!-- DaisyUI 기반 모던 로그인 UI -->
<div class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="card w-full max-w-sm shadow-2xl bg-base-100 transition-all hover:-translate-y-1">
        <div class="card-body">
            <h2 class="card-title text-2xl font-bold justify-center mb-6">항공사진촬영 관리</h2>
            
            <form name="flogin" action="<?php echo $login_action_url ?>" onsubmit="return flogin_submit(this);" method="post">
                <input type="hidden" name="url" value="<?php echo $login_url ?>">
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">아이디</span>
                    </label>
                    <input type="text" name="mb_id" id="login_id" placeholder="아이디를 입력하세요" class="input input-bordered w-full focus:input-primary" required maxLength="20">
                </div>
                
                <div class="form-control mt-4">
                    <label class="label">
                        <span class="label-text">비밀번호</span>
                    </label>
                    <input type="password" name="mb_password" id="login_pw" placeholder="비밀번호를 입력하세요" class="input input-bordered w-full focus:input-primary" required maxLength="20">
                </div>

                <div class="form-control mt-4">
                    <label class="cursor-pointer label justify-start gap-2">
                        <input type="checkbox" name="auto_login" id="login_auto_login" class="checkbox checkbox-primary checkbox-sm">
                        <span class="label-text">자동로그인</span>
                    </label>
                </div>

                <div class="form-control mt-6">
                    <button type="submit" class="btn btn-primary w-full">로그인</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function flogin_submit(f) {
    if (!f.mb_id.value) {
        alert("회원아이디를 입력하십시오.");
        f.mb_id.focus();
        return false;
    }
    if (!f.mb_password.value) {
        alert("비밀번호를 입력하십시오.");
        f.mb_password.focus();
        return false;
    }
    return true;
}
</script>