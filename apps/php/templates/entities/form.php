<?php
$viewModel = $viewModel ?? null;
if (!$viewModel instanceof \Cataloga\View\ResourceFormViewModel) {
    throw new \RuntimeException('ResourceFormViewModel is required.');
}

$id = $viewModel->id;
$type = $viewModel->type;
$name = $viewModel->name;
$sourcePath = $viewModel->sourcePath;
$formAction = $viewModel->formAction;
$schemaItems = $viewModel->schemaItems;
$selectedSchema = $viewModel->selectedSchema;
$basicTags = $viewModel->basicTags;
$specFields = $viewModel->specFields;
$additionalTags = $viewModel->additionalTags;
$specJson = $viewModel->specJson;
?>

<?php if ($viewModel->isCreateWithoutSchema): ?>
  <div class="panel">
    <div class="title-row">
      <div class="title-stack">
        <p class="eyebrow">リソース作成</p>
        <h2>タイプを選択</h2>
        <p class="meta">インストール済みタイプパックからリソースタイプを選択します。</p>
      </div>
    </div>

    <?php if ($schemaItems === []): ?>
      <div class="empty-state">
        利用可能なリソースタイプがありません。
        <div class="actions mt-2">
          <a class="secondary-button" href="/type-packs">タイプパックをインストール</a>
        </div>
      </div>
    <?php else: ?>
      <div class="metrics">
        <?php foreach ($schemaItems as $schema): ?>
          <article class="metric-card">
            <span><?= h((string) ($schema['id'] ?? '')) ?></span>
            <strong><?= h((string) ($schema['name'] ?? $schema['id'] ?? '')) ?></strong>
            <p><?= h((string) ($schema['description'] ?? '')) ?></p>
            <div class="actions mt-2">
              <a class="secondary-button" href="/resources/new?schema=<?= rawurlencode((string) ($schema['id'] ?? '')) ?>">選択</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="panel">
    <div class="title-row">
      <div class="title-stack">
        <p class="eyebrow"><?= $mode === 'edit' ? 'リソース編集' : 'リソース作成' ?></p>
        <h2><?= $mode === 'edit' ? 'リソースを編集' : 'リソースを作成' ?></h2>
      </div>
      <div class="actions">
        <?php if ($mode === 'create'): ?>
          <a class="secondary-button" href="/resources/new">タイプ選択へ戻る</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <p class="pill error">検証エラー: <?= h((string) $error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= h($formAction) ?>" class="form-stack" data-tag-editor-root>
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="type" value="<?= h($type) ?>">

      <section class="panel soft">
        <div class="title-row">
          <div class="title-stack">
            <h3>1. 基本情報</h3>
          </div>
        </div>

        <div class="field">
          <label>リソースタイプ</label>
          <input type="text" readonly value="<?= h((string) ($selectedSchema['name'] ?? $type)) ?>">
        </div>

        <div class="field">
          <label for="name">名前</label>
          <input type="text" required id="name" name="name" value="<?= h($name) ?>" placeholder="cataloga">
        </div>
      </section>

      <section class="panel soft">
        <div class="title-row">
          <div class="title-stack">
            <h3>2. 管理情報</h3>
          </div>
        </div>

        <?php foreach ($basicTags as $tag): ?>
          <div class="field">
            <label><?= h((string) ($tag['label'] ?? '')) ?><?= (bool) ($tag['required'] ?? false) ? ' *' : '' ?></label>
            <input type="hidden" name="basic_tag_key[]" value="<?= h((string) ($tag['key'] ?? '')) ?>">
            <?php if (is_array($tag['values'] ?? null) && ($tag['values'] ?? []) !== []): ?>
              <select name="basic_tag_value[]" <?= (bool) ($tag['required'] ?? false) ? 'required' : '' ?>>
                <option value="">選択</option>
                <?php foreach (($tag['values'] ?? []) as $option): ?>
                  <option value="<?= h((string) $option) ?>" <?= (string) $option === (string) ($tag['value'] ?? '') ? 'selected' : '' ?>><?= h((string) $option) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="text" name="basic_tag_value[]" value="<?= h((string) ($tag['value'] ?? '')) ?>" <?= (bool) ($tag['required'] ?? false) ? 'required' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="title-row mt-3">
          <div class="title-stack">
            <h3>追加タグ</h3>
            <p class="meta">必要な場合のみ key-value を追加します。</p>
          </div>
        </div>

        <div class="tag-editor" data-tag-editor>
          <?php foreach ($additionalTags as $entry): ?>
            <div class="tag-row" data-tag-row>
              <div class="field">
                <label>キー</label>
                <input type="text" name="tag_key[]" value="<?= h((string) ($entry['key'] ?? '')) ?>">
              </div>
              <div class="field">
                <label>値</label>
                <input type="text" name="tag_value[]" value="<?= h((string) ($entry['value'] ?? '')) ?>">
              </div>
              <button type="button" class="secondary-button" data-remove-tag>削除</button>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="actions mt-2">
          <button type="button" class="secondary-button" data-add-tag>+ タグを追加</button>
        </div>
      </section>

      <section class="panel soft">
        <div class="title-row">
          <div class="title-stack">
            <h3>3. 設定</h3>
            <p class="meta">タイプパックの定義に基づく項目です。</p>
          </div>
        </div>

        <?php foreach ($specFields as $field): ?>
          <?php
          $fieldName = (string) ($field['name'] ?? '');
          $fieldType = (string) ($field['type'] ?? 'string');
          $fieldFormat = (string) ($field['format'] ?? '');
          $fieldEnum = is_array($field['enum'] ?? null) ? $field['enum'] : [];
          $value = $field['value'] ?? '';
          ?>
          <div class="field">
            <label><?= h($fieldName) ?></label>

            <?php if ($fieldFormat === 'entity_ref'): ?>
              <input type="text" name="schema_fields[<?= h($fieldName) ?>]" value="<?= h((string) $value) ?>" placeholder="resource.id">
            <?php elseif ($fieldEnum !== []): ?>
              <select name="schema_fields[<?= h($fieldName) ?>]">
                <option value="">選択</option>
                <?php foreach ($fieldEnum as $option): ?>
                  <option value="<?= h((string) $option) ?>" <?= (string) $option === (string) $value ? 'selected' : '' ?>><?= h((string) $option) ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($fieldType === 'boolean'): ?>
              <select name="schema_fields[<?= h($fieldName) ?>]">
                <option value="false" <?= $value === false || $value === 'false' ? 'selected' : '' ?>>false</option>
                <option value="true" <?= $value === true || $value === 'true' ? 'selected' : '' ?>>true</option>
              </select>
            <?php elseif ($fieldType === 'array'): ?>
              <input type="text" name="schema_fields[<?= h($fieldName) ?>][]" value="<?= h(is_array($value) ? implode(',', array_map('strval', $value)) : (string) $value) ?>" placeholder="カンマ区切りで入力">
            <?php elseif ($fieldType === 'text'): ?>
              <textarea name="schema_fields[<?= h($fieldName) ?>]" rows="3"><?= h((string) $value) ?></textarea>
            <?php elseif ($fieldType === 'number' || str_contains(strtolower($fieldName), 'port') || str_contains(strtolower($fieldName), 'vlan')): ?>
              <input type="number" name="schema_fields[<?= h($fieldName) ?>]" value="<?= h((string) $value) ?>">
            <?php else: ?>
              <input type="text" name="schema_fields[<?= h($fieldName) ?>]" value="<?= h((string) $value) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <details class="mt-3">
          <summary><strong>詳細設定</strong></summary>
          <div class="field mt-2">
            <label for="id">リソースID</label>
            <input type="text" id="id" name="id" value="<?= h($id) ?>" placeholder="<?= h((string) $type) ?>.name">
          </div>
          <div class="field">
            <label for="sourcePath">保存パス上書き</label>
            <input type="text" id="sourcePath" name="sourcePath" value="<?= h($sourcePath) ?>">
          </div>
          <div class="field mt-2">
            <label><input type="checkbox" name="advancedMode" value="1"> spec JSON を直接編集</label>
          </div>
          <div class="field">
            <label for="spec">spec (JSON)</label>
            <textarea id="spec" name="spec" rows="8"><?= h($specJson) ?></textarea>
          </div>
        </details>
      </section>

      <div class="actions form-actions">
        <a class="secondary-button" href="/resources">キャンセル</a>
        <button type="submit" class="primary-button">変更を確認</button>
      </div>
    </form>
  </div>
<?php endif; ?>
