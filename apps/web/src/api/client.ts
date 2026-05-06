import type { ImportPreviewResult, Resource, ResourceReferences, ResourceType, ValidationResult } from '../types'

const API = '/api'

export class ApiError extends Error {
  status: number

  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${API}${path}`, init)
  if (!res.ok) {
    const text = await res.text()
    let msg = text || `Request failed: ${res.status}`
    try {
      const json = JSON.parse(text)
      msg = json.error || json.message || msg
    } catch {
      // keep text fallback
    }
    throw new ApiError(msg, res.status)
  }
  if (res.status === 204) return undefined as T
  const text = await res.text()
  return (text ? JSON.parse(text) : undefined) as T
}

export const api = {
  health: () => request<{ status: string }>('/health'),
  listResourceTypes: () => request<ResourceType[]>('/resource-types'),
  getResourceType: (type: string) => request<ResourceType>(`/resource-types/${type}`),
  upsertResourceType: (payload: ResourceType) =>
    request<void>('/resource-types', {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(payload)
    }),
  updateResourceType: (type: string, payload: ResourceType) =>
    request<void>(`/resource-types/${type}`, {
      method: 'PUT',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(payload)
    }),
  deleteResourceType: (type: string) =>
    request<void>(`/resource-types/${type}`, {
      method: 'DELETE'
    }),
  listResources: (type: string) => request<Resource[]>(`/resources/${type}`),
  getResource: (type: string, id: string) => request<Resource>(`/resources/${type}/${id}`),
  getResourceReferences: (type: string, id: string) => request<ResourceReferences>(`/resources/${type}/${id}/references`),
  createResource: (type: string, payload: Resource) =>
    request<void>(`/resources/${type}`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(payload)
    }),
  updateResource: (type: string, id: string, payload: Resource) =>
    request<void>(`/resources/${type}/${id}`, {
      method: 'PUT',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(payload)
    }),
  deleteResource: (type: string, id: string) =>
    request<void>(`/resources/${type}/${id}`, {
      method: 'DELETE'
    }),
  getValidation: () => request<ValidationResult>('/validation'),
  importPreview: (yaml: string) =>
    request<ImportPreviewResult>('/import/preview', {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ yaml })
    }),
  importApply: (yaml: string) =>
    request<void>('/import/apply', {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ yaml })
    }),
  exportYaml: async () => {
    const res = await fetch(`${API}/export`)
    if (!res.ok) throw new ApiError(await res.text(), res.status)
    return res.text()
  }
}
