import { describe, expect, it } from "vitest";
import { buildResourcePayload } from "./resourcePayload";
import type { Resource, ResourceType } from "./types";

function resourceType(overrides: Partial<ResourceType> = {}): ResourceType {
  return {
    id: "ip_reservation",
    title: "IP Reservation",
    group: "",
    description: "",
    fields: [
      {
        name: "address",
        label: "Address",
        type: "ip",
        enum_values: [],
      },
    ],
    required_fields: [],
    list_columns: [],
    form_layout: [],
    detail_sections: [],
    references: [],
    validation_rules: [],
    ...overrides,
  };
}

function resource(overrides: Partial<Resource> = {}): Resource {
  return {
    id: "ip-10.10.10.242",
    type: "ip_reservation",
    name: "10.10.10.242",
    tags: {},
    spec: {
      address: "10.10.10.242",
      zone: "client",
    },
    custom_fields: {},
    dependencies: {},
    ...overrides,
  };
}

describe("buildResourcePayload", () => {
  it("drops spec keys that are no longer defined as Fields", () => {
    const payload = buildResourcePayload(
      resourceType(),
      resource(),
      "{}",
      "{}",
    );

    expect(payload.spec).toEqual({
      address: "10.10.10.242",
    });
  });
});
