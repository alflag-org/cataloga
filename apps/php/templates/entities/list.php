<div class="card">
  <div class="buttons" style="justify-content: space-between;">
    <h2>Entities</h2>
    <a class="button-link" href="/entities/new">Create Entity</a>
  </div>
  <?php if ($entities === []): ?>
    <p class="meta">No entities found under <code>registry/entities</code>.</p>
  <?php else: ?>
    <table>
      <thead>
      <tr><th>ID</th><th>Type</th><th>Name</th><th>Source Path</th></tr>
      </thead>
      <tbody>
      <?php foreach ($entities as $entity): ?>
        <tr>
          <td><a href="/entities/<?= rawurlencode((string) $entity['id']) ?>"><?= h((string) $entity['id']) ?></a></td>
          <td><?= h((string) $entity['type']) ?></td>
          <td><?= h((string) $entity['name']) ?></td>
          <td><code><?= h((string) $entity['sourcePath']) ?></code></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
