<?php
$validation = is_array($change['validation'] ?? null) ? $change['validation'] : ['valid' => false, 'errors' => [], 'warnings' => []];
$operations = is_array($change['operations'] ?? null) ? $change['operations'] : [];
$diffItems = is_array($diff['items'] ?? null) ? $diff['items'] : [];
$status = (string) ($change['status'] ?? 'open');
?>
<div class="panel soft">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Change Session</p>
      <h2><?= h((string) $change['id']) ?></h2>
      <p class="meta">Actor: <?= h((string) ($change['actor'] ?? 'unknown')) ?> (<?= h((string) ($change['actorType'] ?? 'unknown')) ?>)</p>
      <p class="meta">Created: <?= h((string) ($change['createdAt'] ?? '')) ?> | Updated: <?= h((string) ($change['updatedAt'] ?? '')) ?></p>
    </div>
    <div class="actions">
      <span class="pill <?= $status === 'committed' ? 'ok' : ($status === 'aborted' ? 'error' : 'warn') ?>"><?= h($status) ?></span>
      <?php if (!empty($change['commitHash'])): ?>
        <span class="pill ok">Commit: <?= h((string) $change['commitHash']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (is_array($change['git'] ?? null) && !empty($change['git']['message'])): ?>
    <p class="pill warn">Git warning: <?= h((string) $change['git']['message']) ?></p>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Operation Stack</p>
      <h2>Operations</h2>
    </div>
  </div>
  <pre><?= h(format_json($operations)) ?></pre>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Validation</p>
      <h2>Pre-commit Checks</h2>
      <p class="meta">Ran at: <?= h((string) ($validation['ranAt'] ?? 'not yet')) ?></p>
    </div>
    <div>
      <?php if (!empty($validation['valid'])): ?>
        <span class="pill ok">Valid</span>
      <?php else: ?>
        <span class="pill error">Invalid</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($validation['errors'])): ?>
    <h3>Errors</h3>
    <ul class="clean">
      <?php foreach ($validation['errors'] as $error): ?>
        <li class="error"><?= h((string) ($error['message'] ?? 'unknown error')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!empty($validation['warnings'])): ?>
    <h3 class="mt-3">Warnings</h3>
    <ul class="clean">
      <?php foreach ($validation['warnings'] as $warning): ?>
        <li><?= h((string) ($warning['message'] ?? 'warning')) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="actions">
    <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/validate">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <button type="submit" class="secondary-button">Run Validation</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Preview</p>
      <h2>Diff Preview</h2>
    </div>
  </div>

  <?php if ($diffItems === []): ?>
    <p class="empty-state">No pending file changes.</p>
  <?php else: ?>
    <?php foreach ($diffItems as $item): ?>
      <section class="panel soft mt-2">
        <div class="title-row">
          <div class="title-stack">
            <h3><?= h((string) $item['status']) ?>: <span class="mono"><?= h((string) $item['path']) ?></span></h3>
          </div>
        </div>
        <div class="split">
          <div>
            <p class="meta">Before</p>
            <pre><?= h((string) ($item['before'] ?? '')) ?></pre>
          </div>
          <div>
            <p class="meta">After</p>
            <pre><?= h((string) ($item['after'] ?? '')) ?></pre>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Finalize</p>
      <h2>Commit or Abort</h2>
    </div>
  </div>

  <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/commit" class="form-stack">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label for="commitMessage">Git commit message</label>
      <input type="text" name="commitMessage" id="commitMessage" placeholder="Cataloga change <?= h((string) $change['id']) ?>">
    </div>

    <div class="field inline">
      <input class="checkbox" type="hidden" name="createGitCommit" value="0">
      <input class="checkbox" type="checkbox" name="createGitCommit" value="1" id="createGitCommit" checked>
      <label for="createGitCommit">Create Git commit when available</label>
    </div>

    <div class="actions">
      <button type="submit" class="primary-button">Commit Change</button>
    </div>
  </form>

  <form method="post" action="/changes/<?= rawurlencode((string) $change['id']) ?>/abort" class="mt-3">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="actions">
      <button type="submit" class="danger-button">Abort Change</button>
    </div>
  </form>
</div>
