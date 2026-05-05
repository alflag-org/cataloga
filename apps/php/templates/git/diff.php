<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Repository Delta</p>
      <h2>Git Diff (registry)</h2>
      <p class="meta">Current unstaged/staged differences under tracked registry files.</p>
    </div>
  </div>
  <?php if (($diff['ok'] ?? false) === true): ?>
    <pre><?= h((string) ($diff['stdout'] ?? 'No diff')) ?></pre>
  <?php else: ?>
    <p class="pill error">Git diff failed: <?= h((string) ($diff['stderr'] ?? 'unknown error')) ?></p>
    <pre><?= h((string) ($diff['stdout'] ?? '')) ?></pre>
  <?php endif; ?>
</div>
