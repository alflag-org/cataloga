<?php
$record = is_array($relation['record'] ?? null) ? $relation['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? '');
$name = (string) ($metadata['name'] ?? '');
$from = (string) ($spec['from'] ?? '');
$to = (string) ($spec['to'] ?? '');
$attributes = is_array($spec['attributes'] ?? null) ? $spec['attributes'] : [];
$sourcePath = (string) ($relation['sourcePath'] ?? '');
$formAction = $mode === 'edit' && $id !== '' ? '/relations/' . rawurlencode($id) : '/relations';
?>
<div class="panel">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">Mutation Form</p>
      <h2><?= $mode === 'edit' ? 'Edit Relation' : 'Create Relation' ?></h2>
      <p class="meta">Submits an <code>upsert_relation</code> operation to a new change session.</p>
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
      <label for="from">spec.from</label>
      <input type="text" id="from" name="from" value="<?= h($from) ?>" required>
    </div>

    <div class="field">
      <label for="to">spec.to</label>
      <input type="text" id="to" name="to" value="<?= h($to) ?>" required>
    </div>

    <div class="field">
      <label for="attributes">spec.attributes (JSON)</label>
      <textarea id="attributes" name="attributes" rows="6"><?= h(format_json($attributes)) ?></textarea>
    </div>

    <div class="field">
      <label for="sourcePath">Source path under registry/relations (optional)</label>
      <input type="text" id="sourcePath" name="sourcePath" value="<?= h($sourcePath) ?>" placeholder="relations/relation-example.yaml">
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
      <a class="secondary-button" href="/relations">Cancel</a>
    </div>
  </form>
</div>
