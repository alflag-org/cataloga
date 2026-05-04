<div class="card">
  <h2>Registry Validation</h2>
  <p>
    <?php if (!empty($result['valid'])): ?>
      <span class="ok">Registry is valid.</span>
    <?php else: ?>
      <span class="error">Registry has validation errors.</span>
    <?php endif; ?>
  </p>
  <p class="meta">Ran at: <?= h((string) ($result['ranAt'] ?? '')) ?></p>

  <h3>Errors</h3>
  <?php if (empty($result['errors'])): ?>
    <p class="meta">No errors.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($result['errors'] as $error): ?>
        <li class="error"><?= h((string) ($error['message'] ?? 'unknown error')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h3>Warnings</h3>
  <?php if (empty($result['warnings'])): ?>
    <p class="meta">No warnings.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($result['warnings'] as $warning): ?>
        <li><?= h((string) ($warning['message'] ?? 'warning')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
