<p><a href="<?= $url('/admin/packs/' . (int)$q['pack_id']) ?>">← <?= $h($q['pack_title']) ?></a></p>
<h1>Question #<?= (int)$q['id'] ?></h1>
<div class="card">
<form method="post" action="<?= $url('/admin/questions/' . (int)$q['id']) ?>"><?= $csrf ?>
  <label for="kind">Kind</label><select id="kind" name="kind"><?php foreach (array_unique(array_merge($kinds, [$q['kind']])) as $k): ?><option<?= $k === $q['kind'] ? ' selected' : '' ?>><?= $h($k) ?></option><?php endforeach ?></select>
  <label for="text">Text</label><textarea id="text" name="text" maxlength="500" required><?= $h($q['text']) ?></textarea>
  <label for="options">Options (JSON list)</label><input id="options" type="text" name="options" value="<?= $h($q['options'] ?? '') ?>">
  <label for="correct">Correct answer</label><input id="correct" type="text" name="correct" maxlength="200" value="<?= $h($q['correct'] ?? '') ?>">
  <label><input type="checkbox" name="enabled" value="1"<?= $q['enabled'] ? ' checked' : '' ?>> Shown in the app</label>
  <p><button type="submit">Save question</button></p>
</form>
</div>
