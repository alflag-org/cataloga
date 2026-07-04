export function canSubmitResourceTypeDelete({
  targetId,
  confirmation,
  isDeleting,
}: {
  targetId: string;
  confirmation: string;
  isDeleting: boolean;
}) {
  return Boolean(targetId) && confirmation === targetId && !isDeleting;
}

export function shouldDeleteResourcesWithResourceType(resourceCount: number) {
  return resourceCount > 0;
}
