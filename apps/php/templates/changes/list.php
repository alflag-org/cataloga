<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">変更</p>
      <h2>ドラフト変更の一覧</h2>
      <p class="meta">すべての書き込みはここで確認してから保存します。</p>
    </div>
  </div>

  <?php if ($changes === []): ?>
    <p class="empty-state">変更はまだありません。</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
        <tr><th>概要</th><th>状態</th><th>更新日時</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($changes as $change): ?>
          <?php
          $operations = is_array($change['operations'] ?? null) ? $change['operations'] : [];
          $status = (string) ($change['status'] ?? 'open');
          $statusLabel = ui_change_status_label($status);
          $statusClass = ui_change_status_class($status);
          ?>
          <tr>
            <td><?= h((count($operations) > 0 ? (string) count($operations) . ' 件の操作' : 'ドラフト変更')) ?></td>
            <td><span class="pill <?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
            <td><?= h((string) ($change['updatedAt'] ?? '')) ?></td>
            <td><a class="text-link" href="/changes/<?= rawurlencode((string) $change['id']) ?>">確認</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
