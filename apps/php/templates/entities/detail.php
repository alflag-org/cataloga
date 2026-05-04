<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
?>
<div class="card">
  <div class="buttons" style="justify-content: space-between;">
    <h2><?= h($id) ?></h2>
    <a class="button-link" href="/entities/<?= rawurlencode($id) ?>/edit">Edit via Change Session</a>
  </div>
  <p class="meta">Source file: <code><?= h((string) ($entity['sourcePath'] ?? '')) ?></code></p>
  <h3>Metadata</h3>
  <pre><?= h(format_json($metadata)) ?></pre>
  <h3>Spec</h3>
  <pre><?= h(format_json($spec)) ?></pre>
</div>
