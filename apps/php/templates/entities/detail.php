<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? 'unknown');
$name = (string) ($metadata['name'] ?? $id);
$dependsOn = is_array($dependsOn ?? null) ? $dependsOn : [];
$usedBy = is_array($usedBy ?? null) ? $usedBy : [];
$tagGroups = is_array($tagGroups ?? null) ? $tagGroups : ['basic' => [], 'note' => [], 'todo' => [], 'risk' => [], 'other' => []];
$dependencySlotGroups = is_array($dependencySlotGroups ?? null) ? $dependencySlotGroups : ['slots' => [], 'other' => []];
$softAssociations = is_array($softAssociations ?? null) ? $softAssociations : [];

$environment = (string) ($tagGroups['basic']['environment'] ?? '');
$owner = (string) ($tagGroups['basic']['owner'] ?? '');
?>
<div class="panel soft">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">リソース詳細</p>
      <h2><?= h(ucfirst($type)) ?>: <?= h($name) ?></h2>
    </div>
    <div class="actions">
      <a class="primary-button" href="/resources/<?= rawurlencode($id) ?>/edit">リソースを編集</a>
      <a class="secondary-button" href="/dependencies/new?source=<?= rawurlencode($id) ?>">高度な依存関係を追加</a>
    </div>
  </div>

  <div class="metrics">
    <article class="metric-card">
      <span>状態</span>
      <strong>正常</strong>
    </article>
    <article class="metric-card">
      <span>環境</span>
      <strong><?= h($environment !== '' ? $environment : '—') ?></strong>
    </article>
    <article class="metric-card">
      <span>オーナー</span>
      <strong><?= h($owner !== '' ? $owner : '—') ?></strong>
    </article>
    <article class="metric-card">
      <span>更新</span>
      <strong>—</strong>
    </article>
  </div>
</div>

<div class="split">
  <section class="panel">
    <div class="title-row">
      <div class="title-stack">
        <h3>タグ</h3>
      </div>
    </div>

    <?php if (($tagGroups['basic'] ?? []) !== []): ?>
      <p class="eyebrow">基本</p>
      <ul class="clean">
        <?php foreach ($tagGroups['basic'] as $key => $value): ?>
          <li><strong><?= h((string) $key) ?></strong> = <?= h((string) $value) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (($tagGroups['note'] ?? []) !== []): ?>
      <p class="eyebrow mt-3">補足</p>
      <ul class="clean">
        <?php foreach ($tagGroups['note'] as $key => $value): ?>
          <li><strong><?= h((string) $key) ?></strong> = <?= h((string) $value) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (($tagGroups['todo'] ?? []) !== []): ?>
      <p class="eyebrow mt-3">TODO</p>
      <ul class="clean">
        <?php foreach ($tagGroups['todo'] as $key => $value): ?>
          <li><strong><?= h((string) $key) ?></strong> = <?= h((string) $value) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (($tagGroups['risk'] ?? []) !== []): ?>
      <p class="eyebrow mt-3">注意</p>
      <ul class="clean">
        <?php foreach ($tagGroups['risk'] as $key => $value): ?>
          <li><strong><?= h((string) $key) ?></strong> = <?= h((string) $value) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (($tagGroups['other'] ?? []) !== []): ?>
      <p class="eyebrow mt-3">その他</p>
      <ul class="clean">
        <?php foreach ($tagGroups['other'] as $key => $value): ?>
          <li><strong><?= h((string) $key) ?></strong> = <?= h((string) $value) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (($tagGroups['basic'] ?? []) === [] && ($tagGroups['note'] ?? []) === [] && ($tagGroups['todo'] ?? []) === [] && ($tagGroups['risk'] ?? []) === [] && ($tagGroups['other'] ?? []) === []): ?>
      <p class="meta">タグはありません。</p>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="title-row">
      <div class="title-stack">
        <h3>依存関係</h3>
        <p class="meta">このリソースの強い依存関係を設定できます。未設定でもリソースは保存できます。</p>
      </div>
    </div>

    <?php if (is_array($dependencySlotGroups['slots'] ?? null) && ($dependencySlotGroups['slots'] ?? []) !== []): ?>
      <?php foreach ($dependencySlotGroups['slots'] as $slot): ?>
        <?php
        $slotLabel = (string) ($slot['label'] ?? $slot['key'] ?? '');
        $slotItems = is_array($slot['items'] ?? null) ? $slot['items'] : [];
        ?>
        <p class="eyebrow"><?= h($slotLabel) ?></p>
        <?php if ($slotItems === []): ?>
          <p class="meta">未設定です。</p>
        <?php else: ?>
          <ul class="clean">
            <?php foreach ($slotItems as $slotItem): ?>
              <?php
              $peerId = (string) ($slotItem['peer_id'] ?? '');
              $peerName = (string) ($slotItem['peer_name'] ?? $peerId);
              $peerType = (string) ($slotItem['peer_type'] ?? '');
              $peerEnvironment = (string) ($slotItem['peer_environment'] ?? '');
              $relation = is_array($slotItem['relation'] ?? null) ? $slotItem['relation'] : [];
              ?>
              <li>
                <a class="text-link" href="/resources/<?= rawurlencode($peerId) ?>"><?= h($peerName) ?></a>
                <?php if ($peerType !== ''): ?><span class="pill"><?= h($peerType) ?></span><?php endif; ?>
                <?php if ($peerEnvironment !== ''): ?><span class="pill"><?= h($peerEnvironment) ?></span><?php endif; ?>
                <span class="meta mono"><?= h($peerId) ?></span>
                <span class="pill"><?= h((string) ($relation['type'] ?? '')) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ((string) ($slot['direction'] ?? 'outgoing') === 'outgoing'): ?>
          <div class="actions mt-2">
            <a class="secondary-button" href="/resources/<?= rawurlencode($id) ?>/dependencies/<?= rawurlencode((string) ($slot['key'] ?? '')) ?>"><?= $slotItems === [] ? '依存関係を設定' : '依存関係を変更' ?></a>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>

      <?php if (($dependencySlotGroups['other'] ?? []) !== []): ?>
        <p class="eyebrow mt-3">その他</p>
        <ul class="clean">
          <?php foreach (($dependencySlotGroups['other'] ?? []) as $dep): ?>
            <li>
              <span class="pill"><?= h((string) ($dep['type'] ?? '')) ?></span>
              <span><?= h((string) ($dep['from'] ?? '')) ?> → <?= h((string) ($dep['to'] ?? '')) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php else: ?>
      <p class="eyebrow">依存先</p>
      <?php if ($dependsOn === []): ?>
        <p class="meta">依存先はありません。</p>
      <?php else: ?>
        <ul class="clean">
          <?php foreach ($dependsOn as $dep): ?>
            <li>
              <span class="pill"><?= h((string) ($dep['type'] ?? '')) ?></span>
              <a class="text-link" href="/resources/<?= rawurlencode((string) ($dep['to'] ?? '')) ?>"><?= h((string) ($dep['to'] ?? '')) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <p class="eyebrow mt-3">被依存</p>
      <?php if ($usedBy === []): ?>
        <p class="meta">このリソースを参照する依存関係はありません。</p>
      <?php else: ?>
        <ul class="clean">
          <?php foreach ($usedBy as $dep): ?>
            <li>
              <span class="pill"><?= h((string) ($dep['type'] ?? '')) ?></span>
              <a class="text-link" href="/resources/<?= rawurlencode((string) ($dep['from'] ?? '')) ?>"><?= h((string) ($dep['from'] ?? '')) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>

    <div class="title-row mt-3">
      <div class="title-stack">
        <h3>関連タグ</h3>
      </div>
    </div>
    <?php if ($softAssociations === []): ?>
      <p class="meta">関連タグはありません。</p>
    <?php else: ?>
      <ul class="clean">
        <?php foreach ($softAssociations as $key => $value): ?>
          <li><strong><?= h((string) $key) ?></strong> = <?= h((string) $value) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <h3>設定</h3>
    </div>
  </div>
  <?php if ($spec === []): ?>
    <p class="meta">設定項目はありません。</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead><tr><th>項目</th><th>値</th></tr></thead>
        <tbody>
        <?php foreach ($spec as $key => $value): ?>
          <tr>
            <td><?= h((string) $key) ?></td>
            <td><?= h(is_array($value) ? implode(', ', array_map('strval', $value)) : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <details>
    <summary><strong>詳細設定</strong> · 技術情報を表示</summary>
    <div class="form-stack mt-2">
      <div class="field">
        <label>リソースID</label>
        <input type="text" readonly value="<?= h($id) ?>">
      </div>
      <div class="field">
        <label>ソースファイル</label>
        <input type="text" readonly value="<?= h((string) ($entity['sourcePath'] ?? '')) ?>">
      </div>
      <div class="field">
        <label>生データ</label>
        <pre><?= h(format_json($record)) ?></pre>
      </div>
    </div>
  </details>
</div>
