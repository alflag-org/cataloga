<div class="panel soft">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">ダッシュボード</p>
      <h2>カタログ概要</h2>
      <p class="meta">リソース、依存関係、ドラフト変更の現在状態を確認できます。</p>
    </div>
    <div class="actions">
      <a class="primary-button" href="/resources/new">リソースを作成</a>
      <a class="secondary-button" href="/dependencies/new">依存関係を作成</a>
      <a class="secondary-button" href="/changes">変更を確認</a>
    </div>
  </div>

  <div class="metrics">
    <article class="metric-card">
      <span>リソース</span>
      <strong><?= h((string) $resourceCount) ?></strong>
      <p>登録済みのカタログ項目です。</p>
    </article>
    <article class="metric-card">
      <span>依存関係</span>
      <strong><?= h((string) $dependencyCount) ?></strong>
      <p>リソース間の関係です。</p>
    </article>
    <article class="metric-card">
      <span>警告</span>
      <strong><?= h((string) $warningCount) ?></strong>
      <p>警告またはエラーを含む最近のドラフト数です。</p>
    </article>
    <article class="metric-card">
      <span>ドラフト変更</span>
      <strong><?= h((string) $draftCount) ?></strong>
      <p>まだ保存されていない変更です。</p>
    </article>
  </div>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <h3>最近のリソース</h3>
    </div>
    <a class="secondary-button" href="/resources">リソース一覧</a>
  </div>

  <?php if ($recentResources === []): ?>
    <p class="empty-state">リソースがありません。最初のリソースを作成してください。</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
        <tr><th>名前</th><th>タイプ</th><th>環境</th><th>状態</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentResources as $resource): ?>
          <?php
          $record = is_array($resource['record'] ?? null) ? $resource['record'] : [];
          $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
          $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
          $tags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
          $environment = '';
          if (is_array($tags)) {
              foreach ($tags as $tagKey => $tagValue) {
                  if ((string) $tagKey === 'environment') {
                      $environment = is_scalar($tagValue) ? (string) $tagValue : '';
                      break;
                  }
              }
          }
          if ($environment === '') {
              $environment = (string) ($spec['environment'] ?? '—');
          }
          ?>
          <tr>
            <td><a class="text-link" href="/resources/<?= rawurlencode((string) $resource['id']) ?>"><?= h((string) ($resource['name'] !== '' ? $resource['name'] : $resource['id'])) ?></a></td>
            <td><span class="pill"><?= h((string) $resource['type']) ?></span></td>
            <td><?= h($environment !== '' ? $environment : '—') ?></td>
            <td><span class="pill ok">正常</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <h3>最近の変更</h3>
    </div>
    <a class="secondary-button" href="/changes">変更一覧</a>
  </div>

  <?php if ($recentChanges === []): ?>
    <p class="empty-state">ドラフト変更はありません。</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
        <tr><th>概要</th><th>状態</th><th>更新日時</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentChanges as $change): ?>
          <?php
          $status = (string) ($change['status'] ?? 'open');
          $statusLabel = ui_change_status_label($status);
          $statusClass = ui_change_status_class($status);
          $operations = is_array($change['operations'] ?? null) ? $change['operations'] : [];
          ?>
          <tr>
            <td><?= h((count($operations) > 0 ? (string) count($operations) . ' 件の操作' : 'ドラフト変更')) ?></td>
            <td><span class="pill <?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
            <td><?= h((string) ($change['updatedAt'] ?? '')) ?></td>
            <td><a class="text-link" href="/changes/<?= rawurlencode((string) $change['id']) ?>">確認</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <h3>タイプパック</h3>
      <p class="meta">有効なタイプパックが、利用可能なリソース種別と依存関係種別を決定します。</p>
    </div>
    <a class="secondary-button" href="/type-packs">タイプパック管理</a>
  </div>
  <p class="meta"><?= h((string) $typePackCount) ?> 件のタイプパックを検出しました。</p>
</div>
