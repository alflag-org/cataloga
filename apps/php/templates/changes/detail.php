<?php
$validation = is_array($change['validation'] ?? null) ? $change['validation'] : ['valid' => false, 'errors' => [], 'warnings' => []];
$operations = is_array($change['operations'] ?? null) ? $change['operations'] : [];
$diffItems = is_array($diff['items'] ?? null) ? $diff['items'] : [];
?>
<div class="card">
  <h2>Change Session <?= h((string) $change['id']) ?></h2>
  <p class="meta">Status: <strong><?= h((string) ($change['status'] ?? 'open')) ?></strong> | Actor: <?= h((string) ($change['actor'] ?? 'unknown')) ?> (<?= h((string) ($change['actorType'] ?? 'unknown')) ?>)</p>
  <p class="meta">Created: <?= h((string) ($change['createdAt'] ?? '')) ?> | Updated: <?= h((string) ($change['updatedAt'] ?? '')) ?></p>
  <?php if (!empty($change['commitHash'])): ?>
    <p class="ok">Commit Hash: <code><?= h((string) $change['commitHash']) ?></code></p>
  <?php endif; ?>
  <?php if (is_array($change['git'] ?? null) && !empty($change['git']['message'])): ?>
    <p class="error">Git warning: <?= h((string) $change['git']['message']) ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Operations</h2>
  <pre><?= h(format_json($operations)) ?></pre>
</div>

<div class="card">
  <h2>Validation</h2>
  <p>
    <?php if (!empty($validation['valid'])): ?>
      <span class="ok">Valid</span>
    <?php else: ?>
      <span class="error">Invalid</span>
    <?php endif; ?>
  </p>
  <p class="meta">Ran at: <?= h((string) ($validation['ranAt'] ?? 'not yet')) ?></p>

  <?php if (!empty($validation['errors'])): ?>
    <h3>Errors</h3>
    <ul>
      <?php foreach ($validation['errors'] as $error): ?>
        <li class="error"><?= h((string) ($error['message'] ?? 'unknown error')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!empty($validation['warnings'])): ?>
    <h3>Warnings</h3>
    <ul>
      <?php foreach ($validation['warnings'] as $warning): ?>
        <li><?= h((string) ($warning['message'] ?? 'warning')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="buttons">
    <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/validate">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <button type="submit" class="secondary">Run Validation</button>
    </form>
  </div>
</div>

<div class="card">
  <h2>Diff Preview</h2>
  <?php if ($diffItems === []): ?>
    <p class="meta">No pending file changes.</p>
  <?php else: ?>
    <?php foreach ($diffItems as $item): ?>
      <h3><?= h((string) $item['status']) ?>: <code><?= h((string) $item['path']) ?></code></h3>
      <div class="grid">
        <div>
          <p class="meta">Before</p>
          <pre><?= h((string) ($item['before'] ?? '')) ?></pre>
        </div>
        <div>
          <p class="meta">After</p>
          <pre><?= h((string) ($item['after'] ?? '')) ?></pre>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Finalize</h2>
  <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/commit">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label for="commitMessage">Git commit message</label>
    <input type="text" name="commitMessage" id="commitMessage" placeholder="Cataloga change <?= h((string) $change['id']) ?>">

    <label>
      <input type="hidden" name="createGitCommit" value="0">
      <input type="checkbox" name="createGitCommit" value="1" checked>
      Create Git commit when available
    </label>

    <div class="buttons">
      <button type="submit">Commit Change</button>
    </div>
  </form>

  <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/abort">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="buttons">
      <button type="submit" class="danger">Abort Change</button>
    </div>
  </form>
</div>
