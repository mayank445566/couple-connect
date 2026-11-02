<h1>Orders <span class="muted">(<?= number_format($total) ?>)</span></h1>
<form class="card row filters" method="get" action="<?= $url('/admin/orders') ?>">
  <div><label for="q">Search</label><input id="q" type="text" name="q" value="<?= $h($q) ?>" placeholder="Order ID, user ID, email, order_… or pay_…"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">Any</option>
    <?php foreach (['paid', 'refunded', 'created', 'failed'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach ?></select></div>
  <div><button type="submit">Search</button></div>
  <div><a href="<?= $url('/admin/webhooks') ?>">Webhook log</a></div>
</form>
<div class="card">
<table>
  <tr><th>Order</th><th>User</th><th>Product</th><th>Amount</th><th>Status</th><th>Gateway order</th><th>Created</th><th>Paid</th></tr>
  <?php foreach ($orders as $o): ?>
  <tr>
    <td><a href="<?= $url('/admin/orders/' . (int)$o['id']) ?>">#<?= (int)$o['id'] ?></a></td>
    <td><?= $h($o['display_name']) ?><br><span class="muted"><?= $h($o['email'] ?? '') ?></span></td>
    <td><?= $h($o['title']) ?></td>
    <td><?= $money($o['amount_paise'], $o['currency']) ?></td>
    <td><span class="badge <?= $o['status'] === 'paid' ? 'ok' : ($o['status'] === 'refunded' ? 'warn' : '') ?>"><?= $h($o['status']) ?></span></td>
    <td class="mono"><?= $h($o['gateway_order_id']) ?></td>
    <td><?= $date($o['created_at']) ?></td>
    <td><?= $date($o['paid_at']) ?></td>
  </tr>
  <?php endforeach ?>
  <?php if (!$orders): ?><tr><td colspan="8" class="muted">No orders match.</td></tr><?php endif ?>
</table>
<?= $pager($total, ['q' => $q, 'status' => $status], '/admin/orders') ?>
</div>
