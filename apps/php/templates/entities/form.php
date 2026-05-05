<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? $selectedSchemaId ?? '');
$name = (string) ($metadata['name'] ?? '');
$sourcePath = (string) ($entity['sourcePath'] ?? '');
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
$defaultEnvironments = ['prod', 'staging', 'dev', 'test', 'home'];
$defaultOwners = ['infra-team', 'platform-team', 'network-team', 'security-team'];
?>

<?php if ($mode === 'create' && $selectedSchema === null): ?>
  <div class="panel">
    <div class="title-row">
      <div class="title-stack">
        <p class="eyebrow">リソース作成</p>
        <h2>ステップ 1/5: タイプを選択</h2>
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
            <h3>ステップ 2/5: 基本情報</h3>
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
            <h3>ステップ 3/5: 設定</h3>
            <p class="meta">選択したタイプパックのスキーマ定義に基づいて入力します。</p>
          </div>
        </div>

        <?php if ($selectedSchema !== null): ?>
          <?php foreach (($selectedSchema['properties'] ?? []) as $field => $def): ?>
            <?php
            $fieldName = (string) $field;
            $ft = (string) ($def['type'] ?? 'string');
            $val = $spec[$fieldName] ?? '';
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
                  <?php foreach ($enum as $opt): ?>
                    <option value="<?= h((string) $opt) ?>" <?= (string) $val === (string) $opt ? 'selected' : '' ?>><?= h((string) $opt) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($fieldName === 'environment'): ?>
                <select name="schema_fields[<?= h($fieldName) ?>]">
                  <option value="">選択</option>
                  <?php foreach ($defaultEnvironments as $env): ?>
                    <option value="<?= h($env) ?>" <?= (string) $val === $env ? 'selected' : '' ?>><?= h($env) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($fieldName === 'owner'): ?>
                <select name="schema_fields[<?= h($fieldName) ?>]">
                  <option value="">選択</option>
                  <?php foreach ($defaultOwners as $owner): ?>
                    <option value="<?= h($owner) ?>" <?= (string) $val === $owner ? 'selected' : '' ?>><?= h($owner) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($ft === 'boolean'): ?>
                <select name="schema_fields[<?= h($fieldName) ?>]">
                  <option value="false" <?= $val === false ? 'selected' : '' ?>>false</option>
                  <option value="true" <?= $val === true ? 'selected' : '' ?>>true</option>
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
            <h3>ステップ 4/5: 依存関係</h3>
            <p class="meta">任意入力です。既存リソースを選択してください。空欄ならスキップします。</p>
          </div>
        </div>

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
      </section>

      <section class="panel soft">
        <div class="title-row">
          <div class="title-stack">
            <h3>ステップ 5/5: 確認</h3>
            <p class="meta">ドラフト変更を作成し、検証結果と差分を確認してから保存できます。</p>
          </div>
        </div>

        <div class="actions">
          <a class="secondary-button" href="/resources">キャンセル</a>
          <button type="submit" class="primary-button"><?= $mode === 'edit' ? '変更を確認' : '保存' ?></button>
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
