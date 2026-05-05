<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">技術差分</p>
      <h2>Git 差分（registry）</h2>
      <p class="meta">追跡対象レジストリファイルの未ステージ/ステージ済み差分。</p>
    </div>
  </div>
  <?php if (($diff['ok'] ?? false) === true): ?>
    <pre><?= h((string) ($diff['stdout'] ?? '差分はありません')) ?></pre>
  <?php else: ?>
    <p class="pill error">Git diff の取得に失敗: <?= h((string) ($diff['stderr'] ?? '不明なエラー')) ?></p>
    <pre><?= h((string) ($diff['stdout'] ?? '')) ?></pre>
  <?php endif; ?>
</div>
