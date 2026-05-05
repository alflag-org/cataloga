<?php
$record = is_array($relation['record'] ?? null) ? $relation['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? '');
$name = (string) ($metadata['name'] ?? '');
$from = (string) ($spec['from'] ?? '');
$to = (string) ($spec['to'] ?? '');
$attributes = is_array($spec['attributes'] ?? null) ? $spec['attributes'] : [];
$sourcePath = (string) ($relation['sourcePath'] ?? '');
$formAction = $mode === 'edit' && $id !== '' ? '/dependencies/' . rawurlencode($id) : '/dependencies';
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow"><?= $mode === 'edit' ? '依存関係を編集' : '依存関係を作成' ?></p>
      <h2><?= $mode === 'edit' ? 'リソース間の関係を更新' : '2つのリソースを接続' ?></h2>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <p class="pill error">エラー: <?= h((string) $error) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= h($formAction) ?>" class="form-stack">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label for="from">元リソース</label>
      <select name="from" id="from">
        <option value="">元リソースを選択</option>
        <?php foreach (($entities ?? []) as $e): ?>
          <option value="<?= h((string) $e['id']) ?>" <?= (string) $e['id'] === $from ? 'selected' : '' ?>><?= h((string) ($e['name'] !== '' ? $e['name'] : $e['id'])) ?> (<?= h((string) $e['type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="type">関係タイプ</label>
      <select name="type" id="type">
        <option value="">関係タイプを選択</option>
        <?php foreach (($relationTypes ?? []) as $rt): ?>
          <option value="<?= h((string) $rt) ?>" <?= (string) $rt === $type ? 'selected' : '' ?>><?= h((string) $rt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="to">先リソース</label>
      <select name="to" id="to">
        <option value="">先リソースを選択</option>
        <?php foreach (($entities ?? []) as $e): ?>
          <option value="<?= h((string) $e['id']) ?>" <?= (string) $e['id'] === $to ? 'selected' : '' ?>><?= h((string) ($e['name'] !== '' ? $e['name'] : $e['id'])) ?> (<?= h((string) $e['type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="name">プレビュー名</label>
      <input type="text" id="name" name="name" value="<?= h($name) ?>" placeholder="cataloga runs_on host01">
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
      <button type="submit" class="primary-button">保存</button>
    </div>
  </form>
</div>
