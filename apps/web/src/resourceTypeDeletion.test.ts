import { describe, expect, it } from "vitest";
import {
  canSubmitResourceTypeDelete,
  shouldDeleteResourcesWithResourceType,
} from "./resourceTypeDeletion";

describe("resource type deletion", () => {
  it("requires an exact Resource Type ID confirmation", () => {
    expect(
      canSubmitResourceTypeDelete({
        targetId: "site",
        confirmation: "site",
        isDeleting: false,
      }),
    ).toBe(true);

    expect(
      canSubmitResourceTypeDelete({
        targetId: "site",
        confirmation: "Site",
        isDeleting: false,
      }),
    ).toBe(false);

    expect(
      canSubmitResourceTypeDelete({
        targetId: "site",
        confirmation: "site ",
        isDeleting: false,
      }),
    ).toBe(false);
  });

  it("blocks submission while deletion is already running", () => {
    expect(
      canSubmitResourceTypeDelete({
        targetId: "site",
        confirmation: "site",
        isDeleting: true,
      }),
    ).toBe(false);
  });

  it("only requests Resource deletion when Resources exist", () => {
    expect(shouldDeleteResourcesWithResourceType(0)).toBe(false);
    expect(shouldDeleteResourcesWithResourceType(1)).toBe(true);
    expect(shouldDeleteResourcesWithResourceType(42)).toBe(true);
  });
});
