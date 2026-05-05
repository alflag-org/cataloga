<?php
$record = is_array($relation['record'] ?? null) ? $relation['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? (string) ($selectedRelationType ?? ''));
$name = (string) ($metadata['name'] ?? '');
$from = (string) ($spec['from'] ?? (string) ($selectedSource ?? ''));
$to = (string) ($spec['to'] ?? (string) ($selectedTarget ?? ''));
$attributes = is_array($spec['attributes'] ?? null) ? $spec['attributes'] : [];
$sourcePath = (string) ($relation['sourcePath'] ?? '');
$formAction = $mode === 'edit' && $id !== '' ? '/dependencies/' . rawurlencode($id) : '/dependencies';

$entities = is_array($entities ?? null) ? $entities : [];
$targetEntities = is_array($targetEntities ?? null) ? $targetEntities : $entities;
$relationTypes = is_array($relationTypes ?? null) ? $relationTypes : [];
$relationSchemas = is_array($relationSchemas ?? null) ? $relationSchemas : [];
$sourceEntity = is_array($sourceEntity ?? null) ? $sourceEntity : null;
$targetEntity = is_array($targetEntity ?? null) ? $targetEntity : null;

$previewSourceLabel = $sourceEntity !== null
    ? ((string) ($sourceEntity['name'] ?? '') !== '' ? (string) $sourceEntity['name'] : (string) ($sourceEntity['id'] ?? ''))
    : $from;
$previewTargetLabel = $targetEntity !== null
    ? ((string) ($targetEntity['name'] ?? '') !== '' ? (string) $targetEntity['name'] : (string) ($targetEntity['id'] ?? ''))
    : $to;
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">高度な依存関係</p>
      <h2><?= $mode === 'edit' ? '高度な依存関係を編集' : '高度な依存関係を作成' ?></h2>
      <p class="meta">この画面は、通常のリソース画面では表現できない依存関係を手動で作成するための高度な画面です。通常はリソース詳細画面から依存関係を設定してください。</p>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <p class="pill error">エラー: <?= h((string) $error) ?></p>
  <?php endif; ?>

  <form method="get" action="/dependencies/new" class="filters mt-2">
    <div class="field">
      <label for="source_filter">元リソース</label>
      <select name="source" id="source_filter">
        <option value="">元リソースを選択</option>
        <?php foreach ($entities as $e): ?>
          <option value="<?= h((string) $e['id']) ?>" <?= (string) $e['id'] === $from ? 'selected' : '' ?>><?= h((string) ($e['name'] !== '' ? $e['name'] : $e['id'])) ?> (<?= h((string) $e['type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="type_filter">関係タイプ</label>
      <select name="type" id="type_filter">
        <option value="">関係タイプを選択</option>
        <?php foreach ($relationTypes as $rt): ?>
          <option value="<?= h((string) $rt) ?>" <?= (string) $rt === $type ? 'selected' : '' ?>><?= h((string) $rt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="target_filter">先リソース</label>
      <select name="target" id="target_filter">
        <option value="">先リソースを選択</option>
        <?php foreach ($targetEntities as $e): ?>
          <option value="<?= h((string) $e['id']) ?>" <?= (string) $e['id'] === $to ? 'selected' : '' ?>><?= h((string) ($e['name'] !== '' ? $e['name'] : $e['id'])) ?> (<?= h((string) $e['type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label>&nbsp;</label>
      <button type="submit" class="secondary-button">候補を絞り込み</button>
    </div>
  </form>

  <form method="post" action="<?= h($formAction) ?>" class="form-stack mt-2">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label for="from">元リソース</label>
      <select name="from" id="from" required>
        <option value="">元リソースを選択</option>
        <?php foreach ($entities as $e): ?>
          <option value="<?= h((string) $e['id']) ?>" <?= (string) $e['id'] === $from ? 'selected' : '' ?>><?= h((string) ($e['name'] !== '' ? $e['name'] : $e['id'])) ?> (<?= h((string) $e['type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="type">関係タイプ</label>
      <select name="type" id="type" required>
        <option value="">関係タイプを選択</option>
        <?php foreach ($relationTypes as $rt): ?>
          <option value="<?= h((string) $rt) ?>" <?= (string) $rt === $type ? 'selected' : '' ?>><?= h((string) $rt) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($type !== '' && is_array($relationSchemas[$type] ?? null)): ?>
        <?php
        $schema = $relationSchemas[$type];
        $sourceTypes = is_array($schema['sourceTypes'] ?? null) ? $schema['sourceTypes'] : [];
        $targetTypes = is_array($schema['targetTypes'] ?? null) ? $schema['targetTypes'] : [];
        ?>
        <?php if ($sourceTypes !== [] || $targetTypes !== []): ?>
          <p class="meta">
            <?php if ($sourceTypes !== []): ?>
              元タイプ: <?= h(implode(', ', array_map('strval', $sourceTypes))) ?>
            <?php endif; ?>
            <?php if ($sourceTypes !== [] && $targetTypes !== []): ?> / <?php endif; ?>
            <?php if ($targetTypes !== []): ?>
              先タイプ: <?= h(implode(', ', array_map('strval', $targetTypes))) ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="to">先リソース</label>
      <select name="to" id="to" required>
        <option value="">先リソースを選択</option>
        <?php foreach ($targetEntities as $e): ?>
          <option value="<?= h((string) $e['id']) ?>" <?= (string) $e['id'] === $to ? 'selected' : '' ?>><?= h((string) ($e['name'] !== '' ? $e['name'] : $e['id'])) ?> (<?= h((string) $e['type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="name">プレビュー名</label>
      <input type="text" id="name" name="name" value="<?= h($name) ?>" placeholder="cataloga runs_on host01">
      <?php if ($previewSourceLabel !== '' && $type !== '' && $previewTargetLabel !== ''): ?>
        <p class="meta">プレビュー: <?= h($previewSourceLabel) ?> は <?= h($previewTargetLabel) ?> に <?= h($type) ?> します。</p>
      <?php endif; ?>
    </div>

    <details>
      <summary>詳細設定</summary>
      <div class="field mt-2">
        <label for="id">依存関係ID</label>
        <input type="text" id="id" name="id" value="<?= h($id) ?>">
      </div>
      <div class="field">
        <label>属性 JSON</label>
        <textarea name="attributes" rows="6"><?= h(format_json($attributes)) ?></textarea>
      </div>
      <div class="field">
        <label>保存パス上書き</label>
        <input type="text" name="sourcePath" value="<?= h($sourcePath) ?>">
      </div>
    </details>

    <div class="actions">
      <a class="secondary-button" href="/dependencies">キャンセル</a>
      <button type="submit" class="primary-button">変更を確認</button>
    </div>
  </form>
</div>
