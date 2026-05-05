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
<div class="card">
  <h2><?= $mode === 'edit' ? 'Edit Relation' : 'Create Relation' ?></h2>
  <?php if (!empty($error)): ?><p class="error"><?= h((string) $error) ?></p><?php endif; ?>
  <form method="post" action="<?= h($formAction) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label>metadata.id</label><input type="text" name="id" value="<?= h($id) ?>" required>
    <label>metadata.type</label><input type="text" name="type" value="<?= h($type) ?>" required>
    <label>metadata.name</label><input type="text" name="name" value="<?= h($name) ?>" required>
    <label>spec.from</label><input type="text" name="from" value="<?= h($from) ?>" required>
    <label>spec.to</label><input type="text" name="to" value="<?= h($to) ?>" required>
    <label>spec.attributes JSON</label><textarea name="attributes" rows="6"><?= h(format_json($attributes)) ?></textarea>
    <label>sourcePath</label><input type="text" name="sourcePath" value="<?= h($sourcePath) ?>" placeholder="relations/relation-example.yaml">
    <label>Actor</label><input type="text" name="actor" value="human-ui">
    <label>Actor Type</label>
    <select name="actorType"><option value="human">human</option><option value="agent">agent</option><option value="cli">cli</option><option value="unknown">unknown</option></select>
    <div class="buttons"><button type="submit">Create Change Session</button><a class="button-link secondary" href="/relations">Cancel</a></div>
  </form>
</div>
