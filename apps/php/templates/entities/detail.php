<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? 'unknown');
?>
<div class="panel soft">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Entity Detail</p>
      <h2><?= h($id) ?></h2>
      <p class="meta">Source file: <span class="mono"><?= h((string) ($entity['sourcePath'] ?? '')) ?></span></p>
    </div>
    <div class="actions">
      <span class="pill"><?= h($type) ?></span>
      <a class="primary-button" href="/entities/<?= rawurlencode($id) ?>/edit">Edit via Change Session</a>
    </div>
  </div>
</div>

<div class="split">
  <section class="panel">
    <div class="title-row">
      <div class="title-stack">
        <p class="eyebrow">Metadata</p>
        <h3>Record Metadata</h3>
      </div>
    </div>
    <pre><?= h(format_json($metadata)) ?></pre>
  </section>

  <section class="panel">
    <div class="title-row">
      <div class="title-stack">
        <p class="eyebrow">Spec</p>
        <h3>Record Specification</h3>
      </div>
    </div>
    <pre><?= h(format_json($spec)) ?></pre>
  </section>
</div>
