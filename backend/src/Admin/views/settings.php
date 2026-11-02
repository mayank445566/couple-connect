<h1>Settings</h1>
<div class="card">
<form method="post" action="<?= $url('/admin/settings') ?>"><?= $csrf ?>
  <?php foreach ($toggles as $k => [$label, $hint]): ?>
    <label><input type="checkbox" name="<?= $h($k) ?>" value="1"<?= $values[$k] ? ' checked' : '' ?>> <?= $h($label) ?></label>
    <p class="muted"><?= $h($hint) ?></p>
  <?php endforeach ?>
  <label for="daily">Daily question pack</label>
  <select id="daily" name="daily_question_pack"><?php foreach ($packs as $p): ?><option value="<?= $h($p['slug']) ?>"<?= $p['slug'] === $dailyPack ? ' selected' : '' ?>><?= $h($p['game'] . ' · ' . $p['title']) ?></option><?php endforeach ?></select>
  <p><button type="submit">Save settings</button></p>
</form>
</div>
<div class="card">
  <h2>Server health</h2>
  <p class="muted">Read from the server's .env. Secret values are never shown. Change them in the .env file.</p>
  <table><?php foreach ($health as [$label, $ok, $detail]): ?><tr><td><?= $h($label) ?></td><td><?= $ok ? '<span class="badge ok">OK</span>' : '<span class="badge warn">Check</span>' ?></td><td class="muted"><?= $h($detail) ?></td></tr><?php endforeach ?></table>
</div>
