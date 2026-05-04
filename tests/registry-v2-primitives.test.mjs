import test from 'node:test';
import assert from 'node:assert/strict';
import {
  CHANGE_LIFECYCLE,
  createChangeSession
} from '../packages/registry/dist/index.js';
import {
  DEFAULT_MCP_RESOURCES,
  DEFAULT_MCP_TOOLS,
  MUTATION_TOOL_NAMES
} from '../packages/mcp/dist/mcp/src/index.js';

test('registry v2 exposes canonical change lifecycle', () => {
  assert.deepEqual(CHANGE_LIFECYCLE, [
    'start_change',
    'apply_mutation',
    'validate_change',
    'show_diff',
    'commit_change',
    'abort_change'
  ]);
  assert.deepEqual(MUTATION_TOOL_NAMES, CHANGE_LIFECYCLE);
});

test('registry v2 can initialize a change session', () => {
  const session = createChangeSession({
    id: 'chg_01',
    actor: 'tester',
    now: '2026-05-04T00:00:00Z',
    baseRevision: 'abc123'
  });

  assert.equal(session.id, 'chg_01');
  assert.equal(session.state, 'open');
  assert.equal(session.actor, 'tester');
  assert.equal(session.baseRevision, 'abc123');
  assert.deepEqual(session.operations, []);
  assert.deepEqual(session.findings, []);
});

test('mcp v2 defaults include query and mutation tool placeholders', () => {
  assert.ok(DEFAULT_MCP_RESOURCES.length >= 3);
  assert.ok(DEFAULT_MCP_TOOLS.some((tool) => tool.name === 'query_registry'));
  assert.ok(DEFAULT_MCP_TOOLS.some((tool) => tool.name === 'start_change'));
  assert.ok(DEFAULT_MCP_TOOLS.some((tool) => tool.name === 'commit_change'));
});
