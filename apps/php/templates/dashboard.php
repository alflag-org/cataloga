<div class="card">
  <h2>Registry Status</h2>
  <p class="meta">AI-native, Git/file-backed, domain-agnostic registry runtime.</p>
  <div class="grid">
    <div>
      <h3>Entities</h3>
      <p><span class="pill"><?= h((string) $entityCount) ?></span></p>
      <a class="button-link" href="/entities">Open Entity List</a>
    </div>
    <div>
      <h3>Change Sessions</h3>
      <p><a href="/changes">Open Change List</a></p>
      <p><a href="/validation">Run Validation</a></p>
      <p><a href="/git/diff">Inspect Git Diff</a></p>
    </div>
  </div>
</div>

<div class="card">
  <h2>Recent Changes</h2>
  <?php if ($changes === []): ?>
    <p class="meta">No change sessions yet.</p>
  <?php else: ?>
    <table>
      <thead>
      <tr><th>ID</th><th>Status</th><th>Actor</th><th>Updated</th></tr>
      </thead>
      <tbody>
      <?php foreach ($changes as $change): ?>
        <tr>
          <td><a href="/changes/<?= rawurlencode((string) $change['id']) ?>"><?= h((string) $change['id']) ?></a></td>
          <td><?= h((string) $change['status']) ?></td>
          <td><?= h((string) ($change['actor'] ?? 'unknown')) ?> (<?= h((string) ($change['actorType'] ?? 'unknown')) ?>)</td>
          <td><?= h((string) ($change['updatedAt'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Git Status</h2>
  <?php if (($gitStatus['ok'] ?? false) === true): ?>
    <pre><?= h((string) ($gitStatus['stdout'] ?? 'clean')) ?></pre>
  <?php else: ?>
    <p class="error">Git unavailable: <?= h((string) ($gitStatus['stderr'] ?? 'unknown error')) ?></p>
  <?php endif; ?>
</div>
