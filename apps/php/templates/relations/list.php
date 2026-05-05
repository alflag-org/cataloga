<?php
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'type' => ''];
$q = (string) ($filters['q'] ?? '');
$typeFilter = (string) ($filters['type'] ?? '');
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">依存関係</p>
      <h2>リソース間の関係を管理</h2>
    </div>
    <div class="actions">
      <a class="primary-button" href="/dependencies/new">依存関係を作成</a>
    </div>
  </div>

  <form method="get" action="/dependencies" class="filters">
    <div class="field">
      <label for="q">検索</label>
      <input id="q" type="search" name="q" value="<?= h($q) ?>" placeholder="依存関係を検索">
    </div>
    <div class="field">
      <label for="type">関係タイプ</label>
      <select id="type" name="type">
        <option value="">すべて</option>
        <?php foreach (($relationTypes ?? []) as $relationType): ?>
          <option value="<?= h((string) $relationType) ?>" <?= (string) $relationType === $typeFilter ? 'selected' : '' ?>><?= h((string) $relationType) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button type="submit" class="secondary-button">適用</button>
    </div>
  </form>

  <?php if ($relations === []): ?>
    <div class="empty-state mt-3">
      依存関係はまだありません。
      <div class="actions mt-2">
        <a class="primary-button" href="/dependencies/new">依存関係を作成</a>
      </div>
    </div>
  <?php else: ?>
    <div class="table-shell mt-3">
      <table>
        <thead>
        <tr><th>元リソース</th><th>関係</th><th>先リソース</th><th>状態</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($relations as $relation): ?>
          <?php
          $status = (string) ($relation['status'] ?? 'Valid');
          $statusClass = ui_record_status_class($status);
          $statusLabel = ui_record_status_label($status);
          ?>
          <tr>
            <td><a class="text-link" href="/resources/<?= rawurlencode((string) ($relation['from'] ?? '')) ?>"><?= h((string) ($relation['from'] ?? '')) ?></a></td>
            <td><span class="pill"><?= h((string) ($relation['type'] ?? '')) ?></span></td>
            <td><a class="text-link" href="/resources/<?= rawurlencode((string) ($relation['to'] ?? '')) ?>"><?= h((string) ($relation['to'] ?? '')) ?></a></td>
            <td><span class="pill <?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
            <td><a class="text-link" href="/dependencies/<?= rawurlencode((string) ($relation['id'] ?? '')) ?>/edit">編集</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
