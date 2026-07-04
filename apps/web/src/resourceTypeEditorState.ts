export type AdvancedResourceTypeJson = {
  form_layout: string;
  detail_sections: string;
  validation_rules: string;
};

export function parseJsonArray(text: string): unknown[] {
  const trimmed = text.trim();
  if (!trimmed) return [];
  return JSON.parse(trimmed);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function updateJsonArrayText(
  text: string,
  transform: (items: unknown[]) => unknown[],
): string {
  try {
    return JSON.stringify(transform(parseJsonArray(text)), null, 2);
  } catch {
    return text;
  }
}

function removeFieldFromViewSections(
  sections: unknown[],
  fieldName: string,
): unknown[] {
  return sections.map((section) => {
    if (!isRecord(section) || !Array.isArray(section.fields)) {
      return section;
    }
    return {
      ...section,
      fields: section.fields.filter((field) => field !== fieldName),
    };
  });
}

function renameFieldInViewSections(
  sections: unknown[],
  oldName: string,
  newName: string,
): unknown[] {
  return sections.map((section) => {
    if (!isRecord(section) || !Array.isArray(section.fields)) {
      return section;
    }
    return {
      ...section,
      fields: section.fields.map((field) =>
        field === oldName ? newName : field,
      ),
    };
  });
}

function removeFieldFromValidationRules(
  rules: unknown[],
  fieldName: string,
): unknown[] {
  return rules.filter((rule) => !isRecord(rule) || rule.field !== fieldName);
}

function renameFieldInValidationRules(
  rules: unknown[],
  oldName: string,
  newName: string,
): unknown[] {
  return rules.map((rule) => {
    if (!isRecord(rule) || rule.field !== oldName) {
      return rule;
    }
    return { ...rule, field: newName };
  });
}

export function removeFieldFromAdvancedJson(
  advanced: AdvancedResourceTypeJson,
  fieldName: string,
): AdvancedResourceTypeJson {
  if (!fieldName) return advanced;
  return {
    form_layout: updateJsonArrayText(advanced.form_layout, (sections) =>
      removeFieldFromViewSections(sections, fieldName),
    ),
    detail_sections: updateJsonArrayText(advanced.detail_sections, (sections) =>
      removeFieldFromViewSections(sections, fieldName),
    ),
    validation_rules: updateJsonArrayText(advanced.validation_rules, (rules) =>
      removeFieldFromValidationRules(rules, fieldName),
    ),
  };
}

export function renameFieldInAdvancedJson(
  advanced: AdvancedResourceTypeJson,
  oldName: string,
  newName: string,
): AdvancedResourceTypeJson {
  if (!oldName || oldName === newName) return advanced;
  return {
    form_layout: updateJsonArrayText(advanced.form_layout, (sections) =>
      renameFieldInViewSections(sections, oldName, newName),
    ),
    detail_sections: updateJsonArrayText(advanced.detail_sections, (sections) =>
      renameFieldInViewSections(sections, oldName, newName),
    ),
    validation_rules: updateJsonArrayText(advanced.validation_rules, (rules) =>
      renameFieldInValidationRules(rules, oldName, newName),
    ),
  };
}
