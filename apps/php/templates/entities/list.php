<?php
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'type' => ''];
$q = (string) ($filters['q'] ?? '');
$typeFilter = (string) ($filters['type'] ?? '');
$environmentFilter = (string) ($filters['environment'] ?? '');
$ownerFilter = (string) ($filters['owner'] ?? '');
$siteFilter = (string) ($filters['site'] ?? '');
$zoneFilter = (string) ($filters['zone'] ?? '');
$lifecycleFilter = (string) ($filters['lifecycle'] ?? '');
$tagFilterOptions = is_array($tagFilterOptions ?? null) ? $tagFilterOptions : [];
$listColumns = is_array($listColumns ?? null) ? $listColumns : [];

$resolveCell = static function (array $entity, string $path): string {
    $segments = array_values(array_filter(explode('.', trim($path)), static fn (string $v): bool => $v !== ''));
    if ($segments === []) {
        return '—';
    }
    $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
    $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
    $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
    $computed = is_array($entity['computed'] ?? null) ? $entity['computed'] : [];
    $cursor = null;
    if ($segments[0] === 'metadata') {
        $cursor = $metadata;
    } elseif ($segments[0] === 'spec') {
        $cursor = $spec;
    } elseif ($segments[0] === 'computed') {
        $cursor = $computed;
    } else {
        return '—';
    }
    array_shift($segments);
    foreach ($segments as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return '—';
        }
        $cursor = $cursor[$segment];
    }

    if (is_scalar($cursor) || $cursor === null) {
        $value = trim((string) ($cursor ?? ''));
        if ($value === '' && $path === 'metadata.name') {
            $fallbackName = trim((string) ($entity['name'] ?? ''));
            if ($fallbackName !== '') {
                return $fallbackName;
            }
            return trim((string) ($entity['id'] ?? '')) !== '' ? (string) $entity['id'] : '—';
        }
        return $value !== '' ? $value : '—';
    }
    if (is_array($cursor)) {
        return implode(', ', array_map('strval', $cursor));
    }

    return '—';
};
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
      <label for="environment">環境</label>
      <select id="environment" name="environment">
        <option value="">すべて</option>
        <?php foreach ((array) ($tagFilterOptions['environment'] ?? []) as $item): ?>
          <option value="<?= h((string) $item) ?>" <?= (string) $item === $environmentFilter ? 'selected' : '' ?>><?= h((string) $item) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="owner">オーナー</label>
      <select id="owner" name="owner">
        <option value="">すべて</option>
        <?php foreach ((array) ($tagFilterOptions['owner'] ?? []) as $item): ?>
          <option value="<?= h((string) $item) ?>" <?= (string) $item === $ownerFilter ? 'selected' : '' ?>><?= h((string) $item) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="site">サイト</label>
      <select id="site" name="site">
        <option value="">すべて</option>
        <?php foreach ((array) ($tagFilterOptions['site'] ?? []) as $item): ?>
          <option value="<?= h((string) $item) ?>" <?= (string) $item === $siteFilter ? 'selected' : '' ?>><?= h((string) $item) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="zone">ゾーン</label>
      <select id="zone" name="zone">
        <option value="">すべて</option>
        <?php foreach ((array) ($tagFilterOptions['zone'] ?? []) as $item): ?>
          <option value="<?= h((string) $item) ?>" <?= (string) $item === $zoneFilter ? 'selected' : '' ?>><?= h((string) $item) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="lifecycle">ライフサイクル</label>
      <select id="lifecycle" name="lifecycle">
        <option value="">すべて</option>
        <?php foreach ((array) ($tagFilterOptions['lifecycle'] ?? []) as $item): ?>
          <option value="<?= h((string) $item) ?>" <?= (string) $item === $lifecycleFilter ? 'selected' : '' ?>><?= h((string) $item) ?></option>
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
        <tr>
          <?php foreach ($listColumns as $column): ?>
            <th><?= h((string) ($column['label'] ?? '')) ?></th>
          <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($entities as $entity): ?>
          <?php
          $status = (string) ($entity['status'] ?? 'Valid');
          $statusLabel = ui_record_status_label($status);
          $statusClass = ui_record_status_class($status);
          ?>
          <tr>
            <?php foreach ($listColumns as $column): ?>
              <?php
              $path = (string) ($column['path'] ?? '');
              $cell = $resolveCell($entity, $path);
              ?>
              <td>
                <?php if ($path === 'metadata.name'): ?>
                  <a class="text-link" href="/resources/<?= rawurlencode((string) $entity['id']) ?>"><?= h($cell) ?></a>
                <?php elseif ($path === 'metadata.type'): ?>
                  <span class="pill"><?= h($cell) ?></span>
                <?php elseif ($path === 'computed.status'): ?>
                  <span class="pill <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                <?php else: ?>
                  <?= h($cell) ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
