<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Registry Records</p>
      <h2>Entities</h2>
      <p class="meta">Canonical records staged and committed through change sessions.</p>
    </div>
    <div class="actions">
      <a class="primary-button" href="/entities/new">Create Entity</a>
      <a class="secondary-button" href="/graph">Open Graph</a>
    </div>
  </div>

  <?php if ($entities === []): ?>
    <p class="empty-state">No entities found under <code>registry/entities</code>.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
        <tr><th>ID</th><th>Type</th><th>Name</th><th>Source Path</th></tr>
        </thead>
        <tbody>
        <?php foreach ($entities as $entity): ?>
          <tr>
            <td><a class="text-link" href="/entities/<?= rawurlencode((string) $entity['id']) ?>"><?= h((string) $entity['id']) ?></a></td>
            <td><span class="pill"><?= h((string) $entity['type']) ?></span></td>
            <td><?= h((string) $entity['name']) ?></td>
            <td><span class="mono"><?= h((string) $entity['sourcePath']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
