<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $h($title) ?> · Couple Connect Admin</title>
<style nonce="<?= $h($nonce) ?>">
:root{--bg:#f6f5f7;--card:#fff;--text:#231a20;--muted:#6d6168;--line:#e6e0e4;--accent:#c2375c;--accent-weak:#fbe8ee;--ok:#1d7a46;--ok-weak:#e3f4ea;--err:#b3261e;--err-weak:#fde7e5;--warn:#8a5a00;--warn-weak:#fff3d6}
@media (prefers-color-scheme:dark){:root{--bg:#141114;--card:#1e1a1e;--text:#f1ecef;--muted:#a99da4;--line:#342d33;--accent:#ff6b8f;--accent-weak:#3a1f28;--ok:#6fd39b;--ok-weak:#16301f;--err:#ff8a80;--err-weak:#3b1a18;--warn:#f0c060;--warn-weak:#3a2e10}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:var(--accent)}header{background:var(--card);border-bottom:1px solid var(--line);padding:10px 20px;display:flex;flex-wrap:wrap;gap:6px 18px;align-items:center}
header strong{margin-right:10px}header nav{display:flex;flex-wrap:wrap;gap:4px 14px;flex:1}header nav a{text-decoration:none;color:var(--text)}header nav a:hover{color:var(--accent)}
.who{color:var(--muted);font-size:13px;display:flex;gap:10px;align-items:center}
main{max-width:1150px;margin:0 auto;padding:20px 16px 60px}h1{font-size:22px;margin:4px 0 16px}h2{font-size:17px;margin:22px 0 10px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:16px;overflow-x:auto}
table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:7px 8px;border-bottom:1px solid var(--line);vertical-align:top}th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px}.stat{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px}.stat b{display:block;font-size:24px}.stat span{color:var(--muted);font-size:13px}
form.inline{display:inline}label{display:block;margin:10px 0 4px;font-weight:600;font-size:14px}
input[type=text],input[type=email],input[type=password],input[type=number],select,textarea{width:100%;max-width:520px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--text);font:inherit}
textarea{max-width:100%;min-height:90px;font-family:ui-monospace,monospace;font-size:13px}
button,.btn{display:inline-block;padding:8px 14px;border-radius:8px;border:1px solid var(--accent);background:var(--accent);color:#fff;font:inherit;cursor:pointer;text-decoration:none}
button.secondary,.btn.secondary{background:transparent;color:var(--accent)}button.danger{background:var(--err);border-color:var(--err)}
.flash{padding:10px 14px;border-radius:8px;margin-bottom:16px}.flash.ok{background:var(--ok-weak);color:var(--ok)}.flash.error{background:var(--err-weak);color:var(--err)}
.badge{display:inline-block;padding:1px 8px;border-radius:99px;font-size:12px;background:var(--accent-weak);color:var(--accent)}.badge.ok{background:var(--ok-weak);color:var(--ok)}.badge.err{background:var(--err-weak);color:var(--err)}.badge.warn{background:var(--warn-weak);color:var(--warn)}
.muted{color:var(--muted)}.note{background:var(--warn-weak);color:var(--warn);padding:10px 14px;border-radius:8px;margin:10px 0}
.row{display:flex;flex-wrap:wrap;gap:10px;align-items:end}.row>*{flex:0 1 auto}.filters input,.filters select{max-width:240px}
.pager{display:flex;gap:16px;margin-top:12px;align-items:center}
.msg{border:1px solid var(--line);border-radius:8px;padding:8px 10px;margin:6px 0}.msg .meta{font-size:12px;color:var(--muted)}
code,.mono{font-family:ui-monospace,monospace;font-size:13px}.login{max-width:380px;margin:60px auto}
img.evidence,video.evidence{max-width:320px;max-height:320px;border-radius:8px;display:block;margin-top:6px}
dl.kv{display:grid;grid-template-columns:max-content 1fr;gap:4px 16px;margin:0}dl.kv dt{color:var(--muted)}dl.kv dd{margin:0}
@media (max-width:700px){header strong{width:100%}header nav{flex-basis:100%}}
</style>
</head>
<body>
<?php if ($ctx && $ctx->admin): ?>
<header>
  <strong>Couple Connect Admin</strong>
  <nav>
    <a href="<?= $url('/admin') ?>">Dashboard</a>
    <?php if ($can('users.view')): ?><a href="<?= $url('/admin/users') ?>">Users</a><?php endif ?>
    <?php if ($can('reports')): ?><a href="<?= $url('/admin/reports') ?>">Reports</a><?php endif ?>
    <?php if ($can('payments')): ?><a href="<?= $url('/admin/orders') ?>">Orders</a><?php endif ?>
    <?php if ($can('content')): ?><a href="<?= $url('/admin/games') ?>">Games</a><a href="<?= $url('/admin/themes') ?>">Themes</a><?php endif ?>
    <?php if ($can('products')): ?><a href="<?= $url('/admin/products') ?>">Pricing</a><?php endif ?>
    <?php if ($can('settings')): ?><a href="<?= $url('/admin/settings') ?>">Settings</a><?php endif ?>
    <?php if ($can('admins')): ?><a href="<?= $url('/admin/admins') ?>">Admins</a><?php endif ?>
    <?php if ($can('case_access')): ?><a href="<?= $url('/admin/cases') ?>">Case access</a><?php endif ?>
    <?php if ($can('audit')): ?><a href="<?= $url('/admin/audit') ?>">Audit log</a><?php endif ?>
  </nav>
  <div class="who">
    <a href="<?= $url('/admin/account') ?>"><?= $h($ctx->admin['name']) ?></a>
    <span class="badge"><?= $h($ctx->admin['role']) ?></span>
    <form class="inline" method="post" action="<?= $url('/admin/logout') ?>"><?= $csrf ?><button class="secondary" type="submit">Sign out</button></form>
  </div>
</header>
<?php endif ?>
<main>
<?php if ($flash): ?><div class="flash <?= $flash['kind'] === 'error' ? 'error' : 'ok' ?>" role="status"><?= $h($flash['msg']) ?></div><?php endif ?>
<?= $body ?>
</main>
</body>
</html>
