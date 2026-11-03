<?php
require __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $ok = hash_equals($CFG['admin_user'], $_POST['user'] ?? '') && hash_equals($CFG['admin_pass'], $_POST['password'] ?? '');
    if ($ok) { session_regenerate_id(true); $_SESSION['admin'] = true; header('Location: index.php'); exit; }
    flash('Invalid username or password. Please try again.', 'err');
}
$title = 'Sign In · ' . ($CFG['college'] ?? 'Notice Broadcast');
include 'partials/head.php';
?>
<div class="login-wrapper">
  <div class="login-card">
    <div class="login-brand-header">
      <div class="login-logo-box">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 2L11 13"></path>
          <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
        </svg>
      </div>
      <h1 class="login-title"><?= e($CFG['college']) ?></h1>
      <p class="login-subtitle">Notice Broadcast Admin Portal</p>
    </div>

    <form method="post" class="login-form">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <div class="login-field-group">
        <label for="login-user" class="login-label">Username</label>
        <div class="login-input-wrap">
          <span class="login-input-icon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </span>
          <input type="text" name="user" id="login-user" class="login-input" placeholder="Enter your username" autofocus required autocomplete="username">
        </div>
      </div>

      <div class="login-field-group">
        <label for="login-pass" class="login-label">Password</label>
        <div class="login-input-wrap">
          <span class="login-input-icon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </span>
          <input type="password" name="password" id="login-pass" class="login-input" placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" class="login-toggle-pass" id="togglePass" aria-label="Toggle password visibility">
            <svg class="eye-show" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="eye-hide" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
              <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="login-btn-submit">
        <span>Sign In</span>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </form>

    <div class="login-footer-meta">
      <span class="security-badge">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        Authorized Access Only
      </span>
      <span class="wa-badge">WhatsApp Cloud API</span>
    </div>
  </div>
</div>

<script>
const passInput = document.getElementById('login-pass');
const toggleBtn = document.getElementById('togglePass');
if (toggleBtn && passInput) {
  toggleBtn.addEventListener('click', () => {
    const isPassword = passInput.type === 'password';
    passInput.type = isPassword ? 'text' : 'password';
    toggleBtn.querySelector('.eye-show').style.display = isPassword ? 'none' : 'block';
    toggleBtn.querySelector('.eye-hide').style.display = isPassword ? 'block' : 'none';
  });
}
</script>
<?php include 'partials/foot.php'; ?>
