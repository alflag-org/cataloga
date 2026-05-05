<?php
$record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
$metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
$spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
$id = (string) ($metadata['id'] ?? '');
$type = (string) ($metadata['type'] ?? $selectedSchemaId ?? '');
$name = (string) ($metadata['name'] ?? '');
$labels = is_array($metadata['labels'] ?? null) ? $metadata['labels'] : [];
$tags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
$sourcePath = (string) ($entity['sourcePath'] ?? '');
$formAction = $mode === 'edit' && $id !== '' ? '/entities/' . rawurlencode($id) : '/entities';
$advancedMode = false;
$selectedSchema = null;
foreach (($schemas ?? []) as $schema) { if (($schema['id'] ?? '') === $type) { $selectedSchema = $schema; break; } }
?>
<div class="panel"><h2><?= $mode === 'edit' ? 'Edit Entity' : 'Create Entity' ?></h2>
<?php if (!empty($error)): ?><p class="pill error">Error: <?= h((string) $error) ?></p><?php endif; ?>
<form method="get" action="/entities/new" class="form-stack"><div class="field"><label>Entity type</label><select name="schema" onchange="this.form.submit()"><option value="">Select schema…</option><?php foreach (($schemas ?? []) as $schema): ?><option value="<?= h((string)$schema['id']) ?>" <?= ((string)$schema['id'] === $type) ? 'selected' : '' ?>><?= h((string)$schema['id']) ?> — <?= h((string)$schema['name'] ?? '') ?></option><?php endforeach; ?></select></div></form>
<form method="post" action="<?= h($formAction) ?>" class="form-stack">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="type" value="<?= h($type) ?>">
<div class="field"><label>metadata.type</label><input type="text" value="<?= h($type) ?>" readonly></div>
<div class="field"><label for="name">metadata.name</label><input required id="name" name="name" value="<?= h($name) ?>"></div>
<div class="field"><label for="id">metadata.id (optional, auto-generated)</label><input id="id" name="id" value="<?= h($id) ?>"></div>
<?php if ($selectedSchema): foreach (($selectedSchema['properties'] ?? []) as $field => $def): $ft=(string)($def['type'] ?? 'string'); $val=$spec[$field] ?? ''; ?>
<div class="field"><label><?= h($field) ?></label>
<?php if (($def['format'] ?? '') === 'entity_ref'): ?><select name="schema_fields[<?= h($field) ?>]"><option value="">Select entity…</option><?php foreach (($entities ?? []) as $ent): ?><option value="<?= h((string)$ent['id']) ?>" <?= ((string)$val===(string)$ent['id'])?'selected':'' ?>><?= h((string)$ent['id']) ?></option><?php endforeach; ?></select>
<?php elseif (!empty($def['enum']) && is_array($def['enum'])): ?><select name="schema_fields[<?= h($field) ?>]"><?php foreach ($def['enum'] as $opt): ?><option value="<?= h((string)$opt) ?>" <?= ((string)$val===(string)$opt)?'selected':'' ?>><?= h((string)$opt) ?></option><?php endforeach; ?></select>
<?php elseif ($ft==='boolean'): ?><select name="schema_fields[<?= h($field) ?>]"><option value="false" <?= $val===false?'selected':'' ?>>false</option><option value="true" <?= $val===true?'selected':'' ?>>true</option></select>
<?php elseif ($ft==='array'): ?><input name="schema_fields[<?= h($field) ?>][]" value="<?= h(is_array($val)?implode(',', $val):(string)$val) ?>" placeholder="comma values">
<?php elseif ($ft==='text'): ?><textarea name="schema_fields[<?= h($field) ?>]" rows="3"><?= h((string)$val) ?></textarea>
<?php else: ?><input name="schema_fields[<?= h($field) ?>]" value="<?= h((string)$val) ?>"><?php endif; ?></div>
<?php endforeach; endif; ?>
<details><summary>Advanced mode</summary>
<div class="field"><label><input type="checkbox" name="advancedMode" value="1"> Edit raw spec JSON</label></div>
<div class="field"><label for="spec">spec (JSON)</label><textarea id="spec" name="spec" rows="8"><?= h(format_json($spec)) ?></textarea></div>
<div class="field"><label for="sourcePath">Source path override</label><input id="sourcePath" name="sourcePath" value="<?= h($sourcePath) ?>"></div>
</details>
<div class="actions"><button type="submit" class="primary-button">Create Change Session</button></div></form></div>
