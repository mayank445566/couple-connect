<h1>Reports <span class="muted">(<?= number_format($total) ?>)</span></h1>
<p>
  <?php foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $label): ?>
    <a class="btn<?= $status === $k ? '' : ' secondary' ?>" href="<?= $url('/admin/reports', ['status' => $k]) ?>"><?= $label ?></a>
  <?php endforeach ?>
</p>
<div class="card">
<table>
  <tr><th>Report</th><th>Reason</th><th>Reported by</th><th>About</th><th>Attached</th><th>Status</th><th>Received</th></tr>
  <?php foreach ($reports as $r): ?>
  <tr>
    <td><a href="<?= $url('/admin/reports/' . (int)$r['id']) ?>">#<?= (int)$r['id'] ?></a></td>
    <td><span class="badge <?= $r['reason'] === 'underage' ? 'err' : '' ?>"><?= $h($r['reason']) ?></span></td>
    <td><?= $h($r['reporter_name']) ?></td>
    <td><?= $h($r['reported_name'] ?? '—') ?></td>
    <td><?= (int)$r['n_attached'] ?> message(s)</td>
    <td><?= $h($r['status']) ?></td>
    <td><?= $date($r['created_at']) ?></td>
  </tr>
  <?php endforeach ?>
  <?php if (!$reports): ?><tr><td colspan="7" class="muted">Nothing here.</td></tr><?php endif ?>
</table>
<?= $pager($total, ['status' => $status], '/admin/reports') ?>
</div>
