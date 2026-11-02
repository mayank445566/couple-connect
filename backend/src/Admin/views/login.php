<div class="login card">
  <h1>Admin sign in</h1>
  <?php if ($error): ?><div class="flash error" role="alert"><?= $h($error) ?></div><?php endif ?>
  <form method="post" action="<?= $url('/admin/login') ?>">
    <input type="hidden" name="_pre" value="<?= $h($pre) ?>">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" autocomplete="username" required value="<?= $h($email) ?>">
    <label for="password">Password</label>
    <input id="password" type="password" name="password" autocomplete="current-password" required>
    <p><button type="submit">Sign in</button></p>
  </form>
  <p class="muted">Admin accounts are separate from app accounts. Lost access? Run <code>php bin/admin.php reset-password you@example.com</code> on the server.</p>
</div>
