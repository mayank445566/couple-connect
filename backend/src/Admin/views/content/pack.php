<p><a href="<?= $url('/admin/games/' . (int)$p['game_id']) ?>">← <?= $h($p['game_title']) ?></a></p>
<h1><?= $h($p['title']) ?></h1>
<div class="card">
<form method="post" action="<?= $url('/admin/packs/' . (int)$p['id']) ?>"><?= $csrf ?>
  <div class="row">
    <div><label for="title">Title</label><input id="title" type="text" name="title" maxlength="100" required value="<?= $h($p['title']) ?>"></div>
    <div><label for="level">Level</label><select id="level" name="level"><?php foreach (array_unique(array_merge($levels, [$p['level']])) as $l): ?><option<?= $l === $p['level'] ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach ?></select></div>
    <div><label for="sort">Order</label><input id="sort" type="number" name="sort_order" value="<?= (int)$p['sort_order'] ?>"></div>
  </div>
  <label><input type="checkbox" name="enabled" value="1"<?= $p['enabled'] ? ' checked' : '' ?>> Shown in the app</label>
  <?php if ($p['section'] !== 'adult'): ?><label><input type="checkbox" name="is_premium" value="1"<?= $p['is_premium'] ? ' checked' : '' ?>> Premium only</label><?php endif ?>
  <p><button type="submit">Save pack</button></p>
</form>
</div>
<div class="card">
  <h2>Questions (<?= (int)$total ?>)</h2>
  <table><tr><th>#</th><th>Kind</th><th>Text</th><th>Options</th><th>Shown</th></tr>
  <?php foreach ($questions as $q): ?><tr><td><a href="<?= $url('/admin/questions/' . (int)$q['id']) ?>"><?= (int)$q['id'] ?></a></td><td><?= $h($q['kind']) ?></td><td><?= $h($q['text']) ?></td><td class="mono"><?= $h($q['options'] ?? '') ?></td><td><?= $q['enabled'] ? '<span class="badge ok">on</span>' : '<span class="badge">off</span>' ?></td></tr><?php endforeach ?>
  </table>
  <?= $pager($total, [], '/admin/packs/' . (int)$p['id']) ?>
  <h2>Add a question</h2>
  <?php if ($p['section'] === 'adult'): ?><div class="note">18+ prompts must stay suggestive, romantic and consensual, never explicit, and every card can be skipped.</div><?php endif ?>
  <form method="post" action="<?= $url('/admin/packs/' . (int)$p['id'] . '/questions') ?>"><?= $csrf ?>
    <label for="kind">Kind</label><select id="kind" name="kind"><?php foreach ($kinds as $k): ?><option><?= $h($k) ?></option><?php endforeach ?></select>
    <label for="text">Text</label><textarea id="text" name="text" maxlength="500" required></textarea>
    <label for="options">Options (optional JSON list: ["A","B"], or ["@couple"] to pick one of the two partners)</label><input id="options" type="text" name="options">
    <label for="correct">Correct answer (quiz games only)</label><input id="correct" type="text" name="correct" maxlength="200">
    <p><button type="submit">Add question</button></p>
  </form>
</div>
