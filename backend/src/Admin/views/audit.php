<h1>Audit log <span class="muted">(<?= number_format($total) ?>)</span></h1>
<form class="card row filters" method="get" action="<?= $url('/admin/audit') ?>">
  <div><label for="action">Action starts with</label><input id="action" type="text" name="action" value="<?= $h($action) ?>" placeholder="case_, login, user_"></div>
  <div><label for="admin">Admin email</label><input id="admin" type="text" name="admin" value="<?= $h($admin) ?>"></div>
  <div><label for="type">Target type</label><select id="type" name="type"><option value="">Any</option><?php foreach (['user', 'couple', 'report', 'order', 'admin', 'game', 'pack', 'question', 'theme', 'product', 'settings'] as $t): ?><option<?= $type === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?></select></div>
  <div><button type="submit">Filter</button></div>
</form>
<div class="card"><table>
  <tr><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= $date($r['created_at']) ?></td><td><?= $h($r['admin_email']) ?></td>
    <td><span class="badge <?= str_starts_with($r['action'], 'case_') ? 'warn' : (str_contains($r['action'], 'failed') ? 'err' : '') ?>"><?= $h($r['action']) ?></span></td>
    <td><?= $h($r['target_type'] ?? '') ?><?= $r['target_id'] ? ' #' . (int)$r['target_id'] : '' ?></td>
    <td class="mono"><?= $h(mb_substr((string)$r['details'], 0, 300)) ?></td><td class="mono"><?= $h($r['ip']) ?></td>
  </tr>
  <?php endforeach ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">No entries.</td></tr><?php endif ?>
</table>
<?= $pager($total, ['action' => $action, 'admin' => $admin, 'type' => $type], '/admin/audit') ?>
</div>
