<p><a href="<?= $url('/admin/users') ?>">← Users</a></p>
<h1><?= $h($u['display_name']) ?> <span class="badge <?= $u['status'] === 'active' ? 'ok' : 'err' ?>"><?= $h($u['status']) ?></span></h1>
<div class="card">
  <dl class="kv">
    <dt>User ID</dt><dd class="mono"><?= (int)$u['id'] ?></dd>
    <dt>Email</dt><dd><?= $h($u['email'] ?? '—') ?> <?= $u['email_verified_at'] ? '<span class="badge ok">verified</span>' : ($u['email'] ? '<span class="badge warn">not verified</span>' : '') ?></dd>
    <dt>Phone</dt><dd><?= $h($u['phone'] ?? '—') ?> <?= $u['phone_verified_at'] ? '<span class="badge ok">verified</span>' : '' ?></dd>
    <dt>Age</dt><dd><?= $age === null ? '—' : (int)$age ?><?= $age !== null && $age < 18 ? ' <span class="badge err">under 18</span>' : '' ?></dd>
    <dt>Joined</dt><dd><?= $date($u['created_at']) ?></dd>
    <dt>Last active</dt><dd><?= $date($lastSeen) ?></dd>
    <dt>18+ games</dt><dd><?= $u['adult_opt_in_at'] ? 'Turned on' : 'Off' ?></dd>
    <?php if ($u['status_reason']): ?><dt>Status reason</dt><dd><?= $h($u['status_reason']) ?></dd><?php endif ?>
    <?php if ($u['deletion_requested_at']): ?><dt>Deletion</dt><dd>Requested <?= $date($u['deletion_requested_at']) ?> (removed after 7 days)</dd><?php endif ?>
    <dt>Partner</dt><dd><?php if ($couple): ?><a href="<?= $url('/admin/users/' . (int)$couple['partner_id']) ?>"><?= $h($couple['partner_name']) ?></a> since <?= $date($couple['paired_at']) ?><?php else: ?>Not paired<?php endif ?><?= $pastCouples ? ' · ' . $pastCouples . ' past pairing(s)' : '' ?></dd>
  </dl>
  <p class="muted">Chats, photos, notes and game answers are private to the couple and are not shown here.</p>
  <?php if ($can('case_access') && $u['active_couple_id']): ?><p><a class="btn secondary" href="<?= $url('/admin/cases', ['couple' => (int)$u['active_couple_id']]) ?>">Open case access for this couple…</a></p><?php endif ?>
</div>

<?php if ($can('users.moderate') && $u['status'] !== 'deleted'): ?>
<div class="card">
  <h2>Moderation</h2>
  <form method="post" action="<?= $url('/admin/users/' . (int)$u['id'] . '/status') ?>"><?= $csrf ?>
    <div class="row">
      <div><label for="action">Action</label><select id="action" name="action">
        <?php if ($u['status'] !== 'active'): ?><option value="reactivate">Reactivate</option><?php endif ?>
        <?php if ($u['status'] !== 'suspended'): ?><option value="suspend">Suspend (can be undone)</option><?php endif ?>
        <?php if ($u['status'] !== 'banned'): ?><option value="ban">Ban</option><?php endif ?>
      </select></div>
      <div><label for="reason">Reason (kept in audit log)</label><input id="reason" type="text" name="reason" maxlength="255" required></div>
      <div><button class="danger" type="submit">Apply</button></div>
    </div>
    <p class="muted">Suspending or banning signs the person out of every device straight away.</p>
  </form>
  <h2>Devices (<?= count($devices) ?>)</h2>
  <table><tr><th>Device</th><th>Platform</th><th>Signed in</th><th>Last used</th></tr>
    <?php foreach ($devices as $d): ?><tr><td><?= $h($d['device_name'] ?? '—') ?></td><td><?= $h($d['platform'] ?? '—') ?></td><td><?= $date($d['created_at']) ?></td><td><?= $date($d['last_used_at']) ?></td></tr><?php endforeach ?>
  </table>
  <?php if ($devices): ?><form method="post" action="<?= $url('/admin/users/' . (int)$u['id'] . '/sessions/revoke') ?>"><?= $csrf ?><p><button class="secondary" type="submit">Sign out of all devices</button></p></form><?php endif ?>
</div>
<?php endif ?>

<div class="card">
  <h2>Premium</h2>
  <p>Effective access: Premium <?= $effective['premium']['owned'] ? '<span class="badge ok">yes</span>' . ($effective['premium']['via_partner'] ? ' (via partner)' : '') : '<span class="badge">no</span>' ?>
    · Themes <?= $effective['themes']['owned'] ? '<span class="badge ok">yes</span>' . ($effective['themes']['via_partner'] ? ' (via partner)' : '') : '<span class="badge">no</span>' ?></p>
  <table><tr><th>Entitlement</th><th>From</th><th>Granted</th><th>Revoked</th></tr>
    <?php foreach ($ents as $e): ?><tr><td><?= $h($e['entitlement']) ?></td><td><?= $e['order_id'] ? ($can('payments') ? '<a href="' . $url('/admin/orders/' . (int)$e['order_id']) . '">Order #' . (int)$e['order_id'] . '</a>' : 'Order #' . (int)$e['order_id']) : 'Admin grant' ?></td><td><?= $date($e['granted_at']) ?></td><td><?= $date($e['revoked_at']) ?></td></tr><?php endforeach ?>
    <?php if (!$ents): ?><tr><td colspan="4" class="muted">None.</td></tr><?php endif ?>
  </table>
  <?php if ($can('entitlements')): ?>
  <form method="post" action="<?= $url('/admin/users/' . (int)$u['id'] . '/entitlements') ?>"><?= $csrf ?>
    <div class="row">
      <div><label for="op">Change</label><select id="op" name="op"><option value="grant">Grant</option><option value="revoke">Revoke</option></select></div>
      <div><label for="ent">Entitlement</label><select id="ent" name="entitlement"><option value="premium">Premium</option><option value="themes">Themes</option></select></div>
      <div><label for="er">Reason</label><input id="er" type="text" name="reason" maxlength="255" required></div>
      <div><button type="submit">Save</button></div>
    </div>
  </form>
  <?php endif ?>
</div>

<?php if ($orders): ?>
<div class="card"><h2>Orders</h2>
  <table><tr><th>Order</th><th>Product</th><th>Amount</th><th>Status</th><th>Created</th></tr>
  <?php foreach ($orders as $o): ?><tr><td><a href="<?= $url('/admin/orders/' . (int)$o['id']) ?>">#<?= (int)$o['id'] ?></a></td><td><?= $h($o['title']) ?></td><td><?= $money($o['amount_paise'], $o['currency']) ?></td><td><?= $h($o['status']) ?></td><td><?= $date($o['created_at']) ?></td></tr><?php endforeach ?>
  </table></div>
<?php endif ?>

<?php if ($reports): ?>
<div class="card"><h2>Reports</h2>
  <table><tr><th>Report</th><th>Role</th><th>Reason</th><th>Status</th><th>Created</th></tr>
  <?php foreach ($reports as $r): ?><tr><td><a href="<?= $url('/admin/reports/' . (int)$r['id']) ?>">#<?= (int)$r['id'] ?></a></td><td><?= (int)$r['reporter_id'] === (int)$u['id'] ? 'Filed by this user' : 'Against this user' ?></td><td><?= $h($r['reason']) ?></td><td><?= $h($r['status']) ?></td><td><?= $date($r['created_at']) ?></td></tr><?php endforeach ?>
  </table></div>
<?php endif ?>

<?php if ($history): ?>
<div class="card"><h2>Admin history</h2>
  <table><tr><th>When</th><th>Admin</th><th>Action</th><th>Details</th></tr>
  <?php foreach ($history as $a): ?><tr><td><?= $date($a['created_at']) ?></td><td><?= $h($a['admin_email']) ?></td><td><?= $h($a['action']) ?></td><td class="mono"><?= $h($a['details']) ?></td></tr><?php endforeach ?>
  </table></div>
<?php endif ?>
