<p><a href="<?= $url('/admin/games') ?>">← Games</a></p>
<h1><?= $h($g['title']) ?> <?= $g['section'] === 'adult' ? '<span class="badge warn">18+</span>' : '' ?></h1>
<div class="card">
<form method="post" action="<?= $url('/admin/games/' . (int)$g['id']) ?>"><?= $csrf ?>
  <label for="title">Title</label><input id="title" type="text" name="title" maxlength="100" required value="<?= $h($g['title']) ?>">
  <label for="description">Description</label><textarea id="description" name="description" maxlength="400" required><?= $h($g['description']) ?></textarea>
  <div class="row">
    <div><label for="rounds">Rounds per game</label><input id="rounds" type="number" name="rounds" min="1" max="50" value="<?= (int)$g['rounds'] ?>"></div>
    <div><label for="sort">Order</label><input id="sort" type="number" name="sort_order" value="<?= (int)$g['sort_order'] ?>"></div>
  </div>
  <label><input type="checkbox" name="enabled" value="1"<?= $g['enabled'] ? ' checked' : '' ?>> Shown in the app</label>
  <?php if ($g['section'] === 'adult'): ?><p class="muted">18+ games always need Premium, both partners 18+, and both partners opted in.</p>
  <?php else: ?><label><input type="checkbox" name="is_premium" value="1"<?= $g['is_premium'] ? ' checked' : '' ?>> Premium only</label><?php endif ?>
  <p><button type="submit">Save game</button></p>
</form>
</div>
<div class="card">
  <h2>Question packs</h2>
  <table><tr><th>Pack</th><th>Level</th><th>Premium</th><th>Questions (live / total)</th><th>Shown</th></tr>
  <?php foreach ($packs as $p): ?><tr><td><a href="<?= $url('/admin/packs/' . (int)$p['id']) ?>"><?= $h($p['title']) ?></a></td><td><?= $h($p['level']) ?></td><td><?= $p['is_premium'] ? 'Yes' : '—' ?></td><td><?= (int)$p['live'] ?> / <?= (int)$p['total'] ?></td><td><?= $p['enabled'] ? '<span class="badge ok">on</span>' : '<span class="badge">off</span>' ?></td></tr><?php endforeach ?>
  <?php if (!$packs): ?><tr><td colspan="5" class="muted">No packs.</td></tr><?php endif ?>
  </table>
  <h2>New pack</h2>
  <form method="post" action="<?= $url('/admin/games/' . (int)$g['id'] . '/packs') ?>"><?= $csrf ?>
    <div class="row">
      <div><label for="ptitle">Title</label><input id="ptitle" type="text" name="title" maxlength="100" required></div>
      <div><label for="plevel">Level</label><select id="plevel" name="level"><?php foreach ($levels as $l): ?><option><?= $h($l) ?></option><?php endforeach ?></select></div>
      <?php if ($g['section'] !== 'adult'): ?><div><label><input type="checkbox" name="is_premium" value="1"> Premium</label></div><?php endif ?>
      <div><button type="submit">Create pack</button></div>
    </div>
  </form>
</div>
