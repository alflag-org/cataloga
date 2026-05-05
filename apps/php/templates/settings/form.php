<?php
$settings = is_array($settings ?? null) ? $settings : [];
$tagKeys = is_array($settings['tag_keys'] ?? null) ? $settings['tag_keys'] : [];
$reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : ['cataloga:'];
$defaultManagementTags = is_array($settings['default_management_tags'] ?? null) ? $settings['default_management_tags'] : ['environment', 'owner'];
$resourceTypeProfiles = is_array($settings['resource_type_profiles'] ?? null) ? $settings['resource_type_profiles'] : [];
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
      <h2>管理情報設定</h2>
      <p class="meta">ワークスペースの <span class="mono">registry/settings.yaml</span> を change session 経由で更新します。</p>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <p class="pill error">エラー: <?= h((string) $error) ?></p>
  <?php endif; ?>

  <form method="post" action="/settings" class="form-stack">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <section class="panel soft">
      <div class="title-stack">
        <h3>よく使う管理情報</h3>
        <p class="meta">環境、オーナー、サイト、ゾーン、ライフサイクルなどのタグキーをここで管理します。</p>
      </div>
    </section>

    <section class="panel soft">
      <div class="field">
        <label for="default_management_tags">デフォルト管理タグ</label>
        <input type="text" id="default_management_tags" name="default_management_tags" value="<?= h(implode(', ', array_map('strval', $defaultManagementTags))) ?>" placeholder="environment, owner">
        <p class="meta">resource_type_profiles と type pack 推奨タグがない場合に使います。</p>
      </div>
      <p class="meta">TODO: リソースタイプ別プロファイル（management_tags / list_columns）の編集UIは今後追加予定です。現時点では <span class="mono">registry/settings.yaml</span> を直接編集してください。</p>
      <?php if ($resourceTypeProfiles !== []): ?>
        <details class="mt-2">
          <summary><strong>現在の resource_type_profiles</strong></summary>
          <pre><?= h(format_json($resourceTypeProfiles)) ?></pre>
        </details>
      <?php endif; ?>
    </section>

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

    <details class="mt-2">
      <summary><strong>詳細設定</strong></summary>
      <div class="field mt-2">
        <label for="reserved_prefixes">予約 prefix</label>
        <input type="text" id="reserved_prefixes" name="reserved_prefixes" value="<?= h(implode(', ', array_map('strval', $reservedPrefixes))) ?>">
        <p class="meta">通常は <span class="mono">cataloga:</span> のみです。import pack 固有の namespace は pack 側で扱います。</p>
      </div>
    </details>

    <div class="actions form-actions">
      <a class="secondary-button" href="/">キャンセル</a>
      <button type="submit" class="primary-button">変更を確認</button>
    </div>
  </form>
</div>
