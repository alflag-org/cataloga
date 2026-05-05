<?php
$installed = [];
$available = [];
foreach (($packs ?? []) as $pack) {
  if (($pack['installed'] ?? false) === true) {
    $installed[] = $pack;
  } else {
    $available[] = $pack;
  }
}
$impactByPack = is_array($impactByPack ?? null) ? $impactByPack : [];
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">タイプパック</p>
      <h2>インストール可能な拡張</h2>
      <p class="meta">タイプパックはリソース種別、依存関係種別、検証ルールを追加します。</p>
    </div>
  </div>

  <h3>インストール済み</h3>
  <?php if ($installed === []): ?>
    <div class="empty-state mt-2">インストール済みのタイプパックはありません。</div>
  <?php else: ?>
    <div class="table-shell mt-2">
      <table>
        <thead><tr><th>名前</th><th>説明</th><th>バージョン</th><th>状態</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($installed as $pack): ?>
          <?php
          $packId = (string) ($pack['id'] ?? '');
          $enabled = (bool) ($pack['enabled'] ?? false);
          $impact = is_array($impactByPack[$packId] ?? null) ? $impactByPack[$packId] : ['affectedResources' => [], 'affectedDependencies' => []];
          $affectedResourceCount = array_sum(array_map('intval', $impact['affectedResources'] ?? []));
          $affectedDependencyCount = array_sum(array_map('intval', $impact['affectedDependencies'] ?? []));
          ?>
          <tr>
            <td><?= h((string) ($pack['name'] ?? $packId)) ?></td>
            <td><?= h((string) ($pack['description'] ?? '')) ?></td>
            <td><?= h((string) ($pack['version'] ?? '')) ?></td>
            <td><span class="pill <?= $enabled ? 'ok' : 'warn' ?>"><?= $enabled ? '有効' : '無効' ?></span></td>
            <td>
              <div class="actions">
                <?php if ($enabled): ?>
                  <form method="post" action="/type-packs/<?= rawurlencode($packId) ?>/disable" onsubmit="return confirm('タイプパック <?= h($packId) ?> を無効化しますか？ 影響: リソース <?= h((string) $affectedResourceCount) ?> 件, 依存関係 <?= h((string) $affectedDependencyCount) ?> 件');">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <button type="submit" class="secondary-button">タイプパックを無効化</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="/type-packs/<?= rawurlencode($packId) ?>/enable">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <button type="submit" class="secondary-button">タイプパックを有効化</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="/type-packs/<?= rawurlencode($packId) ?>/uninstall" onsubmit="return confirm('タイプパック <?= h($packId) ?> をアンインストールしますか？');">
                  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                  <button type="submit" class="danger-button">アンインストール</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h3 class="mt-3">利用可能</h3>
  <?php if ($available === []): ?>
    <div class="empty-state mt-2">追加で利用可能なタイプパックはありません。</div>
  <?php else: ?>
    <div class="table-shell mt-2">
      <table>
        <thead><tr><th>名前</th><th>説明</th><th>バージョン</th><th>リソース種別</th><th>依存関係種別</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($available as $pack): ?>
          <?php
          $resourceTypes = is_array($pack['resourceTypes'] ?? null) ? $pack['resourceTypes'] : [];
          $dependencyTypes = is_array($pack['dependencyTypes'] ?? null) ? $pack['dependencyTypes'] : [];
          ?>
          <tr>
            <td><?= h((string) ($pack['name'] ?? '')) ?></td>
            <td><?= h((string) ($pack['description'] ?? '')) ?></td>
            <td><?= h((string) ($pack['version'] ?? '')) ?></td>
            <td><?= h((string) count($resourceTypes)) ?></td>
            <td><?= h((string) count($dependencyTypes)) ?></td>
            <td>
              <form method="post" action="/type-packs/install">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="name" value="<?= h((string) ($pack['id'] ?? '')) ?>">
                <button type="submit" class="primary-button">タイプパックをインストール</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
