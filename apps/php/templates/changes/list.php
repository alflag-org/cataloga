<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Session Queue</p>
      <h2>Change Sessions</h2>
      <p class="meta">Every write operation is staged here before commit.</p>
    </div>
    <div class="actions">
      <a class="secondary-button" href="/graph">Open Graph</a>
    </div>
  </div>

  <?php if ($changes === []): ?>
    <p class="empty-state">No change sessions found.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
        <tr><th>ID</th><th>Status</th><th>Actor</th><th>Operations</th><th>Updated</th></tr>
        </thead>
        <tbody>
        <?php foreach ($changes as $change): ?>
          <?php
          $operations = is_array($change['operations'] ?? null) ? $change['operations'] : [];
          $status = (string) ($change['status'] ?? 'open');
          ?>
          <tr>
            <td><a class="text-link" href="/changes/<?= rawurlencode((string) $change['id']) ?>"><?= h((string) $change['id']) ?></a></td>
            <td><span class="pill <?= $status === 'committed' ? 'ok' : ($status === 'aborted' ? 'error' : 'warn') ?>"><?= h($status) ?></span></td>
            <td><?= h((string) ($change['actor'] ?? 'unknown')) ?> (<?= h((string) ($change['actorType'] ?? 'unknown')) ?>)</td>
            <td><?= h((string) count($operations)) ?></td>
            <td><?= h((string) ($change['updatedAt'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
