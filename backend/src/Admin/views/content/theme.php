<p><a href="<?= $url('/admin/themes') ?>">← Themes</a></p>
<h1><?= $t['id'] ? $h($t['name']) : 'New theme' ?></h1>
<div class="card">
<form method="post" action="<?= $url($t['id'] ? '/admin/themes/' . (int)$t['id'] : '/admin/themes/new') ?>"><?= $csrf ?>
  <label for="name">Name</label><input id="name" type="text" name="name" maxlength="60" required value="<?= $h($t['name']) ?>">
  <?php if (!$t['id']): ?><label for="slug">Slug (permanent id, e.g. cherry-blossom)</label><input id="slug" type="text" name="slug" maxlength="40" pattern="[a-z0-9\-]+"><?php endif ?>
  <label for="tokens">Colours (JSON)</label>
  <textarea id="tokens" name="tokens" rows="12" required><?= $h($t['tokens_pretty']) ?></textarea>
  <p class="muted">Required: primary, background, surface, text, bubble_mine, bubble_theirs (as #hex or rgba()), dark (true/false). Optional: gradient (2–4 colours), blur (0–40).</p>
  <label for="sort">Order</label><input id="sort" type="number" name="sort_order" value="<?= (int)$t['sort_order'] ?>">
  <label><input type="checkbox" name="is_premium" value="1"<?= $t['is_premium'] ? ' checked' : '' ?>> Premium theme</label>
  <label><input type="checkbox" name="enabled" value="1"<?= $t['enabled'] ? ' checked' : '' ?>> Shown in the app</label>
  <p><button type="submit">Save theme</button></p>
</form>
</div>
