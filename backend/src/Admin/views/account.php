<h1>My account</h1>
<div class="card">
  <dl class="kv"><dt>Name</dt><dd><?= $h($a['name']) ?></dd><dt>Email</dt><dd><?= $h($a['email']) ?></dd><dt>Role</dt><dd><?= $h($a['role']) ?></dd><dt>Last sign-in</dt><dd><?= $date($a['last_login_at']) ?></dd></dl>
</div>

<div class="card">
  <h2>Two-step sign-in</h2>
  <?php if ($a['totp_enabled_at']): ?>
    <p><span class="badge ok">On</span> since <?= $date($a['totp_enabled_at']) ?>.</p>
    <?php if (!$require2fa): ?>
    <form method="post" action="<?= $url('/admin/account/2fa/disable') ?>"><?= $csrf ?>
      <label for="cur2">Current password</label><input id="cur2" type="password" name="current" required autocomplete="current-password">
      <p><button class="danger" type="submit">Turn off</button></p>
    </form>
    <?php endif ?>
  <?php elseif ($pending): ?>
    <ol>
      <li>Open an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password).</li>
      <li>Choose <b>Enter a setup key</b>, type the key below and pick <b>Time based</b>.</li>
      <li>Enter the 6-digit code the app shows.</li>
    </ol>
    <p>Setup key: <code><?= $h(trim(chunk_split($pending, 4, ' '))) ?></code></p>
    <p class="muted">Some apps can import this link instead: <code><?= $h($uri) ?></code></p>
    <form method="post" action="<?= $url('/admin/account/2fa/confirm') ?>"><?= $csrf ?>
      <label for="code">Code</label><input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>
      <p><button type="submit">Turn on</button></p>
    </form>
  <?php else: ?>
    <p><span class="badge warn">Off</span> <?= $require2fa ? 'Required before you can use the panel.' : 'Strongly recommended.' ?></p>
    <form method="post" action="<?= $url('/admin/account/2fa/start') ?>"><?= $csrf ?><button type="submit">Set up two-step sign-in</button></form>
  <?php endif ?>
</div>

<div class="card">
  <h2>Change password</h2>
  <?php if ((int)$a['must_change_password'] === 1): ?><div class="note">You are using a temporary password. Choose your own to continue.</div><?php endif ?>
  <form method="post" action="<?= $url('/admin/account/password') ?>"><?= $csrf ?>
    <label for="cur">Current password</label><input id="cur" type="password" name="current" required autocomplete="current-password">
    <label for="new">New password (12+ characters, a letter and a number)</label><input id="new" type="password" name="new" minlength="12" required autocomplete="new-password">
    <label for="confirm">New password again</label><input id="confirm" type="password" name="confirm" minlength="12" required autocomplete="new-password">
    <p><button type="submit">Change password</button></p>
  </form>
</div>

<div class="card">
  <h2>Signed-in sessions</h2>
  <table><tr><th>Started</th><th>Last active</th><th>IP</th><th>Browser</th><th></th></tr>
  <?php foreach ($sessions as $s): ?>
    <tr><td><?= $date($s['created_at']) ?></td><td><?= $date($s['last_seen_at']) ?></td><td class="mono"><?= $h($s['ip']) ?></td><td class="muted"><?= $h(mb_substr((string)$s['user_agent'], 0, 80)) ?></td>
    <td><?php if ((int)$s['id'] === (int)$ctx->session['id']): ?><span class="badge ok">This one</span><?php else: ?>
      <form class="inline" method="post" action="<?= $url('/admin/account/sessions/' . (int)$s['id'] . '/revoke') ?>"><?= $csrf ?><button class="secondary" type="submit">Sign out</button></form><?php endif ?></td></tr>
  <?php endforeach ?>
  </table>
</div>
