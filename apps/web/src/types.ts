export type FieldType =
  | 'string'
  | 'text'
  | 'integer'
  | 'number'
  | 'boolean'
  | 'enum'
  | 'array'
  | 'json'
  | 'reference'
  | 'reference_array'
  | 'ip'
  | 'cidr'
  | 'url'

export type FieldDef = {
  name: string
  label: string
  type: FieldType
  enum_values: string[]
}

export type ResourceType = {
  id: string
  title: string
  group: string
  description: string
  fields: FieldDef[]
  required_fields: string[]
  list_columns: string[]
  form_layout: Array<{ title: string; fields: string[] }>
  detail_sections: Array<{ title: string; fields: string[] }>
  references: Array<{ field: string; target_type: string; multiple: boolean }>
  validation_rules: Array<Record<string, unknown>>
}

export type Resource = {
  api_version: string
  kind: string
  metadata: {
    id: string
    type: string
    name: string
    tags: Record<string, string>
  }
  spec: Record<string, unknown>
  custom_fields: Record<string, unknown>
  dependencies: Record<string, unknown>
}

export function defaultResourceType(): ResourceType {
  return {
    id: '',
    title: '',
    group: '',
    description: '',
    fields: [],
    required_fields: [],
    list_columns: ['metadata.name'],
    form_layout: [],
    detail_sections: [],
    references: [],
    validation_rules: []
  }
}

export function defaultResource(type: string): Resource {
  return {
    api_version: 'cataloga.io/v1',
    kind: 'Resource',
    metadata: {
      id: '',
      type,
      name: '',
      tags: {}
    },
    spec: {},
    custom_fields: {},
    dependencies: {}
  }
}

export function compactValue(value: unknown): string {
  if (value == null) return ''
  if (typeof value === 'string') return value
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)
  try {
    return JSON.stringify(value)
  } catch {
    return String(value)
  }
}

export function readPath(resource: Resource, path: string): string {
  const [head, ...rest] = path.split('.')
  const root =
    head === 'metadata'
      ? resource.metadata
      : head === 'spec'
        ? resource.spec
        : head === 'custom_fields'
          ? resource.custom_fields
          : head === 'dependencies'
            ? resource.dependencies
            : undefined
  if (!root) return ''
  let cur: unknown = root
  for (const segment of rest) {
    if (segment === '*') {
      return compactValue(cur)
    }
    if (cur == null || typeof cur !== 'object') return ''
    cur = (cur as Record<string, unknown>)[segment]
  }
  return compactValue(cur)
}
