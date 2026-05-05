<div class="panel soft">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Workspace Status</p>
      <h2>Registry Overview</h2>
      <p class="meta">Graph-first navigation for entities, relations, and staged changes.</p>
    </div>
    <div class="actions">
      <a class="primary-button" href="/graph">Open Graph</a>
      <a class="secondary-button" href="/entities">Open Entities</a>
      <a class="secondary-button" href="/changes">Open Changes</a>
    </div>
  </div>

  <div class="metrics">
    <article class="metric-card">
      <span>Entities</span>
      <strong><?= h((string) $entityCount) ?></strong>
      <p>Canonical records under <code>registry/entities</code>.</p>
    </article>
    <article class="metric-card">
      <span>Relations</span>
      <strong><?= h((string) $relationCount) ?></strong>
      <p>Links under <code>registry/relations</code>.</p>
    </article>
    <article class="metric-card">
      <span>Domain Packs</span>
      <strong><?= h((string) $domainPackCount) ?></strong>
      <p>Available pack definitions.</p>
    </article>
    <article class="metric-card">
      <span>Open Changes</span>
      <strong><?= h((string) $openChangeCount) ?></strong>
      <p>Change sessions not committed or aborted.</p>
    </article>
  </div>

  <div class="actions">
    <a class="secondary-button" href="/graph">Inspect Topology</a>
    <a class="secondary-button" href="/validation">Run Validation</a>
    <a class="secondary-button" href="/git/diff">Inspect Git Diff</a>
    <a class="secondary-button" href="/domain-packs">Browse Domain Packs</a>
  </div>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Audit Trail</p>
      <h2>Recent Change Sessions</h2>
    </div>
  </div>

  <?php if ($changes === []): ?>
    <p class="empty-state">No change sessions yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
        <tr><th>ID</th><th>Status</th><th>Actor</th><th>Updated</th></tr>
        </thead>
        <tbody>
        <?php foreach ($changes as $change): ?>
          <?php $status = (string) ($change['status'] ?? 'open'); ?>
          <tr>
            <td><a class="text-link" href="/changes/<?= rawurlencode((string) $change['id']) ?>"><?= h((string) $change['id']) ?></a></td>
            <td>
              <span class="pill <?= $status === 'committed' ? 'ok' : ($status === 'aborted' ? 'error' : 'warn') ?>"><?= h($status) ?></span>
            </td>
            <td><?= h((string) ($change['actor'] ?? 'unknown')) ?> (<?= h((string) ($change['actorType'] ?? 'unknown')) ?>)</td>
            <td><?= h((string) ($change['updatedAt'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Repository</p>
      <h2>Git Status</h2>
    </div>
  </div>
  <?php if (($gitStatus['ok'] ?? false) === true): ?>
    <pre><?= h((string) ($gitStatus['stdout'] ?? 'clean')) ?></pre>
  <?php else: ?>
    <p class="error">Git unavailable: <?= h((string) ($gitStatus['stderr'] ?? 'unknown error')) ?></p>
  <?php endif; ?>
</div>
