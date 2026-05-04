<div class="card">
  <h2>Relations</h2>
  <?php if ($relations === []): ?>
    <p class="meta">No relations found under <code>registry/relations</code>.</p>
  <?php else: ?>
    <table>
      <thead>
      <tr><th>ID</th><th>Type</th><th>From</th><th>To</th><th>Source Path</th></tr>
      </thead>
      <tbody>
      <?php foreach ($relations as $relation): ?>
        <tr>
          <td><?= h((string) $relation['id']) ?></td>
          <td><?= h((string) $relation['type']) ?></td>
          <td><?= h((string) $relation['from']) ?></td>
          <td><?= h((string) $relation['to']) ?></td>
          <td><code><?= h((string) $relation['sourcePath']) ?></code></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
