<div class="card">
  <h2>Change Sessions</h2>
  <?php if ($changes === []): ?>
    <p class="meta">No change sessions found.</p>
  <?php else: ?>
    <table>
      <thead>
      <tr><th>ID</th><th>Status</th><th>Actor</th><th>Operations</th><th>Updated</th></tr>
      </thead>
      <tbody>
      <?php foreach ($changes as $change): ?>
        <?php $operations = is_array($change['operations'] ?? null) ? $change['operations'] : []; ?>
        <tr>
          <td><a href="/changes/<?= rawurlencode((string) $change['id']) ?>"><?= h((string) $change['id']) ?></a></td>
          <td><?= h((string) ($change['status'] ?? 'open')) ?></td>
          <td><?= h((string) ($change['actor'] ?? 'unknown')) ?> (<?= h((string) ($change['actorType'] ?? 'unknown')) ?>)</td>
          <td><?= h((string) count($operations)) ?></td>
          <td><?= h((string) ($change['updatedAt'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
