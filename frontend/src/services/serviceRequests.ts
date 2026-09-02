import { api } from './api'
import type { ServiceRequest, RecommendedTechnician, ServiceNote, Review } from '../types'

export interface CreateServiceRequestPayload {
  service_type: string
  description: string
  preferred_date?: string
  preferred_time?: string
  address?: string
}

export async function listServiceRequests(params?: { status?: string; priority?: string }) {
  const { data } = await api.get<{ data: ServiceRequest[] }>('/service-requests', { params })
  return data.data
}

export async function getServiceRequest(id: number) {
  const { data } = await api.get<{ data: ServiceRequest }>(`/service-requests/${id}`)
  return data.data
}

export async function createServiceRequest(payload: CreateServiceRequestPayload) {
  const { data } = await api.post<{ data: ServiceRequest }>('/service-requests', payload)
  return data.data
}

export async function getRecommendedTechnicians(requestId: number) {
  const { data } = await api.get<{ data: RecommendedTechnician[] }>(
    `/service-requests/${requestId}/recommended-technicians`,
  )
  return data.data
}

export async function assignTechnician(
  requestId: number,
  technicianId: number,
  aiMeta?: { was_ai_recommended: boolean; ai_score?: number; ai_rationale?: string },
) {
  const { data } = await api.post(`/service-requests/${requestId}/assign`, {
    technician_id: technicianId,
    ...aiMeta,
  })
  return data.data
}

export async function scheduleJob(requestId: number, scheduledAt: string) {
  const { data } = await api.post<{ data: ServiceRequest }>(`/service-requests/${requestId}/schedule`, {
    scheduled_at: scheduledAt,
  })
  return data.data
}

export async function startJob(requestId: number) {
  const { data } = await api.post<{ data: ServiceRequest }>(`/service-requests/${requestId}/start`)
  return data.data
}

export async function completeJob(requestId: number) {
  const { data } = await api.post<{ data: ServiceRequest }>(`/service-requests/${requestId}/complete`)
  return data.data
}

export async function upsertServiceNote(requestId: number, rawNotes: string, photoPaths?: string[]) {
  const { data } = await api.post<{ data: ServiceNote }>(`/service-requests/${requestId}/notes`, {
    raw_notes: rawNotes,
    ...(photoPaths ? { photo_paths: photoPaths } : {}),
  })
  return data.data
}

export async function generateReport(requestId: number) {
  const { data } = await api.post<{ data: ServiceNote }>(`/service-requests/${requestId}/generate-report`)
  return data.data
}

export async function finalizeReport(requestId: number, finalReport: string) {
  const { data } = await api.post<{ data: ServiceNote }>(`/service-requests/${requestId}/finalize-report`, {
    final_report: finalReport,
  })
  return data.data
}

export async function reviewAiAnalysis(
  requestId: number,
  payload: { category: string; priority: string; required_skill: string; estimated_duration_minutes: number },
) {
  const { data } = await api.post<{ data: ServiceRequest }>(`/service-requests/${requestId}/triage`, payload)
  return data.data
}

export async function submitReview(requestId: number, rating: number, comment?: string) {
  const { data } = await api.post<{ data: Review }>(`/service-requests/${requestId}/review`, {
    rating,
    ...(comment ? { comment } : {}),
  })
  return data.data
}
