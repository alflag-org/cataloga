<?php
$validation = is_array($change['validation'] ?? null) ? $change['validation'] : ['valid' => false, 'errors' => [], 'warnings' => []];
$operations = is_array($change['operations'] ?? null) ? $change['operations'] : [];
$diffItems = is_array($diff['items'] ?? null) ? $diff['items'] : [];
$status = (string) ($change['status'] ?? 'draft');
$statusLabel = ui_change_status_label($status);
$statusClass = ui_change_status_class($status);
?>
<div class="panel soft">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">変更を確認</p>
      <h2><?= h((string) $change['id']) ?></h2>
      <p class="meta">作成: <?= h((string) ($change['createdAt'] ?? '')) ?> · 更新: <?= h((string) ($change['updatedAt'] ?? '')) ?></p>
    </div>
    <div class="actions">
      <span class="pill <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
    </div>
  </div>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <h3>概要</h3>
    </div>
  </div>
  <ul class="clean">
    <?php if ($operations === []): ?>
      <li>このドラフトに操作はありません。</li>
    <?php else: ?>
      <?php foreach ($operations as $operation): ?>
        <li><?= h((string) ($operation['type'] ?? 'operation')) ?></li>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>
  <div class="mt-2">
    <h3>保存結果</h3>
    <p class="meta">保存時にローカル `registry/` のファイルへ書き込みます。</p>
  </div>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <h3>検証</h3>
      <p class="meta">実行時刻: <?= h((string) ($validation['ranAt'] ?? '未実行')) ?></p>
    </div>
    <div>
      <?php if (!empty($validation['valid'])): ?>
        <span class="pill ok">正常</span>
      <?php else: ?>
        <span class="pill error">検証エラー</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($validation['errors'])): ?>
    <h3>エラー</h3>
    <ul class="clean">
      <?php foreach ($validation['errors'] as $error): ?>
        <li><?= h((string) ($error['message'] ?? 'unknown error')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!empty($validation['warnings'])): ?>
    <h3 class="mt-3">警告</h3>
    <ul class="clean">
      <?php foreach ($validation['warnings'] as $warning): ?>
        <li><?= h((string) ($warning['message'] ?? 'warning')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="actions mt-2">
    <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/validate">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <button type="submit" class="secondary-button">再検証</button>
    </form>
  </div>
</div>

<div class="panel">
  <details>
    <summary><strong>技術差分</strong></summary>
    <?php if ($diffItems === []): ?>
      <p class="empty-state mt-2">ファイル差分はありません。</p>
    <?php else: ?>
      <?php foreach ($diffItems as $item): ?>
        <section class="panel soft mt-2">
          <div class="title-row">
            <div class="title-stack">
              <h3><?= h((string) $item['status']) ?>: <span class="mono"><?= h((string) $item['path']) ?></span></h3>
            </div>
          </div>
          <div class="split">
            <div>
              <p class="meta">変更前</p>
              <pre><?= h((string) ($item['before'] ?? '')) ?></pre>
            </div>
            <div>
              <p class="meta">変更後</p>
              <pre><?= h((string) ($item['after'] ?? '')) ?></pre>
            </div>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </details>
</div>

<div class="panel">
  <div class="actions">
    <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/save" class="form-stack">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <button type="submit" class="primary-button">変更を保存</button>
    </form>

    <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/discard">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <button type="submit" class="danger-button">破棄</button>
    </form>
  </div>
</div>
