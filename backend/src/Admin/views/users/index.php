<h1>Users <span class="muted">(<?= number_format($total) ?>)</span></h1>
<form class="card row filters" method="get" action="<?= $url('/admin/users') ?>">
  <div><label for="q">Search</label><input id="q" type="text" name="q" value="<?= $h($q) ?>" placeholder="Email, name, phone or ID"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">Any</option>
    <?php foreach (['active', 'suspended', 'banned', 'deleted'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach ?></select></div>
  <div><button type="submit">Search</button></div>
</form>
<div class="card">
<table>
  <tr><th>ID</th><th>Name</th><th>Email / phone</th><th>Status</th><th>Paired</th><th>Premium</th><th>Joined</th><th>Last seen</th></tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td class="mono"><?= (int)$u['id'] ?></td>
    <td><a href="<?= $url('/admin/users/' . (int)$u['id']) ?>"><?= $h($u['display_name']) ?></a></td>
    <td><?= $h($u['email'] ?? '') ?><?= $u['email'] && $u['phone'] ? '<br>' : '' ?><?= $h($u['phone'] ?? '') ?></td>
    <td><span class="badge <?= $u['status'] === 'active' ? 'ok' : 'err' ?>"><?= $h($u['status']) ?></span></td>
    <td><?= $u['active_couple_id'] ? 'Yes' : '—' ?></td>
    <td><?= $u['premium'] ? 'Yes' : '—' ?></td>
    <td><?= $date($u['created_at']) ?></td>
    <td><?= $date($u['last_seen']) ?></td>
  </tr>
  <?php endforeach ?>
  <?php if (!$users): ?><tr><td colspan="8" class="muted">No users match.</td></tr><?php endif ?>
</table>
<?= $pager($total, ['q' => $q, 'status' => $status], '/admin/users') ?>
</div>
