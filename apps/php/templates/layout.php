<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title ?? 'Cataloga') ?></title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <strong>Cataloga</strong>
      <span>リソースレジストリ</span>
    </div>
    <form class="searchbar" method="get" action="/resources">
      <input type="search" name="q" placeholder="リソースを検索..." value="<?= h((string) ($_GET['q'] ?? '')) ?>">
      <button type="submit" class="secondary-button">検索</button>
    </form>
  </div>
</header>

<div class="page-shell">
  <aside class="sidebar">
    <nav>
      <a href="/" class="<?= ($currentPath ?? '') === '/' ? 'active' : '' ?>">ダッシュボード</a>
      <a href="/resources" class="<?= str_starts_with((string) ($currentPath ?? ''), '/resources') || str_starts_with((string) ($currentPath ?? ''), '/entities') ? 'active' : '' ?>">リソース</a>
      <a href="/dependencies" class="<?= str_starts_with((string) ($currentPath ?? ''), '/dependencies') || str_starts_with((string) ($currentPath ?? ''), '/relations') ? 'active' : '' ?>">依存関係</a>
      <a href="/changes" class="<?= str_starts_with((string) ($currentPath ?? ''), '/changes') ? 'active' : '' ?>">変更</a>
      <a href="/type-packs" class="<?= str_starts_with((string) ($currentPath ?? ''), '/type-packs') || str_starts_with((string) ($currentPath ?? ''), '/domain-packs') ? 'active' : '' ?>">タイプパック</a>
      <a href="/validation" class="<?= ($currentPath ?? '') === '/validation' ? 'active' : '' ?>">検証</a>
      <a href="/settings" class="<?= ($currentPath ?? '') === '/settings' ? 'active' : '' ?>">設定</a>
    </nav>
  </aside>

  <main class="workspace">
    <?= $content ?>
    <div class="footer-status">Cataloga ・ ローカルワークスペース ・ 変更は保存前に確認されます</div>
  </main>
</div>
<script src="/assets/app.js"></script>
</body>
</html>
