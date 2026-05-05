<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Registry Health</p>
      <h2>Registry Validation</h2>
    </div>
    <div>
      <?php if (!empty($result['valid'])): ?>
        <span class="pill ok">Valid</span>
      <?php else: ?>
        <span class="pill error">Invalid</span>
      <?php endif; ?>
    </div>
  </div>

  <p class="meta">Ran at: <?= h((string) ($result['ranAt'] ?? '')) ?></p>

  <div class="split mt-3">
    <section class="panel soft">
      <div class="title-row">
        <div class="title-stack">
          <p class="eyebrow">Errors</p>
          <h3>Blocking Issues</h3>
        </div>
      </div>
      <?php if (empty($result['errors'])): ?>
        <p class="meta">No errors.</p>
      <?php else: ?>
        <ul class="clean">
          <?php foreach ($result['errors'] as $error): ?>
            <li class="error"><?= h((string) ($error['message'] ?? 'unknown error')) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel soft">
      <div class="title-row">
        <div class="title-stack">
          <p class="eyebrow">Warnings</p>
          <h3>Advisory Findings</h3>
        </div>
      </div>
      <?php if (empty($result['warnings'])): ?>
        <p class="meta">No warnings.</p>
      <?php else: ?>
        <ul class="clean">
          <?php foreach ($result['warnings'] as $warning): ?>
            <li><?= h((string) ($warning['message'] ?? 'warning')) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
