<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? '');
$name = (string) ($metadata['name'] ?? '');
$labels = is_array($metadata['labels'] ?? null) ? $metadata['labels'] : [];
$tags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
$sourcePath = (string) ($entity['sourcePath'] ?? '');
$formAction = $mode === 'edit' && $id !== '' ? '/entities/' . rawurlencode($id) : '/entities';
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Mutation Form</p>
      <h2><?= $mode === 'edit' ? 'Edit Entity' : 'Create Entity' ?></h2>
      <p class="meta">Submits an <code>upsert_entity</code> operation to a new change session.</p>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <p class="pill error">Error: <?= h((string) $error) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= h($formAction) ?>" class="form-stack">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label for="id">metadata.id</label>
      <input type="text" id="id" name="id" value="<?= h($id) ?>" required>
    </div>

    <div class="field">
      <label for="type">metadata.type</label>
      <input type="text" id="type" name="type" value="<?= h($type) ?>" required>
    </div>

    <div class="field">
      <label for="name">metadata.name</label>
      <input type="text" id="name" name="name" value="<?= h($name) ?>" required>
    </div>

    <div class="field">
      <label for="labels">metadata.labels (JSON)</label>
      <textarea id="labels" name="labels" rows="4"><?= h(format_json($labels)) ?></textarea>
    </div>

    <div class="field">
      <label for="tags">metadata.tags (comma separated)</label>
      <input type="text" id="tags" name="tags" value="<?= h(implode(',', $tags)) ?>">
    </div>

    <div class="field">
      <label for="spec">spec (JSON)</label>
      <textarea id="spec" name="spec" rows="8"><?= h(format_json($spec)) ?></textarea>
    </div>

    <div class="field">
      <label for="sourcePath">Source path under registry/entities (optional)</label>
      <input type="text" id="sourcePath" name="sourcePath" value="<?= h($sourcePath) ?>" placeholder="entities/entity-example-entity.yaml">
    </div>

    <div class="field">
      <label for="actor">Actor</label>
      <input type="text" id="actor" name="actor" value="human-ui">
    </div>

    <div class="field">
      <label for="actorType">Actor Type</label>
      <select id="actorType" name="actorType">
        <option value="human">human</option>
        <option value="agent">agent</option>
        <option value="cli">cli</option>
        <option value="unknown">unknown</option>
      </select>
    </div>

    <div class="actions">
      <button type="submit" class="primary-button">Create Change Session</button>
      <a class="secondary-button" href="/entities">Cancel</a>
    </div>
  </form>
</div>
