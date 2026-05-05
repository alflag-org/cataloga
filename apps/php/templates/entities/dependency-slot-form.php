<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$id = (string) ($metadata['id'] ?? '');
$name = (string) ($metadata['name'] ?? $id);
$slot = is_array($slot ?? null) ? $slot : [];
$slotKey = (string) ($slot['key'] ?? '');
$slotLabel = (string) ($slot['label'] ?? $slotKey);
$description = (string) ($slot['description'] ?? '');
$multiple = (bool) ($slot['multiple'] ?? true);
$selectedTargets = is_array($selectedTargets ?? null) ? array_values(array_map('strval', $selectedTargets)) : [];
$candidates = is_array($candidates ?? null) ? $candidates : [];
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">依存関係</p>
      <h2><?= h($slotLabel) ?>を設定</h2>
      <p class="meta"><?= h($name) ?> に対する依存関係スロットを更新します。保存先はリソースファイルの <span class="mono">dependencies.<?= h($slotKey) ?></span> です。</p>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <p class="pill error">エラー: <?= h((string) $error) ?></p>
  <?php endif; ?>

  <?php if ($description !== ''): ?>
    <p class="meta"><?= h($description) ?></p>
  <?php endif; ?>

  <form method="post" action="/resources/<?= rawurlencode($id) ?>/dependencies/<?= rawurlencode($slotKey) ?>" class="form-stack mt-2">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label for="targets">対象リソース</label>
      <?php if ($candidates === []): ?>
        <p class="empty-state">互換性のある対象リソースがありません。先に対象リソースを作成してください。</p>
      <?php elseif ($multiple): ?>
        <select id="targets" name="targets[]" multiple>
          <?php foreach ($candidates as $candidate): ?>
            <?php $candidateId = (string) ($candidate['id'] ?? ''); ?>
            <option value="<?= h($candidateId) ?>" <?= in_array($candidateId, $selectedTargets, true) ? 'selected' : '' ?>>
              <?= h((string) (($candidate['name'] ?? '') !== '' ? $candidate['name'] : $candidateId)) ?> (<?= h((string) ($candidate['type'] ?? '')) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <p class="meta">複数選択できます。未選択で保存するとこのスロットを未設定に戻します。</p>
      <?php else: ?>
        <select id="targets" name="targets[]">
          <option value="">未設定</option>
          <?php foreach ($candidates as $candidate): ?>
            <?php $candidateId = (string) ($candidate['id'] ?? ''); ?>
            <option value="<?= h($candidateId) ?>" <?= (string) ($selectedTargets[0] ?? '') === $candidateId ? 'selected' : '' ?>>
              <?= h((string) (($candidate['name'] ?? '') !== '' ? $candidate['name'] : $candidateId)) ?> (<?= h((string) ($candidate['type'] ?? '')) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>

    <section class="panel soft">
      <h3>プレビュー</h3>
      <p class="meta"><?= h($name) ?> の <span class="mono"><?= h($slotKey) ?></span> を選択した対象リソースに置き換えます。</p>
    </section>

    <div class="actions form-actions">
      <a class="secondary-button" href="/resources/<?= rawurlencode($id) ?>">キャンセル</a>
      <button type="submit" class="primary-button">変更を確認</button>
    </div>
  </form>
</div>
