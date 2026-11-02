<p><a href="<?= $url('/admin/orders') ?>">← Orders</a></p>
<h1>Order #<?= (int)$o['id'] ?> <span class="badge <?= $o['status'] === 'paid' ? 'ok' : ($o['status'] === 'refunded' ? 'warn' : '') ?>"><?= $h($o['status']) ?></span></h1>
<div class="card">
  <dl class="kv">
    <dt>User</dt><dd><?php if ($can('users.view')): ?><a href="<?= $url('/admin/users/' . (int)$o['user_id']) ?>"><?= $h($o['display_name']) ?></a><?php else: ?><?= $h($o['display_name']) ?><?php endif ?> <span class="muted"><?= $h($o['email'] ?? '') ?></span></dd>
    <dt>Product</dt><dd><?= $h($o['title']) ?> <span class="mono muted"><?= $h($o['sku']) ?></span></dd>
    <dt>Amount</dt><dd><?= $money($o['amount_paise'], $o['currency']) ?></dd>
    <dt>Gateway</dt><dd><?= $h($o['gateway']) ?> · <span class="mono"><?= $h($o['gateway_order_id']) ?></span></dd>
    <dt>Created</dt><dd><?= $date($o['created_at']) ?></dd>
    <dt>Paid</dt><dd><?= $date($o['paid_at']) ?></dd>
    <?php if ($o['status'] === 'refunded'): ?><dt>Refunded</dt><dd><?= $date($o['refunded_at']) ?><?= $o['refund_gateway_id'] ? ' · <span class="mono">' . $h($o['refund_gateway_id']) . '</span>' : '' ?><?= $refunder ? ' by ' . $h($refunder) : ' (from Razorpay dashboard / webhook)' ?></dd><?php endif ?>
  </dl>
</div>
<div class="card"><h2>Payments</h2>
  <table><tr><th>Payment</th><th>Amount</th><th>Status</th><th>Recorded from</th><th>When</th></tr>
  <?php foreach ($payments as $p): ?><tr><td class="mono"><?= $h($p['gateway_payment_id']) ?></td><td><?= $money($p['amount_paise'], $o['currency']) ?></td><td><?= $h($p['status']) ?></td><td><?= $h($p['source']) ?></td><td><?= $date($p['created_at']) ?></td></tr><?php endforeach ?>
  <?php if (!$payments): ?><tr><td colspan="5" class="muted">No payment yet.</td></tr><?php endif ?>
  </table>
</div>
<div class="card"><h2>Webhook events</h2>
  <table><tr><th>Event</th><th>Type</th><th>Received</th><th>Processed</th></tr>
  <?php foreach ($events as $e): ?><tr><td class="mono"><?= $h($e['event_id']) ?></td><td><?= $h($e['type']) ?></td><td><?= $date($e['created_at']) ?></td><td><?= $e['processed_at'] ? $date($e['processed_at']) : '<span class="badge err">not processed</span>' ?></td></tr><?php endforeach ?>
  <?php if (!$events): ?><tr><td colspan="4" class="muted">None.</td></tr><?php endif ?>
  </table>
</div>
<?php if ($o['status'] === 'paid'): ?>
<div class="card"><h2>Refund</h2>
  <p>Refunds the full <?= $money($o['amount_paise'], $o['currency']) ?> through Razorpay and removes the Premium this order gave (for the buyer and their partner).</p>
  <form method="post" action="<?= $url('/admin/orders/' . (int)$o['id'] . '/refund') ?>"><?= $csrf ?>
    <label for="reason">Reason</label><input id="reason" type="text" name="reason" maxlength="255" required>
    <label><input type="checkbox" name="confirm" value="1" required> I understand this sends money back and cannot be undone.</label>
    <p><button class="danger" type="submit">Refund <?= $money($o['amount_paise'], $o['currency']) ?></button></p>
  </form>
</div>
<?php endif ?>
