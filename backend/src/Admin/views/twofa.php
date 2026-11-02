<div class="login card">
  <h1>Two-step sign in</h1>
  <?php if ($error): ?><div class="flash error" role="alert"><?= $h($error) ?></div><?php endif ?>
  <form method="post" action="<?= $url('/admin/2fa') ?>">
    <input type="hidden" name="_pre" value="<?= $h($pre) ?>">
    <label for="code">6-digit code from your authenticator app</label>
    <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" required autofocus>
    <p><button type="submit">Verify</button></p>
  </form>
  <p class="muted">Lost your phone? Another owner can reset your two-step sign-in from Admins, or run <code>php bin/admin.php reset-2fa you@example.com</code> on the server.</p>
</div>
