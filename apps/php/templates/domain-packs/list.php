<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Domain Extension</p>
      <h2>Domain Packs</h2>
      <p class="meta">Domain semantics are isolated in packs while core remains generic.</p>
    </div>
  </div>

  <?php if ($packs === []): ?>
    <p class="empty-state">No domain packs found.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead><tr><th>ID</th><th>Name</th><th>Version</th><th>Source</th></tr></thead>
        <tbody>
        <?php foreach ($packs as $pack): ?>
          <tr>
            <td><span class="mono"><?= h((string) $pack['id']) ?></span></td>
            <td><?= h((string) $pack['name']) ?></td>
            <td><span class="pill"><?= h((string) $pack['version']) ?></span></td>
            <td><span class="mono"><?= h((string) $pack['sourcePath']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
