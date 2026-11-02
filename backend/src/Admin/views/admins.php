<h1>Admins</h1>
<?php if ($temp): ?>
<div class="flash ok" role="status">Temporary password for <b><?= $h($temp['email']) ?></b>: <code><?= $h($temp['password']) ?></code><br>Copy it now and give it to them privately. It is not shown again. They must change it and set up two-step sign-in when they first sign in.</div>
<?php endif ?>
<div class="card"><table>
  <tr><th>Admin</th><th>Role</th><th>Status</th><th>2-step</th><th>Last sign-in</th><th>Actions</th></tr>
  <?php foreach ($admins as $a): $self = (int)$a['id'] === (int)$ctx->admin['id']; ?>
  <tr>
    <td><?= $h($a['name']) ?><br><span class="muted"><?= $h($a['email']) ?></span></td>
    <td><?php if ($self): ?><?= $h($a['role']) ?> (you)<?php else: ?>
      <form class="inline" method="post" action="<?= $url('/admin/admins/' . (int)$a['id']) ?>"><?= $csrf ?><input type="hidden" name="op" value="role">
        <select name="role" aria-label="Role for <?= $h($a['email']) ?>"><?php foreach ($roles as $r => $label): ?><option value="<?= $h($r) ?>"<?= $r === $a['role'] ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?></select>
        <button class="secondary" type="submit">Change</button></form><?php endif ?></td>
    <td><span class="badge <?= $a['status'] === 'active' ? 'ok' : 'err' ?>"><?= $h($a['status']) ?></span><?= $a['locked_until'] && strtotime($a['locked_until'] . ' UTC') > time() ? ' <span class="badge warn">locked</span>' : '' ?></td>
    <td><?= $a['totp_enabled_at'] ? 'On' : 'Off' ?></td>
    <td><?= $date($a['last_login_at']) ?></td>
    <td><?php if (!$self): ?>
      <?php foreach (($a['status'] === 'active' ? ['disable' => 'Disable'] : ['enable' => 'Enable']) + ['reset_password' => 'Reset password'] + ($a['totp_enabled_at'] ? ['reset_2fa' => 'Reset 2-step'] : []) as $op => $label): ?>
        <form class="inline" method="post" action="<?= $url('/admin/admins/' . (int)$a['id']) ?>"><?= $csrf ?><input type="hidden" name="op" value="<?= $op ?>"><button class="secondary" type="submit"><?= $label ?></button></form>
      <?php endforeach ?>
    <?php endif ?></td>
  </tr>
  <?php endforeach ?>
</table></div>
<div class="card">
  <h2>Add an admin</h2>
  <form method="post" action="<?= $url('/admin/admins') ?>"><?= $csrf ?>
    <div class="row">
      <div><label for="email">Email</label><input id="email" type="email" name="email" required></div>
      <div><label for="name">Name</label><input id="name" type="text" name="name" maxlength="60" required></div>
      <div><label for="role">Role</label><select id="role" name="role"><?php foreach ($roles as $r => $label): ?><option value="<?= $h($r) ?>"<?= $r === 'moderator' ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?></select></div>
      <div><button type="submit">Add admin</button></div>
    </div>
  </form>
  <p class="muted">Only owners can use case access, change settings and prices, manage admins and read the audit log.</p>
</div>
