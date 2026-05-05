<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Registry Links</p>
      <h2>Relations</h2>
      <p class="meta">Typed links that connect entities through audited change sessions.</p>
    </div>
    <div class="actions">
      <a class="primary-button" href="/relations/new">Create Relation</a>
      <a class="secondary-button" href="/graph">Open Graph</a>
    </div>
  </div>

  <?php if ($relations === []): ?>
    <p class="empty-state">No relations found under <code>registry/relations</code>.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
        <tr><th>ID</th><th>Type</th><th>From</th><th>To</th><th>Source Path</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($relations as $relation): ?>
          <tr>
            <td><span class="mono"><?= h((string) $relation['id']) ?></span></td>
            <td><span class="pill"><?= h((string) $relation['type']) ?></span></td>
            <td><?= h((string) $relation['from']) ?></td>
            <td><?= h((string) $relation['to']) ?></td>
            <td><span class="mono"><?= h((string) $relation['sourcePath']) ?></span></td>
            <td><a class="text-link" href="/relations/<?= rawurlencode((string) $relation['id']) ?>/edit">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
