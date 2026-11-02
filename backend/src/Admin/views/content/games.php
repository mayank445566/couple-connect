<h1>Games</h1>
<div class="card"><table>
  <tr><th>Game</th><th>Section</th><th>Mode</th><th>Premium</th><th>Packs</th><th>Live questions</th><th>Plays</th><th>Shown</th></tr>
  <?php foreach ($games as $g): ?>
  <tr>
    <td><a href="<?= $url('/admin/games/' . (int)$g['id']) ?>"><?= $h($g['title']) ?></a><br><span class="mono muted"><?= $h($g['slug']) ?></span></td>
    <td><?= $g['section'] === 'adult' ? '<span class="badge warn">18+</span>' : 'Free' ?></td>
    <td><?= $h($g['mode']) ?></td><td><?= $g['is_premium'] ? 'Yes' : '—' ?></td>
    <td><?= (int)$g['packs'] ?></td><td><?= (int)$g['questions'] ?></td><td><?= (int)$g['plays'] ?></td>
    <td><?= $g['enabled'] ? '<span class="badge ok">on</span>' : '<span class="badge">off</span>' ?></td>
  </tr>
  <?php endforeach ?>
</table></div>
<p class="muted">New game types need app code; here you edit text, order, availability and the question packs. Couples' own custom questions are private and never shown here.</p>
