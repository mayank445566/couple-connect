<h1>Pricing</h1>
<div class="note">Price changes apply to new orders only. Google Play prices are set in the Play Console, not here.</div>
<?php foreach ($products as $p): ?>
<div class="card">
  <h2><?= $h($p['title']) ?> <span class="mono muted"><?= $h($p['sku']) ?></span></h2>
  <p class="muted">Grants: <?= $h(implode(', ', json_decode((string)$p['grants'], true) ?: [])) ?> · Sold: <?= (int)$p['sold'] ?></p>
  <form method="post" action="<?= $url('/admin/products/' . (int)$p['id']) ?>"><?= $csrf ?>
    <label for="t<?= (int)$p['id'] ?>">Title</label><input id="t<?= (int)$p['id'] ?>" type="text" name="title" maxlength="100" required value="<?= $h($p['title']) ?>">
    <label for="d<?= (int)$p['id'] ?>">Description</label><textarea id="d<?= (int)$p['id'] ?>" name="description" maxlength="400"><?= $h($p['description'] ?? '') ?></textarea>
    <label for="p<?= (int)$p['id'] ?>">Price in ₹</label><input id="p<?= (int)$p['id'] ?>" type="text" name="price" inputmode="decimal" required value="<?= $h(number_format($p['price_paise'] / 100, 2, '.', '')) ?>">
    <label><input type="checkbox" name="active" value="1"<?= $p['active'] ? ' checked' : '' ?>> On sale</label>
    <p><button type="submit">Save</button></p>
  </form>
</div>
<?php endforeach ?>
