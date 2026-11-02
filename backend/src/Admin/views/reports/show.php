<p><a href="<?= $url('/admin/reports') ?>">← Reports</a></p>
<h1>Report #<?= (int)$r['id'] ?> <span class="badge <?= $r['reason'] === 'underage' ? 'err' : '' ?>"><?= $h($r['reason']) ?></span> <span class="badge"><?= $h($r['status']) ?></span></h1>
<?php if ($r['reason'] === 'underage'): ?><div class="flash error">Possible under-18 user. The app is adults-only: if confirmed, ban the account.</div><?php endif ?>
<div class="card">
  <dl class="kv">
    <dt>Received</dt><dd><?= $date($r['created_at']) ?></dd>
    <dt>Reported by</dt><dd><a href="<?= $url('/admin/users/' . (int)$r['reporter_id']) ?>"><?= $h($names[(int)$r['reporter_id']]['display_name'] ?? 'User #' . $r['reporter_id']) ?></a></dd>
    <dt>About</dt><dd><?php if ($r['reported_id']): ?><a href="<?= $url('/admin/users/' . (int)$r['reported_id']) ?>"><?= $h($names[(int)$r['reported_id']]['display_name'] ?? 'User #' . $r['reported_id']) ?></a> (<?= $h($names[(int)$r['reported_id']]['status'] ?? '') ?>)<?= $prior ? ' · ' . $prior . ' other report(s) about this person' : '' ?><?php else: ?>—<?php endif ?></dd>
    <dt>Details</dt><dd><?= nl2br($h($r['details'] ?? '—')) ?></dd>
    <?php if ($handler): ?><dt>Handled by</dt><dd><?= $h($handler) ?>, <?= $date($r['handled_at']) ?></dd><?php endif ?>
    <?php if ($r['resolution_note']): ?><dt>Decision</dt><dd><?= $h($r['action_taken']) ?> — <?= nl2br($h($r['resolution_note'])) ?></dd><?php endif ?>
  </dl>
</div>

<div class="card">
  <h2>Messages the reporter attached (<?= count($attached) ?>)</h2>
  <p class="muted">Only these messages are visible to moderators, as they were when reported.</p>
  <?php foreach ($attached as $m): $md = isset($m['media_id']) && $m['media_id'] ? ($media[(int)$m['media_id']] ?? null) : null; ?>
    <div class="msg">
      <div class="meta">From <?= $h($names[(int)$m['sender_id']]['display_name'] ?? 'User #' . (int)$m['sender_id']) ?> · <?= $date($m['created_at'] ?? null) ?> · <?= $h($m['type'] ?? 'text') ?></div>
      <?php if (!empty($m['body'])): ?><div><?= nl2br($h($m['body'])) ?></div><?php endif ?>
      <?php if (!empty($m['media_id'])): ?>
        <?php if (!$md || $md['deleted_at']): ?><em class="muted">File deleted by the sender.</em>
        <?php else: $src = $url('/admin/reports/' . (int)$r['id'] . '/media/' . (int)$md['id']); ?>
          <?php if (str_starts_with($md['mime'], 'image/')): ?><img class="evidence" src="<?= $src ?>" alt="Attached image" loading="lazy">
          <?php elseif (str_starts_with($md['mime'], 'video/')): ?><video class="evidence" src="<?= $src ?>" controls preload="none"></video>
          <?php elseif (str_starts_with($md['mime'], 'audio/')): ?><audio src="<?= $src ?>" controls preload="none"></audio>
          <?php else: ?><a href="<?= $src ?>">Download attached <?= $h($md['kind']) ?></a><?php endif ?>
        <?php endif ?>
      <?php endif ?>
    </div>
  <?php endforeach ?>
  <?php if (!$attached): ?><p class="muted">No messages were attached.</p><?php endif ?>
  <?php if ($can('case_access') && $r['couple_id']): ?><p><a class="btn secondary" href="<?= $url('/admin/cases', ['couple' => (int)$r['couple_id'], 'report' => (int)$r['id']]) ?>">Need more context? Open case access…</a></p><?php endif ?>
</div>

<div class="card">
  <h2>Decision</h2>
  <form method="post" action="<?= $url('/admin/reports/' . (int)$r['id']) ?>"><?= $csrf ?>
    <div class="row">
      <div><label for="status">Outcome</label><select id="status" name="status">
        <option value="resolved">Resolved</option><option value="dismissed">Dismissed (no violation)</option><option value="reviewing">Keep reviewing</option></select></div>
      <?php if ($r['reported_id'] && $can('users.moderate')): ?>
      <div><label for="action">Action on reported account</label><select id="action" name="action">
        <option value="none">No action</option><option value="suspended">Suspend</option><option value="banned">Ban</option></select></div>
      <?php endif ?>
    </div>
    <label for="note">Note (kept in the audit log)</label><textarea id="note" name="note" maxlength="1000"></textarea>
    <p><button type="submit">Save decision</button></p>
  </form>
</div>
