import { describe, expect, it } from "vitest";
import {
  removeFieldFromAdvancedJson,
  renameFieldInAdvancedJson,
  type AdvancedResourceTypeJson,
} from "./resourceTypeEditorState";

function advancedJson(): AdvancedResourceTypeJson {
  return {
    form_layout: JSON.stringify(
      [{ title: "Basic", fields: ["name", "site_type", "address"] }],
      null,
      2,
    ),
    detail_sections: JSON.stringify(
      [{ title: "Overview", fields: ["site_type", "address"] }],
      null,
      2,
    ),
    validation_rules: JSON.stringify(
      [
        { type: "unique", field: "site_type" },
        { type: "url", field: "dashboard_url" },
      ],
      null,
      2,
    ),
  };
}

describe("resource type editor state", () => {
  it("removes a deleted Field from View and Validate JSON", () => {
    const next = removeFieldFromAdvancedJson(advancedJson(), "site_type");

    expect(JSON.parse(next.form_layout)).toEqual([
      { title: "Basic", fields: ["name", "address"] },
    ]);
    expect(JSON.parse(next.detail_sections)).toEqual([
      { title: "Overview", fields: ["address"] },
    ]);
    expect(JSON.parse(next.validation_rules)).toEqual([
      { type: "url", field: "dashboard_url" },
    ]);
  });

  it("renames Field references in View and Validate JSON", () => {
    const next = renameFieldInAdvancedJson(
      advancedJson(),
      "site_type",
      "category",
    );

    expect(JSON.parse(next.form_layout)).toEqual([
      { title: "Basic", fields: ["name", "category", "address"] },
    ]);
    expect(JSON.parse(next.detail_sections)).toEqual([
      { title: "Overview", fields: ["category", "address"] },
    ]);
    expect(JSON.parse(next.validation_rules)).toEqual([
      { type: "unique", field: "category" },
      { type: "url", field: "dashboard_url" },
    ]);
  });

  it("leaves invalid JSON text unchanged", () => {
    const advanced = {
      ...advancedJson(),
      form_layout: "[",
    };

    const next = removeFieldFromAdvancedJson(advanced, "site_type");

    expect(next.form_layout).toBe("[");
    expect(JSON.parse(next.detail_sections)).toEqual([
      { title: "Overview", fields: ["address"] },
    ]);
  });
});
