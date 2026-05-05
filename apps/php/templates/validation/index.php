<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">検証</p>
      <h2>リソースと依存関係のチェック</h2>
    </div>
    <div>
      <?php if (!empty($result['valid'])): ?>
        <span class="pill ok">正常</span>
      <?php else: ?>
        <span class="pill error">異常</span>
      <?php endif; ?>
    </div>
  </div>

  <p class="meta">実行時刻: <?= h((string) ($result['ranAt'] ?? '')) ?></p>

  <div class="split mt-3">
    <section class="panel soft">
      <div class="title-row">
        <div class="title-stack">
          <p class="eyebrow">エラー</p>
          <h3>保存をブロックする問題</h3>
        </div>
      </div>
      <?php if (empty($result['errors'])): ?>
        <p class="meta">エラーはありません。</p>
      <?php else: ?>
        <ul class="clean">
          <?php foreach ($result['errors'] as $error): ?>
            <li class="error"><?= h((string) ($error['message'] ?? 'unknown error')) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel soft">
      <div class="title-row">
        <div class="title-stack">
          <p class="eyebrow">警告</p>
          <h3>注意事項</h3>
        </div>
      </div>
      <?php if (empty($result['warnings'])): ?>
        <p class="meta">警告はありません。</p>
      <?php else: ?>
        <ul class="clean">
          <?php foreach ($result['warnings'] as $warning): ?>
            <li><?= h((string) ($warning['message'] ?? 'warning')) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
