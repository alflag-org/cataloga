<div class="card">
  <h2>Domain Packs</h2>
  <?php if ($packs === []): ?>
    <p class="meta">No domain packs found.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Version</th><th>Source</th></tr></thead>
      <tbody>
      <?php foreach ($packs as $pack): ?>
        <tr>
          <td><?= h((string) $pack['id']) ?></td>
          <td><?= h((string) $pack['name']) ?></td>
          <td><?= h((string) $pack['version']) ?></td>
          <td><code><?= h((string) $pack['sourcePath']) ?></code></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
