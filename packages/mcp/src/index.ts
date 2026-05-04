import { CHANGE_LIFECYCLE, type ChangeLifecycleAction, type RegistryKind } from '@cataloga/registry';

export const packageName = '@cataloga/mcp';

export type McpResource = {
  readonly uri: string;
  readonly title: string;
  readonly description: string;
};

export type McpTool = {
  readonly name: string;
  readonly description: string;
  readonly requiresChangeSession: boolean;
};

export type McpPrompt = {
  readonly id: string;
  readonly title: string;
  readonly template: string;
};

export const RESOURCE_URIS = {
  registrySummary: 'registry://summary',
  schemas: 'registry://schemas',
  entities: 'registry://entities',
  relations: 'registry://relations',
  views: 'registry://views',
  policies: 'registry://policies',
  evidence: 'registry://evidence',
  validationRules: 'registry://validation-rules',
  changeSessions: 'registry://change-sessions'
} as const;

export const DEFAULT_MCP_RESOURCES: readonly McpResource[] = [
  {
    uri: RESOURCE_URIS.registrySummary,
    title: 'Registry Summary',
    description: 'High-level counts and active domain packs.'
  },
  {
    uri: RESOURCE_URIS.schemas,
    title: 'Schemas',
    description: 'Effective schema definitions loaded from core and packs.'
  },
  {
    uri: RESOURCE_URIS.changeSessions,
    title: 'Change Sessions',
    description: 'Open and recent mutation sessions with status and actor metadata.'
  }
];

export type MutationToolName = Extract<ChangeLifecycleAction, string>;

export const MUTATION_TOOL_NAMES: readonly MutationToolName[] = CHANGE_LIFECYCLE;

const mutationToolDescription = (name: MutationToolName): string => {
  if (name === 'start_change') {
    return 'Start a new change session from the current registry revision.';
  }
  if (name === 'apply_mutation') {
    return 'Apply one or more typed mutation operations to a change session.';
  }
  if (name === 'validate_change') {
    return 'Run schema, policy, and validation rule checks for the session.';
  }
  if (name === 'show_diff') {
    return 'Return semantic and file-level diff for the staged session.';
  }
  if (name === 'commit_change') {
    return 'Write approved session changes and finalize commit metadata.';
  }
  return 'Abort and discard all staged session operations.';
};

export const DEFAULT_MCP_TOOLS: readonly McpTool[] = [
  {
    name: 'query_registry',
    description: 'Run read-only registry queries across entities, relations, and views.',
    requiresChangeSession: false
  },
  ...MUTATION_TOOL_NAMES.map((name) => ({
    name,
    description: mutationToolDescription(name),
    requiresChangeSession: name !== 'start_change'
  }))
];

export const DEFAULT_MCP_PROMPTS: readonly McpPrompt[] = [
  {
    id: 'safe-edit',
    title: 'Safe Registry Edit',
    template:
      'Plan edits, start a change session, apply mutations, validate, show diff, and ask for commit approval.'
  },
  {
    id: 'validation-triage',
    title: 'Validation Triage',
    template:
      'Inspect validation findings, propose fixes as mutations, and summarize policy/schema impact before commit.'
  },
  {
    id: 'pack-bootstrap',
    title: 'Domain Pack Bootstrap',
    template:
      'Create or update a domain pack schema/policy/view set without modifying core registry semantics.'
  }
];

export type McpPermissionScope =
  | 'registry.read'
  | 'registry.query'
  | 'registry.mutate'
  | 'registry.commit'
  | 'registry.admin';

export type MutationInput = {
  readonly kind: RegistryKind;
  readonly id: string;
};
