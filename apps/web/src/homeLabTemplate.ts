import type { Resource, ResourceType } from "./types";

export function buildHomeLabTypes(): ResourceType[] {
  return [
    {
      id: "site",
      title: "Site",
      group: "home-lab",
      description: "Home Lab site",
      fields: [
        { name: "code", label: "Code", type: "string", enum_values: [] },
        {
          name: "description",
          label: "Description",
          type: "text",
          enum_values: [],
        },
      ],
      required_fields: ["code"],
      list_columns: ["metadata.name", "spec.code"],
      form_layout: [{ title: "Basic", fields: ["code", "description"] }],
      detail_sections: [{ title: "Overview", fields: ["code", "description"] }],
      references: [],
      validation_rules: [{ type: "unique", field: "code" }],
    },
    {
      id: "zone",
      title: "Zone",
      group: "home-lab",
      description: "Home Lab zone",
      fields: [
        { name: "site", label: "Site", type: "reference", enum_values: [] },
        { name: "purpose", label: "Purpose", type: "text", enum_values: [] },
      ],
      required_fields: ["site"],
      list_columns: ["metadata.name", "spec.site"],
      form_layout: [{ title: "Basic", fields: ["site", "purpose"] }],
      detail_sections: [{ title: "Overview", fields: ["site", "purpose"] }],
      references: [{ field: "site", target_type: "site", multiple: false }],
      validation_rules: [],
    },
    {
      id: "prefix",
      title: "Prefix",
      group: "home-lab",
      description: "Network prefix",
      fields: [
        { name: "site", label: "Site", type: "reference", enum_values: [] },
        { name: "cidr", label: "CIDR", type: "cidr", enum_values: [] },
        { name: "gateway", label: "Gateway", type: "ip", enum_values: [] },
      ],
      required_fields: ["site", "cidr"],
      list_columns: ["metadata.name", "spec.cidr"],
      form_layout: [{ title: "Basic", fields: ["site", "cidr", "gateway"] }],
      detail_sections: [
        { title: "Overview", fields: ["site", "cidr", "gateway"] },
      ],
      references: [{ field: "site", target_type: "site", multiple: false }],
      validation_rules: [{ type: "unique", field: "cidr" }],
    },
    {
      id: "vlan",
      title: "VLAN",
      group: "home-lab",
      description: "VLAN segment",
      fields: [
        { name: "site", label: "Site", type: "reference", enum_values: [] },
        { name: "zone", label: "Zone", type: "reference", enum_values: [] },
        { name: "vlan_id", label: "VLAN ID", type: "integer", enum_values: [] },
        { name: "prefix", label: "Prefix", type: "reference", enum_values: [] },
        { name: "role", label: "Role", type: "string", enum_values: [] },
      ],
      required_fields: ["site", "zone", "vlan_id"],
      list_columns: ["metadata.name", "spec.vlan_id"],
      form_layout: [
        {
          title: "Basic",
          fields: ["site", "zone", "vlan_id", "prefix", "role"],
        },
      ],
      detail_sections: [
        {
          title: "Overview",
          fields: ["site", "zone", "vlan_id", "prefix", "role"],
        },
      ],
      references: [
        { field: "site", target_type: "site", multiple: false },
        { field: "zone", target_type: "zone", multiple: false },
        { field: "prefix", target_type: "prefix", multiple: false },
      ],
      validation_rules: [{ type: "unique", field: "vlan_id" }],
    },
    {
      id: "ip_address",
      title: "IP Address",
      group: "home-lab",
      description: "IP address",
      fields: [
        { name: "address", label: "Address", type: "ip", enum_values: [] },
        { name: "prefix", label: "Prefix", type: "reference", enum_values: [] },
        {
          name: "assigned_to_type",
          label: "Assigned To Type",
          type: "enum",
          enum_values: ["vm", "device", "container", "service"],
        },
        {
          name: "assigned_to_id",
          label: "Assigned To ID",
          type: "string",
          enum_values: [],
        },
      ],
      required_fields: ["address"],
      list_columns: ["metadata.name", "spec.address"],
      form_layout: [
        {
          title: "Basic",
          fields: ["address", "prefix", "assigned_to_type", "assigned_to_id"],
        },
      ],
      detail_sections: [
        {
          title: "Overview",
          fields: ["address", "prefix", "assigned_to_type", "assigned_to_id"],
        },
      ],
      references: [{ field: "prefix", target_type: "prefix", multiple: false }],
      validation_rules: [{ type: "unique", field: "address" }],
    },
    {
      id: "device",
      title: "Device",
      group: "home-lab",
      description: "Physical device",
      fields: [
        { name: "site", label: "Site", type: "reference", enum_values: [] },
        {
          name: "role",
          label: "Role",
          type: "enum",
          enum_values: ["router", "switch", "firewall", "hypervisor"],
        },
        {
          name: "management_ip",
          label: "Management IP",
          type: "reference",
          enum_values: [],
        },
      ],
      required_fields: ["site", "role"],
      list_columns: ["metadata.name", "spec.role"],
      form_layout: [
        { title: "Basic", fields: ["site", "role", "management_ip"] },
      ],
      detail_sections: [
        { title: "Overview", fields: ["site", "role", "management_ip"] },
      ],
      references: [
        { field: "site", target_type: "site", multiple: false },
        { field: "management_ip", target_type: "ip_address", multiple: false },
      ],
      validation_rules: [],
    },
    {
      id: "vm",
      title: "VM",
      group: "home-lab",
      description: "Virtual machine",
      fields: [
        { name: "site", label: "Site", type: "reference", enum_values: [] },
        { name: "host", label: "Host", type: "reference", enum_values: [] },
        {
          name: "primary_ip",
          label: "Primary IP",
          type: "reference",
          enum_values: [],
        },
        { name: "role", label: "Role", type: "string", enum_values: [] },
        { name: "os", label: "OS", type: "string", enum_values: [] },
      ],
      required_fields: ["site", "host", "primary_ip"],
      list_columns: ["metadata.name", "spec.role"],
      form_layout: [
        {
          title: "Basic",
          fields: ["site", "host", "primary_ip", "role", "os"],
        },
      ],
      detail_sections: [
        {
          title: "Overview",
          fields: ["site", "host", "primary_ip", "role", "os"],
        },
      ],
      references: [
        { field: "site", target_type: "site", multiple: false },
        { field: "host", target_type: "device", multiple: false },
        { field: "primary_ip", target_type: "ip_address", multiple: false },
      ],
      validation_rules: [],
    },
    {
      id: "dns_zone",
      title: "DNS Zone",
      group: "home-lab",
      description: "DNS zone",
      fields: [
        { name: "name", label: "Name", type: "string", enum_values: [] },
        { name: "site", label: "Site", type: "reference", enum_values: [] },
      ],
      required_fields: ["name"],
      list_columns: ["metadata.name", "spec.name"],
      form_layout: [{ title: "Basic", fields: ["name", "site"] }],
      detail_sections: [{ title: "Overview", fields: ["name", "site"] }],
      references: [{ field: "site", target_type: "site", multiple: false }],
      validation_rules: [{ type: "unique", field: "name" }],
    },
    {
      id: "service",
      title: "Service",
      group: "home-lab",
      description: "Application service",
      fields: [
        { name: "site", label: "Site", type: "reference", enum_values: [] },
        {
          name: "runtime",
          label: "Runtime",
          type: "reference",
          enum_values: [],
        },
        { name: "url", label: "URL", type: "url", enum_values: [] },
        { name: "ports", label: "Ports", type: "array", enum_values: [] },
        {
          name: "depends_on",
          label: "Depends On",
          type: "reference_array",
          enum_values: [],
        },
      ],
      required_fields: ["site"],
      list_columns: ["metadata.name", "spec.url"],
      form_layout: [
        {
          title: "Basic",
          fields: ["site", "runtime", "url", "ports", "depends_on"],
        },
      ],
      detail_sections: [
        {
          title: "Overview",
          fields: ["site", "runtime", "url", "ports", "depends_on"],
        },
      ],
      references: [
        { field: "site", target_type: "site", multiple: false },
        { field: "runtime", target_type: "vm", multiple: false },
        { field: "depends_on", target_type: "service", multiple: true },
      ],
      validation_rules: [],
    },
    {
      id: "dns_record",
      title: "DNS Record",
      group: "home-lab",
      description: "DNS record",
      fields: [
        { name: "zone", label: "Zone", type: "reference", enum_values: [] },
        {
          name: "record_type",
          label: "Record Type",
          type: "enum",
          enum_values: ["A", "AAAA", "CNAME", "TXT"],
        },
        { name: "name", label: "Name", type: "string", enum_values: [] },
        { name: "value", label: "Value", type: "string", enum_values: [] },
        { name: "target", label: "Target", type: "reference", enum_values: [] },
      ],
      required_fields: ["zone", "record_type", "name"],
      list_columns: ["metadata.name", "spec.record_type"],
      form_layout: [
        {
          title: "Basic",
          fields: ["zone", "record_type", "name", "value", "target"],
        },
      ],
      detail_sections: [
        {
          title: "Overview",
          fields: ["zone", "record_type", "name", "value", "target"],
        },
      ],
      references: [
        { field: "zone", target_type: "dns_zone", multiple: false },
        { field: "target", target_type: "service", multiple: false },
      ],
      validation_rules: [],
    },
  ];
}

export function buildHomeLabSampleResources(): Resource[] {
  return [];
}
