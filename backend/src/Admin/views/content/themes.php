<h1>Themes</h1>
<p><a class="btn" href="<?= $url('/admin/themes/new') ?>">New theme</a></p>
<div class="card"><table>
  <tr><th>Theme</th><th>Colours</th><th>Premium</th><th>Shown</th><th>Order</th></tr>
  <?php foreach ($themes as $t): $tk = json_decode((string)$t['tokens'], true) ?: []; ?>
  <tr>
    <td><a href="<?= $url('/admin/themes/' . (int)$t['id']) ?>"><?= $h($t['name']) ?></a><br><span class="mono muted"><?= $h($t['slug']) ?></span></td>
    <td><svg width="120" height="20" role="img" aria-label="Theme colours"><?php $x = 0; foreach (['background', 'primary', 'bubble_mine', 'bubble_theirs', 'text'] as $k): ?><rect x="<?= $x ?>" width="22" height="20" rx="3" fill="<?= $h($tk[$k] ?? '#ccc') ?>" stroke="#8884"/><?php $x += 24; endforeach ?></svg></td>
    <td><?= $t['is_premium'] ? 'Yes' : '—' ?></td>
    <td><?= $t['enabled'] ? '<span class="badge ok">on</span>' : '<span class="badge">off</span>' ?></td>
    <td><?= (int)$t['sort_order'] ?></td>
  </tr>
  <?php endforeach ?>
</table></div>
