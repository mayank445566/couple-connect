<p><a href="<?= $url('/admin/cases') ?>">← Case access</a></p>
<h1>Case #<?= (int)$g['id'] ?>: <?= $h($couple['a_name']) ?> and <?= $h($couple['b_name']) ?></h1>
<div class="note">Open until <?= $date($g['expires_at']) ?>. Basis: <?= $h(str_replace('_', ' ', $g['basis'])) ?> (<?= $h($g['reference']) ?>). This page view has been logged.</div>
<form method="post" action="<?= $url('/admin/cases/' . (int)$g['id'] . '/close') ?>"><?= $csrf ?><p><button class="secondary" type="submit">Close case now</button></p></form>
<div class="card">
  <p class="muted">Newest first, <?= count($rows) ?> message(s) on this page.</p>
  <?php foreach ($rows as $m): $md = $m['media_id'] ? ($media[(int)$m['media_id']] ?? null) : null; ?>
    <div class="msg">
      <div class="meta">#<?= (int)$m['id'] ?> · <?= $h($m['sender']) ?> · <?= $date($m['created_at']) ?> · <?= $h($m['type']) ?><?= $m['edited_at'] ? ' · edited' : '' ?><?= $m['expires_at'] ? ' · disappearing' : '' ?></div>
      <?php if ($m['deleted_at']): ?>
        <em class="muted">Deleted by the sender. Content is gone.</em>
      <?php else: ?>
        <?php if ($m['body'] !== null && $m['body'] !== ''): ?><div><?= nl2br($h($m['body'])) ?></div><?php endif ?>
        <?php if ($m['media_id']): ?>
          <?php if (!$md || $md['deleted_at']): ?><em class="muted">File deleted.</em>
          <?php else: $src = $url('/admin/cases/' . (int)$g['id'] . '/media/' . (int)$md['id']); ?>
            <?php if (str_starts_with($md['mime'], 'image/') && $md['mime'] !== 'image/heic' && $md['mime'] !== 'image/heif'): ?><img class="evidence" src="<?= $src ?>" alt="Image in message <?= (int)$m['id'] ?>" loading="lazy">
            <?php elseif (str_starts_with($md['mime'], 'video/')): ?><video class="evidence" src="<?= $src ?>" controls preload="none"></video>
            <?php elseif (str_starts_with($md['mime'], 'audio/')): ?><audio src="<?= $src ?>" controls preload="none"></audio>
            <?php else: ?><a href="<?= $src ?>">Download <?= $h($md['kind']) ?> (<?= number_format((int)$md['size_bytes'] / 1024) ?> KB)</a><?php endif ?>
          <?php endif ?>
        <?php endif ?>
      <?php endif ?>
    </div>
  <?php endforeach ?>
  <?php if (!$rows): ?><p class="muted">No messages.</p><?php endif ?>
  <?php if ($next): ?><p><a class="btn secondary" href="<?= $url('/admin/cases/' . (int)$g['id'], ['before' => $next]) ?>">Older messages</a></p><?php endif ?>
</div>
