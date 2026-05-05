<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? 'unknown');
$name = (string) ($metadata['name'] ?? $id);
$dependsOn = is_array($dependsOn ?? null) ? $dependsOn : [];
$usedBy = is_array($usedBy ?? null) ? $usedBy : [];
?>
<div class="panel soft">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">リソース詳細</p>
      <h2><?= h(ucfirst($type)) ?>: <?= h($name) ?></h2>
    </div>
    <div class="actions">
      <a class="primary-button" href="/resources/<?= rawurlencode($id) ?>/edit">リソースを編集</a>
      <a class="secondary-button" href="/dependencies/new">依存関係を作成</a>
    </div>
  </div>

  <div class="metrics">
    <article class="metric-card">
      <span>状態</span>
      <strong>正常</strong>
    </article>
    <article class="metric-card">
      <span>環境</span>
      <strong><?= h((string) ($spec['environment'] ?? '—')) ?></strong>
    </article>
    <article class="metric-card">
      <span>オーナー</span>
      <strong><?= h((string) ($spec['owner'] ?? '—')) ?></strong>
    </article>
    <article class="metric-card">
      <span>更新</span>
      <strong>—</strong>
    </article>
  </div>
</div>

<div class="split">
  <section class="panel">
    <div class="title-row">
      <div class="title-stack">
        <h3>設定</h3>
      </div>
    </div>
    <?php if ($spec === []): ?>
      <p class="meta">設定項目はありません。</p>
    <?php else: ?>
      <div class="table-shell">
        <table>
          <thead><tr><th>項目</th><th>値</th></tr></thead>
          <tbody>
          <?php foreach ($spec as $key => $value): ?>
            <tr>
              <td><?= h((string) $key) ?></td>
              <td><?= h(is_array($value) ? implode(', ', array_map('strval', $value)) : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="title-row">
      <div class="title-stack">
        <h3>依存関係</h3>
      </div>
    </div>

    <p class="eyebrow">依存先</p>
    <?php if ($dependsOn === []): ?>
      <p class="meta">依存先はありません。</p>
    <?php else: ?>
      <ul class="clean">
        <?php foreach ($dependsOn as $dep): ?>
          <li>
            <span class="pill"><?= h((string) ($dep['type'] ?? '')) ?></span>
            <a class="text-link" href="/resources/<?= rawurlencode((string) ($dep['to'] ?? '')) ?>"><?= h((string) ($dep['to'] ?? '')) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <p class="eyebrow mt-3">被依存</p>
    <?php if ($usedBy === []): ?>
      <p class="meta">このリソースを参照する依存関係はありません。</p>
    <?php else: ?>
      <ul class="clean">
        <?php foreach ($usedBy as $dep): ?>
          <li>
            <span class="pill"><?= h((string) ($dep['type'] ?? '')) ?></span>
            <a class="text-link" href="/resources/<?= rawurlencode((string) ($dep['from'] ?? '')) ?>"><?= h((string) ($dep['from'] ?? '')) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<div class="panel">
  <details>
    <summary><strong>詳細設定</strong> · 技術情報を表示</summary>
    <div class="form-stack mt-2">
      <div class="field">
        <label>リソースID</label>
        <input type="text" readonly value="<?= h($id) ?>">
      </div>
      <div class="field">
        <label>ソースファイル</label>
        <input type="text" readonly value="<?= h((string) ($entity['sourcePath'] ?? '')) ?>">
      </div>
      <div class="field">
        <label>生データ</label>
        <pre><?= h(format_json($record)) ?></pre>
      </div>
    </div>
  </details>
</div>
