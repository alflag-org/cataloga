<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? ($_POST['id'] ?? ''));
$type = (string) ($metadata['type'] ?? ($_POST['type'] ?? $selectedSchemaId ?? ''));
$name = (string) ($metadata['name'] ?? ($_POST['name'] ?? ''));
$sourcePath = (string) ($entity['sourcePath'] ?? ($_POST['sourcePath'] ?? ''));
$formAction = $mode === 'edit' && $id !== '' ? '/resources/' . rawurlencode($id) : '/resources';

$schemaItems = [];
$selectedSchema = null;
foreach (($schemas ?? []) as $schema) {
    if (($schema['kind'] ?? 'entity') === 'relation') {
        continue;
    }
    $schemaItems[] = $schema;
    if (($schema['id'] ?? '') === $type) {
        $selectedSchema = $schema;
    }
}

$allResources = is_array($entities ?? null) ? $entities : [];
$relationTypes = is_array($relationTypes ?? null) ? $relationTypes : [];
$existingRelations = is_array($existingRelations ?? null) ? $existingRelations : [];

$settings = is_array($settings ?? null) ? $settings : [];
$tagKeys = is_array($settings['tag_keys'] ?? null) ? $settings['tag_keys'] : [];
$reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : ['cataloga:', 'aws:'];

$normalizedTags = [];
$rawTags = $metadata['tags'] ?? [];
if (is_array($rawTags)) {
    foreach ($rawTags as $k => $v) {
        if (is_int($k)) {
            $legacy = trim((string) $v);
            if ($legacy === '') {
                continue;
            }
            if (str_contains($legacy, ':')) {
                [$lk, $lv] = explode(':', $legacy, 2);
                $lk = trim($lk);
                if ($lk === '') {
                    continue;
                }
                $normalizedTags[$lk] = trim($lv);
                continue;
            }
            $normalizedTags[$legacy] = '';
            continue;
        }
        $key = trim((string) $k);
        if ($key === '') {
            continue;
        }
        $normalizedTags[$key] = is_scalar($v) ? trim((string) $v) : '';
    }
}

foreach (['environment', 'owner', 'site', 'zone', 'visibility', 'lifecycle', 'criticality', 'managed-by', 'cost-center', 'data-classification', 'backup-policy', 'patch-policy'] as $legacyKey) {
    if (($normalizedTags[$legacyKey] ?? '') !== '') {
        continue;
    }
    $legacyValue = trim((string) ($spec[$legacyKey] ?? ($_POST['schema_fields'][$legacyKey] ?? '')));
    if ($legacyValue !== '') {
        $normalizedTags[$legacyKey] = $legacyValue;
    }
}

if (is_array($_POST['basic_tag_key'] ?? null) && is_array($_POST['basic_tag_value'] ?? null)) {
    $normalizedTags = [];
    $postTagKeys = $_POST['basic_tag_key'];
    $postTagValues = $_POST['basic_tag_value'];
    $count = min(count($postTagKeys), count($postTagValues));
    for ($i = 0; $i < $count; $i++) {
        $k = trim((string) ($postTagKeys[$i] ?? ''));
        if ($k === '') {
            continue;
        }
        $normalizedTags[$k] = trim((string) ($postTagValues[$i] ?? ''));
    }

    if (is_array($_POST['tag_key'] ?? null) && is_array($_POST['tag_value'] ?? null)) {
        $extraKeys = $_POST['tag_key'];
        $extraValues = $_POST['tag_value'];
        $extraCount = min(count($extraKeys), count($extraValues));
        for ($i = 0; $i < $extraCount; $i++) {
            $k = trim((string) ($extraKeys[$i] ?? ''));
            if ($k === '') {
                continue;
            }
            $normalizedTags[$k] = trim((string) ($extraValues[$i] ?? ''));
        }
    }
}

$requiredTagKeys = is_array($selectedSchema['requiredTags'] ?? null) ? $selectedSchema['requiredTags'] : [];
$recommendedTagKeys = is_array($selectedSchema['recommendedTags'] ?? null) ? $selectedSchema['recommendedTags'] : [];

$basicTagOrder = ['environment', 'owner', 'site', 'zone', 'lifecycle'];
$basicTagKeys = $basicTagOrder;
foreach (array_merge($requiredTagKeys, $recommendedTagKeys) as $schemaTagKey) {
    $schemaTagKey = (string) $schemaTagKey;
    if ($schemaTagKey === '' || in_array($schemaTagKey, $basicTagKeys, true)) {
        continue;
    }
    $basicTagKeys[] = $schemaTagKey;
}

$additionalTags = [];
foreach ($normalizedTags as $key => $value) {
    if (in_array($key, $basicTagKeys, true)) {
        continue;
    }

    $isReserved = false;
    foreach ($reservedPrefixes as $prefix) {
        if (is_string($prefix) && $prefix !== '' && str_starts_with((string) $key, $prefix)) {
            $isReserved = true;
            break;
        }
    }
    if ($isReserved) {
        continue;
    }

    $additionalTags[$key] = (string) $value;
}

$additionalRows = max(3, count($additionalTags) + 1);

$dependencySlots = is_array($selectedSchema['dependencySlots'] ?? null) ? $selectedSchema['dependencySlots'] : [];
$currentDependencyValues = [];
if ($id !== '' && $dependencySlots !== []) {
    foreach ($dependencySlots as $slot) {
        $slotKey = (string) ($slot['key'] ?? '');
        $relationType = (string) ($slot['relation_type'] ?? '');
        $direction = (string) ($slot['direction'] ?? 'outgoing');
        if ($slotKey === '' || $relationType === '') {
            continue;
        }
        $values = [];
        foreach ($existingRelations as $relation) {
            if ((string) ($relation['type'] ?? '') !== $relationType) {
                continue;
            }
            if ($direction === 'incoming' && (string) ($relation['to'] ?? '') === $id) {
                $values[] = (string) ($relation['from'] ?? '');
            }
            if ($direction === 'outgoing' && (string) ($relation['from'] ?? '') === $id) {
                $values[] = (string) ($relation['to'] ?? '');
            }
        }
        $currentDependencyValues[$slotKey] = array_values(array_unique(array_filter($values)));
    }
}

$dependencyValuesFromPost = [];
foreach ($_POST as $postKey => $postValue) {
    if (!is_string($postKey) || !str_starts_with($postKey, 'dependency_slot_target_')) {
        continue;
    }
    $slotKey = substr($postKey, strlen('dependency_slot_target_'));
    if ($slotKey === '') {
        continue;
    }
    if (is_array($postValue)) {
        $dependencyValuesFromPost[$slotKey] = array_values(array_filter(array_map(static fn ($x): string => trim((string) $x), $postValue), static fn (string $x): bool => $x !== ''));
    }
}
?>

<?php if ($mode === 'create' && $selectedSchema === null): ?>
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
        <h2><?= $mode === 'edit' ? '変更内容を確認して更新' : 'ガイド付きでリソースを作成' ?></h2>
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

    <form method="post" action="<?= h($formAction) ?>" class="form-stack">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="type" value="<?= h($type) ?>">

      <section class="panel soft">
        <div class="title-row">
          <div class="title-stack">
            <h3>基本情報</h3>
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

        <h3>基本タグ</h3>
        <?php foreach ($basicTagKeys as $tagKey): ?>
          <?php
          $tagConfig = is_array($tagKeys[$tagKey] ?? null) ? $tagKeys[$tagKey] : [];
          $tagLabel = (string) ($tagConfig['label'] ?? $tagKey);
          $values = is_array($tagConfig['values'] ?? null) ? $tagConfig['values'] : [];
          $value = (string) ($normalizedTags[$tagKey] ?? '');
          $required = in_array($tagKey, $requiredTagKeys, true) || (bool) ($tagConfig['required'] ?? false);
          ?>
          <div class="field">
            <label><?= h($tagLabel) ?><?= $required ? ' *' : '' ?></label>
            <input type="hidden" name="basic_tag_key[]" value="<?= h($tagKey) ?>">
            <?php if ($values !== []): ?>
              <select name="basic_tag_value[]" <?= $required ? 'required' : '' ?>>
                <option value="">選択</option>
                <?php foreach ($values as $option): ?>
                  <option value="<?= h((string) $option) ?>" <?= (string) $option === $value ? 'selected' : '' ?>><?= h((string) $option) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="text" name="basic_tag_value[]" value="<?= h($value) ?>" <?= $required ? 'required' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <h3>追加タグ</h3>
        <p class="meta">キーと値を入力してください。`cataloga:` と `aws:` のプレフィックスは予約済みです。</p>

        <?php
        $additionalEntries = array_values(array_map(
            static fn (string $k, string $v): array => ['key' => $k, 'value' => $v],
            array_keys($additionalTags),
            array_values($additionalTags)
        ));
        ?>
        <?php for ($i = 0; $i < $additionalRows; $i++): ?>
          <?php
          $entry = $additionalEntries[$i] ?? ['key' => '', 'value' => ''];
          ?>
          <div class="split">
            <div class="field">
              <label>キー</label>
              <input type="text" name="tag_key[]" value="<?= h((string) $entry['key']) ?>" placeholder="managed-by">
            </div>
            <div class="field">
              <label>値</label>
              <input type="text" name="tag_value[]" value="<?= h((string) $entry['value']) ?>" placeholder="ansible">
            </div>
          </div>
        <?php endfor; ?>

        <details>
          <summary>詳細設定</summary>
          <div class="field mt-2">
            <label for="id">リソースID</label>
            <input type="text" id="id" name="id" value="<?= h($id) ?>" placeholder="<?= h((string) $type) ?>.name">
          </div>
          <div class="field">
            <label for="sourcePath">保存パス上書き</label>
            <input type="text" id="sourcePath" name="sourcePath" value="<?= h($sourcePath) ?>">
          </div>
        </details>
      </section>

      <section class="panel soft">
        <div class="title-row">
          <div class="title-stack">
            <h3>設定</h3>
            <p class="meta">選択したタイプパックのスキーマ定義に基づいて入力します。</p>
          </div>
        </div>

        <?php if ($selectedSchema !== null): ?>
          <?php foreach (($selectedSchema['properties'] ?? []) as $field => $def): ?>
            <?php
            $fieldName = (string) $field;
            if (in_array($fieldName, ['environment', 'owner', 'site', 'zone', 'visibility', 'lifecycle', 'criticality', 'managed-by', 'cost-center', 'data-classification', 'backup-policy', 'patch-policy'], true)) {
                continue;
            }
            $ft = (string) ($def['type'] ?? 'string');
            $val = $spec[$fieldName] ?? ($_POST['schema_fields'][$fieldName] ?? '');
            $enum = is_array($def['enum'] ?? null) ? $def['enum'] : [];
            ?>
            <div class="field">
              <label><?= h($fieldName) ?></label>

              <?php if (($def['format'] ?? '') === 'entity_ref'): ?>
                <select name="schema_fields[<?= h($fieldName) ?>]">
                  <option value="">リソースを選択...</option>
                  <?php foreach ($allResources as $ent): ?>
                    <option value="<?= h((string) $ent['id']) ?>" <?= (string) $val === (string) $ent['id'] ? 'selected' : '' ?>><?= h((string) ($ent['name'] !== '' ? $ent['name'] : $ent['id'])) ?> (<?= h((string) $ent['type']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($enum !== []): ?>
                <select name="schema_fields[<?= h($fieldName) ?>]">
                  <option value="">選択</option>
                  <?php foreach ($enum as $opt): ?>
                    <option value="<?= h((string) $opt) ?>" <?= (string) $val === (string) $opt ? 'selected' : '' ?>><?= h((string) $opt) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($ft === 'boolean'): ?>
                <select name="schema_fields[<?= h($fieldName) ?>]">
                  <option value="false" <?= $val === false || $val === 'false' ? 'selected' : '' ?>>false</option>
                  <option value="true" <?= $val === true || $val === 'true' ? 'selected' : '' ?>>true</option>
                </select>
              <?php elseif ($ft === 'array'): ?>
                <input type="text" name="schema_fields[<?= h($fieldName) ?>][]" value="<?= h(is_array($val) ? implode(',', array_map('strval', $val)) : (string) $val) ?>" placeholder="カンマ区切りで入力">
              <?php elseif ($ft === 'text'): ?>
                <textarea name="schema_fields[<?= h($fieldName) ?>]" rows="3"><?= h((string) $val) ?></textarea>
              <?php elseif ($ft === 'number' || str_contains(strtolower($fieldName), 'port') || str_contains(strtolower($fieldName), 'vlan')): ?>
                <input type="number" name="schema_fields[<?= h($fieldName) ?>]" value="<?= h((string) $val) ?>">
              <?php else: ?>
                <input type="text" name="schema_fields[<?= h($fieldName) ?>]" value="<?= h((string) $val) ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

      <section class="panel soft">
        <div class="title-row">
          <div class="title-stack">
            <h3>依存関係</h3>
            <p class="meta">通常はスロットごとに設定します。表現できない場合は高度な依存関係を使用してください。</p>
          </div>
        </div>

        <?php if ($dependencySlots !== []): ?>
          <?php foreach ($dependencySlots as $slot): ?>
            <?php
            $slotKey = (string) ($slot['key'] ?? '');
            $direction = (string) ($slot['direction'] ?? 'outgoing');
            $label = (string) ($slot['label'] ?? $slotKey);
            $description = (string) ($slot['description'] ?? '');
            $multiple = (bool) ($slot['multiple'] ?? true);
            $required = (bool) ($slot['required'] ?? false);
            $targetTypes = is_array($slot['target_types'] ?? null) ? $slot['target_types'] : [];
            $sourceTypes = is_array($slot['source_types'] ?? null) ? $slot['source_types'] : [];

            $candidates = [];
            foreach ($allResources as $resource) {
                $resourceType = (string) ($resource['type'] ?? '');
                if ($direction === 'outgoing' && $targetTypes !== [] && !in_array($resourceType, $targetTypes, true)) {
                    continue;
                }
                if ($direction === 'incoming' && $sourceTypes !== [] && !in_array($resourceType, $sourceTypes, true)) {
                    continue;
                }
                $candidates[] = $resource;
            }

            $selectedValues = $dependencyValuesFromPost[$slotKey] ?? ($currentDependencyValues[$slotKey] ?? []);
            if ($selectedValues === []) {
                $selectedValues = [''];
            }
            if ($multiple) {
                $selectedValues[] = '';
                $selectedValues = array_slice(array_values(array_unique($selectedValues)), 0, 3);
            } else {
                $selectedValues = [trim((string) ($selectedValues[0] ?? ''))];
            }
            ?>
            <div class="field">
              <label><?= h($label) ?><?= $required ? ' *' : '' ?></label>
              <?php if ($description !== ''): ?><p class="meta"><?= h($description) ?></p><?php endif; ?>

              <?php if ($candidates === []): ?>
                <?php if ($direction === 'outgoing'): ?>
                  <p class="empty-state">互換性のある対象リソースがありません。先にリソースを作成するか、この依存関係をスキップしてください。</p>
                <?php else: ?>
                  <p class="empty-state">互換性のある参照元リソースがありません。先にリソースを作成するか、この依存関係をスキップしてください。</p>
                <?php endif; ?>
              <?php else: ?>
                <?php foreach ($selectedValues as $selectedValue): ?>
                  <select name="dependency_slot_target_<?= h($slotKey) ?>[]" <?= $required ? 'required' : '' ?>>
                    <option value="">選択</option>
                    <?php foreach ($candidates as $candidate): ?>
                      <option value="<?= h((string) $candidate['id']) ?>" <?= (string) $candidate['id'] === (string) $selectedValue ? 'selected' : '' ?>><?= h((string) ($candidate['name'] !== '' ? $candidate['name'] : $candidate['id'])) ?> (<?= h((string) $candidate['type']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <details>
            <summary>高度な依存関係を追加</summary>
            <p class="meta mt-2">スロット表現できない依存関係のみ手動で追加してください。</p>
            <?php for ($i = 0; $i < 2; $i++): ?>
              <div class="split mt-2">
                <div class="field">
                  <label>関係タイプ</label>
                  <select name="dependency_type[]">
                    <option value="">関係タイプを選択</option>
                    <?php foreach ($relationTypes as $relationType): ?>
                      <option value="<?= h((string) $relationType) ?>"><?= h((string) $relationType) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label>対象リソース</label>
                  <select name="dependency_target[]">
                    <option value="">リソースを選択</option>
                    <?php foreach ($allResources as $ent): ?>
                      <option value="<?= h((string) $ent['id']) ?>"><?= h((string) ($ent['name'] !== '' ? $ent['name'] : $ent['id'])) ?> (<?= h((string) $ent['type']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            <?php endfor; ?>
          </details>
        <?php else: ?>
          <?php if ($relationTypes === []): ?>
            <p class="meta">利用可能な依存関係タイプがありません。依存関係スキーマを含むタイプパックをインストールしてください。</p>
          <?php else: ?>
            <?php for ($i = 0; $i < 3; $i++): ?>
              <div class="split">
                <div class="field">
                  <label>関係タイプ</label>
                  <select name="dependency_type[]">
                    <option value="">関係タイプを選択</option>
                    <?php foreach ($relationTypes as $relationType): ?>
                      <option value="<?= h((string) $relationType) ?>"><?= h((string) $relationType) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label>対象リソース</label>
                  <select name="dependency_target[]">
                    <option value="">リソースを選択</option>
                    <?php foreach ($allResources as $ent): ?>
                      <option value="<?= h((string) $ent['id']) ?>"><?= h((string) ($ent['name'] !== '' ? $ent['name'] : $ent['id'])) ?> (<?= h((string) $ent['type']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            <?php endfor; ?>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="panel soft">
        <div class="title-row">
          <div class="title-stack">
            <h3>確認</h3>
            <p class="meta">ドラフト変更を作成し、検証結果と差分を確認してから保存できます。</p>
          </div>
        </div>

        <div class="actions form-actions">
          <a class="secondary-button" href="/resources">キャンセル</a>
          <button type="submit" class="primary-button">変更を確認</button>
        </div>
      </section>

      <details>
        <summary>詳細設定</summary>
        <div class="field mt-2">
          <label><input type="checkbox" name="advancedMode" value="1"> spec JSON を直接編集</label>
        </div>
        <div class="field">
          <label for="spec">spec (JSON)</label>
          <textarea id="spec" name="spec" rows="8"><?= h(format_json($spec)) ?></textarea>
        </div>
      </details>
    </form>
  </div>
<?php endif; ?>
