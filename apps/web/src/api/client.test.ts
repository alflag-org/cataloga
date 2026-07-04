import { afterEach, describe, expect, it, vi } from "vitest";
import { api } from "./client";

function stubNoContentFetch() {
  const fetchMock = vi.fn(async () => new Response(null, { status: 204 }));
  vi.stubGlobal("fetch", fetchMock);
  return fetchMock;
}

describe("api client", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("deletes a Resource Type without deleting Resources by default", async () => {
    const fetchMock = stubNoContentFetch();

    await api.deleteResourceType("site");

    expect(fetchMock).toHaveBeenCalledWith("/api/resource-types/site", {
      method: "DELETE",
    });
  });

  it("requests Resource deletion when requested", async () => {
    const fetchMock = stubNoContentFetch();

    await api.deleteResourceType("site", { deleteResources: true });

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/resource-types/site?deleteResources=true",
      {
        method: "DELETE",
      },
    );
  });
});
