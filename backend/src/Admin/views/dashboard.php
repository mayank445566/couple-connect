<h1>Dashboard</h1>
<?php if ($reports && (int)$reports['underage'] > 0): ?>
  <div class="flash error"><?= (int)$reports['underage'] ?> open report(s) say someone may be under 18. <a href="<?= $url('/admin/reports') ?>">Review them first</a>.</div>
<?php endif ?>
<?php if ($rev && $rev['stuck_webhooks'] > 0): ?>
  <div class="note"><?= (int)$rev['stuck_webhooks'] ?> payment webhook(s) were received but not processed. <a href="<?= $url('/admin/webhooks') ?>">Check them</a>.</div>
<?php endif ?>
<div class="grid">
  <?php foreach ($stats as $label => $n): ?><div class="stat"><b><?= number_format($n) ?></b><span><?= $h($label) ?></span></div><?php endforeach ?>
  <?php if ($reports): ?><div class="stat"><b><?= (int)$reports['open'] ?></b><span><a href="<?= $url('/admin/reports') ?>">Open reports</a></span></div><?php endif ?>
</div>
<?php if ($rev): ?>
<h2>Premium</h2>
<div class="grid">
  <div class="stat"><b><?= $money($rev['revenue']) ?></b><span>Revenue, all time (paid, not refunded)</span></div>
  <div class="stat"><b><?= $money($rev['revenue30']) ?></b><span>Revenue, last 30 days</span></div>
  <div class="stat"><b><?= (int)$rev['paid'] ?></b><span>Paid orders</span></div>
  <div class="stat"><b><?= (int)$rev['refunded'] ?></b><span>Refunded orders</span></div>
  <div class="stat"><b><?= (int)$rev['premium_users'] ?></b><span>Accounts with Premium</span></div>
</div>
<?php endif ?>
<h2>Sign-ups, last 14 days</h2>
<div class="card">
  <?php if (!$signups): ?><p class="muted">No sign-ups yet.</p><?php else: $max = max(array_column($signups, 'n')); ?>
  <table><?php foreach ($signups as $s): ?><tr><td class="mono"><?= $h($s['d']) ?></td><td><?= (int)$s['n'] ?></td><td><svg width="300" height="12" role="img" aria-label="<?= (int)$s['n'] ?> sign-ups"><rect width="<?= round(300 * $s['n'] / max(1, $max)) ?>" height="12" rx="3" fill="currentColor" opacity=".55"/></svg></td></tr><?php endforeach ?></table>
  <?php endif ?>
</div>
<p class="muted">This page shows counts only. Chats and photos are visible only as attachments to a report, or to an owner through an audited case.</p>
