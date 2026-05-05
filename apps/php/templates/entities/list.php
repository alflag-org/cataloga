<?php
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'type' => ''];
$q = (string) ($filters['q'] ?? '');
$typeFilter = (string) ($filters['type'] ?? '');
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">リソース</p>
      <h2>登録済みリソースを管理</h2>
    </div>
    <div class="actions">
      <a class="primary-button" href="/resources/new">リソースを作成</a>
    </div>
  </div>

  <form method="get" action="/resources" class="filters">
    <div class="field">
      <label for="q">検索</label>
      <input id="q" type="search" name="q" value="<?= h($q) ?>" placeholder="リソースを検索">
    </div>
    <div class="field">
      <label for="type">タイプ</label>
      <select id="type" name="type">
        <option value="">すべて</option>
        <?php foreach (($types ?? []) as $type): ?>
          <option value="<?= h((string) $type) ?>" <?= (string) $type === $typeFilter ? 'selected' : '' ?>><?= h((string) $type) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button type="submit" class="secondary-button">適用</button>
    </div>
  </form>

  <?php if ($entities === []): ?>
    <div class="empty-state mt-3">
      リソースはまだありません。
      <div class="actions mt-2">
        <a class="primary-button" href="/resources/new">リソースを作成</a>
        <a class="secondary-button" href="/type-packs">タイプパックをインストール</a>
      </div>
    </div>
  <?php else: ?>
    <div class="table-shell mt-3">
      <table>
        <thead>
        <tr><th>名前</th><th>タイプ</th><th>環境</th><th>オーナー</th><th>状態</th><th>更新</th></tr>
        </thead>
        <tbody>
        <?php foreach ($entities as $entity): ?>
          <?php
          $status = (string) ($entity['status'] ?? 'Valid');
          $statusLabel = ui_record_status_label($status);
          $statusClass = ui_record_status_class($status);
          ?>
          <tr>
            <td><a class="text-link" href="/resources/<?= rawurlencode((string) $entity['id']) ?>"><?= h((string) ($entity['name'] !== '' ? $entity['name'] : $entity['id'])) ?></a></td>
            <td><span class="pill"><?= h((string) ($entity['type'] ?? '')) ?></span></td>
            <td><?= h((string) ($entity['environment'] !== '' ? $entity['environment'] : '—')) ?></td>
            <td><?= h((string) ($entity['owner'] !== '' ? $entity['owner'] : '—')) ?></td>
            <td><span class="pill <?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
            <td><?= h((string) ($entity['updated'] ?? '—')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
