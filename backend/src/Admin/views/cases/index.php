<h1>Case access</h1>
<div class="note">Use this only when you suspect illegal activity, or for a user report, a legal request or a safety emergency. You open one couple's chat for <?= \App\Admin\Pages\CasesPage::HOURS ?> hours. Every page and every file you open is written to the audit log, which other owners can read. Deleted and disappearing messages cannot be recovered.</div>

<?php if ($couple): ?>
<div class="card">
  <h2>Open a case for couple #<?= (int)$couple['id'] ?></h2>
  <p><a href="<?= $url('/admin/users/' . (int)$couple['user_a_id']) ?>"><?= $h($couple['a_name']) ?></a> and <a href="<?= $url('/admin/users/' . (int)$couple['user_b_id']) ?>"><?= $h($couple['b_name']) ?></a> · paired <?= $date($couple['paired_at']) ?> · <?= $h($couple['status']) ?></p>
  <?php if (!$has2fa): ?>
    <p>You need two-step sign-in turned on first. <a href="<?= $url('/admin/account') ?>">Set it up</a>.</p>
  <?php else: ?>
  <form method="post" action="<?= $url('/admin/cases') ?>"><?= $csrf ?>
    <input type="hidden" name="couple_id" value="<?= (int)$couple['id'] ?>">
    <label for="basis">Basis</label>
    <select id="basis" name="basis" required>
      <option value="user_report">User report (reference = report number)</option>
      <option value="suspected_illegal">Suspected illegal activity (reference = your internal note or ticket id)</option>
      <option value="legal_request">Legal request (reference = notice or case number)</option>
      <option value="safety_emergency">Safety emergency (reference = ticket or incident id)</option>
    </select>
    <label for="reference">Reference</label><input id="reference" type="text" name="reference" maxlength="100" required value="<?= $h($reportId) ?>">
    <label for="reason">Reason (at least 20 characters, kept permanently)</label><textarea id="reason" name="reason" maxlength="1000" required minlength="20"></textarea>
    <label for="code">Two-step code</label><input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>
    <p><button class="danger" type="submit">Open case access for 24 hours</button></p>
  </form>
  <?php endif ?>
</div>
<?php else: ?>
<p class="muted">To open a case, go to a user's page and choose <b>Open case access</b>, or open it from a report.</p>
<?php endif ?>

<div class="card">
  <h2>All cases</h2>
  <table><tr><th>Case</th><th>Couple</th><th>Owner</th><th>Basis</th><th>Reference</th><th>Reason</th><th>Opened</th><th>State</th></tr>
  <?php foreach ($cases as $c): ?>
    <tr>
      <td><?= $c['active'] && (int)$c['admin_id'] === (int)$ctx->admin['id'] ? '<a href="' . $url('/admin/cases/' . (int)$c['id']) . '">#' . (int)$c['id'] . '</a>' : '#' . (int)$c['id'] ?></td>
      <td>#<?= (int)$c['couple_id'] ?></td><td><?= $h($c['admin_email']) ?></td><td><?= $h(str_replace('_', ' ', $c['basis'])) ?></td><td><?= $h($c['reference']) ?></td>
      <td><?= $h(mb_substr($c['reason'], 0, 120)) ?></td><td><?= $date($c['created_at']) ?></td>
      <td><?= $c['active'] ? '<span class="badge warn">open until ' . $date($c['expires_at']) . '</span>' : '<span class="badge">closed</span>' ?></td>
    </tr>
  <?php endforeach ?>
  <?php if (!$cases): ?><tr><td colspan="8" class="muted">No cases yet.</td></tr><?php endif ?>
  </table>
</div>
