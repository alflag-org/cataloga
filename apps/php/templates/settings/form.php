<?php
$settings = is_array($settings ?? null) ? $settings : [];
$tagKeys = is_array($settings['tag_keys'] ?? null) ? $settings['tag_keys'] : [];
$reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : ['cataloga:'];
$rows = [];
foreach ($tagKeys as $key => $config) {
    if (!is_array($config)) {
        continue;
    }
    $rows[] = [
        'key' => (string) $key,
        'label' => (string) ($config['label'] ?? $key),
        'required' => (bool) ($config['required'] ?? false),
        'values' => is_array($config['values'] ?? null) ? implode(', ', array_map('strval', $config['values'])) : '',
        'free_value' => (bool) ($config['free_value'] ?? false),
        'allow_empty' => (bool) ($config['allow_empty'] ?? false),
    ];
}
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">設定</p>
      <h2>タグキー設定</h2>
      <p class="meta">ワークスペースの <span class="mono">registry/settings.yaml</span> を change session 経由で更新します。</p>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <p class="pill error">エラー: <?= h((string) $error) ?></p>
  <?php endif; ?>

  <form method="post" action="/settings" class="form-stack">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label for="reserved_prefixes">予約 prefix</label>
      <input type="text" id="reserved_prefixes" name="reserved_prefixes" value="<?= h(implode(', ', array_map('strval', $reservedPrefixes))) ?>">
      <p class="meta">通常は <span class="mono">cataloga:</span> のみです。import pack 固有の namespace は pack 側で扱います。</p>
    </div>

    <div class="tag-editor" data-settings-tag-editor>
      <?php foreach ($rows as $index => $row): ?>
        <section class="panel soft" data-settings-tag-row>
          <div class="split">
            <div class="field">
              <label>キー</label>
              <input type="text" name="tag_key[]" value="<?= h($row['key']) ?>" required>
            </div>
            <div class="field">
              <label>ラベル</label>
              <input type="text" name="tag_label[]" value="<?= h($row['label']) ?>">
            </div>
          </div>
          <div class="field">
            <label>許可値</label>
            <input type="text" name="tag_values[]" value="<?= h($row['values']) ?>" placeholder="prod, staging, dev">
            <p class="meta">カンマ区切り。空の場合は自由入力として扱えます。</p>
          </div>
          <div class="actions">
            <label class="field inline"><input class="checkbox" type="checkbox" name="tag_required[]" value="<?= h((string) $index) ?>" <?= $row['required'] ? 'checked' : '' ?>>必須</label>
            <label class="field inline"><input class="checkbox" type="checkbox" name="tag_free_value[]" value="<?= h((string) $index) ?>" <?= $row['free_value'] ? 'checked' : '' ?>>自由入力</label>
            <label class="field inline"><input class="checkbox" type="checkbox" name="tag_allow_empty[]" value="<?= h((string) $index) ?>" <?= $row['allow_empty'] ? 'checked' : '' ?>>空値を許可</label>
            <button type="button" class="secondary-button" data-remove-settings-tag>削除</button>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="actions">
      <button type="button" class="secondary-button" data-add-settings-tag>+ タグキーを追加</button>
    </div>

    <div class="actions form-actions">
      <a class="secondary-button" href="/">キャンセル</a>
      <button type="submit" class="primary-button">変更を確認</button>
    </div>
  </form>
</div>

<script>
  (() => {
    const editor = document.querySelector('[data-settings-tag-editor]');
    const addButton = document.querySelector('[data-add-settings-tag]');
    if (!editor || !addButton) {
      return;
    }

    const renumber = () => {
      editor.querySelectorAll('[data-settings-tag-row]').forEach((row, index) => {
        row.querySelectorAll('input[type="checkbox"]').forEach((input) => {
          input.value = String(index);
        });
      });
    };

    const createRow = () => {
      const section = document.createElement('section');
      section.className = 'panel soft';
      section.dataset.settingsTagRow = '';
      section.innerHTML = `
        <div class="split">
          <div class="field">
            <label>キー</label>
            <input type="text" name="tag_key[]" required>
          </div>
          <div class="field">
            <label>ラベル</label>
            <input type="text" name="tag_label[]">
          </div>
        </div>
        <div class="field">
          <label>許可値</label>
          <input type="text" name="tag_values[]" placeholder="prod, staging, dev">
          <p class="meta">カンマ区切り。空の場合は自由入力として扱えます。</p>
        </div>
        <div class="actions">
          <label class="field inline"><input class="checkbox" type="checkbox" name="tag_required[]">必須</label>
          <label class="field inline"><input class="checkbox" type="checkbox" name="tag_free_value[]" checked>自由入力</label>
          <label class="field inline"><input class="checkbox" type="checkbox" name="tag_allow_empty[]">空値を許可</label>
          <button type="button" class="secondary-button" data-remove-settings-tag>削除</button>
        </div>
      `;
      return section;
    };

    addButton.addEventListener('click', () => {
      const row = createRow();
      editor.appendChild(row);
      renumber();
      row.querySelector('input')?.focus();
    });

    editor.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement) || !target.matches('[data-remove-settings-tag]')) {
        return;
      }
      target.closest('[data-settings-tag-row]')?.remove();
      renumber();
    });
  })();
</script>
