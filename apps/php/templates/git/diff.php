<div class="card">
  <h2>Git Diff (registry)</h2>
  <?php if (($diff['ok'] ?? false) === true): ?>
    <pre><?= h((string) ($diff['stdout'] ?? 'No diff')) ?></pre>
  <?php else: ?>
    <p class="error">Git diff failed: <?= h((string) ($diff['stderr'] ?? 'unknown error')) ?></p>
    <pre><?= h((string) ($diff['stdout'] ?? '')) ?></pre>
  <?php endif; ?>
</div>
