<p><a href="<?= $url('/admin/orders') ?>">← Orders</a></p>
<h1>Webhook log</h1>
<p class="muted">Every webhook Razorpay sent, newest first. Duplicates are stored once and ignored. "Not processed" after a few minutes means the handler failed; check <code>storage/logs/php-error.log</code>.</p>
<div class="card"><table><tr><th>#</th><th>Event id</th><th>Type</th><th>Received</th><th>Processed</th></tr>
<?php foreach ($events as $e): ?><tr><td><?= (int)$e['id'] ?></td><td class="mono"><?= $h($e['event_id']) ?></td><td><?= $h($e['type']) ?></td><td><?= $date($e['created_at']) ?></td><td><?= $e['processed_at'] ? $date($e['processed_at']) : '<span class="badge err">not processed</span>' ?></td></tr><?php endforeach ?>
<?php if (!$events): ?><tr><td colspan="5" class="muted">No webhooks received yet.</td></tr><?php endif ?>
</table></div>
