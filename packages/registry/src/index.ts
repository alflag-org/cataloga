export const packageName = '@cataloga/registry';

export type RegistryScalar = string | number | boolean | null;

export type RegistryValue =
  | RegistryScalar
  | readonly RegistryValue[]
  | { readonly [key: string]: RegistryValue };

export type RegistryRecord = Readonly<Record<string, RegistryValue>>;

export type RegistryKind =
  | 'entity'
  | 'relation'
  | 'schema'
  | 'view'
  | 'policy'
  | 'evidence'
  | 'validation-rule';

export type EvidenceRef = {
  readonly evidenceId: string;
  readonly summary?: string;
};

export type Entity = {
  readonly kind: 'entity';
  readonly id: string;
  readonly schemaId: string;
  readonly title?: string;
  readonly tags: readonly string[];
  readonly attributes: RegistryRecord;
  readonly evidenceRefs?: readonly EvidenceRef[];
};

export type RelationEndpoint = {
  readonly entityId: string;
  readonly role?: string;
};

export type Relation = {
  readonly kind: 'relation';
  readonly id: string;
  readonly relationType: string;
  readonly source: RelationEndpoint;
  readonly target: RelationEndpoint;
  readonly attributes?: RegistryRecord;
  readonly evidenceRefs?: readonly EvidenceRef[];
};

export type SchemaFieldType = 'string' | 'number' | 'boolean' | 'object' | 'array';

export type SchemaField = {
  readonly name: string;
  readonly type: SchemaFieldType;
  readonly required: boolean;
  readonly description?: string;
};

export type Schema = {
  readonly kind: 'schema';
  readonly id: string;
  readonly title: string;
  readonly appliesTo: 'entity' | 'relation';
  readonly fields: readonly SchemaField[];
  readonly identityFields?: readonly string[];
};

export type View = {
  readonly kind: 'view';
  readonly id: string;
  readonly title: string;
  readonly query: string;
  readonly description?: string;
};

export type PolicyMode = 'advisory' | 'enforced';

export type Policy = {
  readonly kind: 'policy';
  readonly id: string;
  readonly title: string;
  readonly mode: PolicyMode;
  readonly ruleIds: readonly string[];
  readonly owner?: string;
};

export type Evidence = {
  readonly kind: 'evidence';
  readonly id: string;
  readonly source: string;
  readonly observedAt: string;
  readonly confidence: number;
  readonly uri?: string;
  readonly attributes?: RegistryRecord;
};

export type ValidationSeverity = 'error' | 'warning' | 'info';

export type ValidationRule = {
  readonly kind: 'validation-rule';
  readonly id: string;
  readonly title: string;
  readonly appliesTo: readonly RegistryKind[];
  readonly severity: ValidationSeverity;
  readonly expression: string;
  readonly owner?: string;
};

export type RegistryPrimitive =
  | Entity
  | Relation
  | Schema
  | View
  | Policy
  | Evidence
  | ValidationRule;

export type ChangeSessionState =
  | 'open'
  | 'validated'
  | 'ready_to_commit'
  | 'committed'
  | 'aborted';

export type MutationOperation =
  | {
      readonly op: 'upsert';
      readonly kind: RegistryKind;
      readonly id: string;
      readonly value: RegistryRecord;
    }
  | {
      readonly op: 'delete';
      readonly kind: RegistryKind;
      readonly id: string;
    }
  | {
      readonly op: 'patch';
      readonly kind: RegistryKind;
      readonly id: string;
      readonly set?: RegistryRecord;
      readonly unset?: readonly string[];
    };

export type ValidationFinding = {
  readonly severity: ValidationSeverity;
  readonly code: string;
  readonly message: string;
  readonly subjectKind: RegistryKind | 'change-session';
  readonly subjectId?: string;
};

export type ChangeDiff = {
  readonly semantic: readonly string[];
  readonly filePaths: readonly string[];
};

export type ChangeSession = {
  readonly id: string;
  readonly state: ChangeSessionState;
  readonly createdAt: string;
  readonly updatedAt: string;
  readonly actor: string;
  readonly baseRevision?: string;
  readonly operations: readonly MutationOperation[];
  readonly findings: readonly ValidationFinding[];
  readonly diff?: ChangeDiff;
};

export const CHANGE_LIFECYCLE = [
  'start_change',
  'apply_mutation',
  'validate_change',
  'show_diff',
  'commit_change',
  'abort_change'
] as const;

export type ChangeLifecycleAction = (typeof CHANGE_LIFECYCLE)[number];

export const createChangeSession = (input: {
  id: string;
  actor: string;
  now: string;
  baseRevision?: string;
}): ChangeSession => ({
  id: input.id,
  state: 'open',
  createdAt: input.now,
  updatedAt: input.now,
  actor: input.actor,
  ...(input.baseRevision ? { baseRevision: input.baseRevision } : {}),
  operations: [],
  findings: []
});
