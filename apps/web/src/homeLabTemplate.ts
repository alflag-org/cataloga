import type { Resource, ResourceType } from './types'

const TYPE_IDS = ['site','zone','vlan','prefix','ip_address','device','vm','container','dns_zone','dns_record','service'] as const

export function buildHomeLabTypes(): ResourceType[] {
  return TYPE_IDS.map((id) => ({
    id,
    title: id.replaceAll('_', ' ').replace(/\b\w/g, (x) => x.toUpperCase()),
    group: 'home-lab',
    description: `Home Lab ${id}`,
    fields: [
      { name: 'description', label: 'Description', type: 'string', enum_values: [] }
    ],
    required_fields: [],
    list_columns: ['metadata.name', 'spec.description'],
    form_layout: [{ title: 'Basic', fields: ['description'] }],
    detail_sections: [{ title: 'Overview', fields: ['description'] }],
    references: [],
    validation_rules: []
  }))
}

export function buildHomeLabSampleResources(): Resource[] {
  return []
}
