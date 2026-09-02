import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as api from '../services/serviceRequests'

export function useServiceRequests(filters?: { status?: string; priority?: string }) {
  return useQuery({
    queryKey: ['service-requests', filters],
    queryFn: () => api.listServiceRequests(filters),
  })
}

export function useServiceRequest(id: number) {
  return useQuery({
    queryKey: ['service-requests', id],
    queryFn: () => api.getServiceRequest(id),
    enabled: !!id,
  })
}

export function useRecommendedTechnicians(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['service-requests', id, 'recommended-technicians'],
    queryFn: () => api.getRecommendedTechnicians(id),
    enabled,
  })
}

export function useCreateServiceRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: api.createServiceRequest,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['service-requests'] }),
  })
}

export function useAssignTechnician(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      technicianId,
      aiMeta,
    }: {
      technicianId: number
      aiMeta?: { was_ai_recommended: boolean; ai_score?: number; ai_rationale?: string }
    }) => api.assignTechnician(requestId, technicianId, aiMeta),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] })
      queryClient.invalidateQueries({ queryKey: ['service-requests'] })
    },
  })
}

export function useScheduleJob(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (scheduledAt: string) => api.scheduleJob(requestId, scheduledAt),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] })
      queryClient.invalidateQueries({ queryKey: ['service-requests'] })
    },
  })
}

export function useStartJob(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.startJob(requestId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] })
      queryClient.invalidateQueries({ queryKey: ['service-requests'] })
    },
  })
}

export function useUpsertServiceNote(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ rawNotes, photoPaths }: { rawNotes: string; photoPaths?: string[] }) =>
      api.upsertServiceNote(requestId, rawNotes, photoPaths),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] }),
  })
}

export function useGenerateReport(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.generateReport(requestId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] }),
  })
}

export function useFinalizeReport(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (finalReport: string) => api.finalizeReport(requestId, finalReport),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] }),
  })
}

export function useCompleteJob(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.completeJob(requestId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] })
      queryClient.invalidateQueries({ queryKey: ['service-requests'] })
    },
  })
}

export function useReviewAiAnalysis(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: { category: string; priority: string; required_skill: string; estimated_duration_minutes: number }) =>
      api.reviewAiAnalysis(requestId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] })
      queryClient.invalidateQueries({ queryKey: ['service-requests'] })
    },
  })
}

export function useSubmitReview(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ rating, comment }: { rating: number; comment?: string }) =>
      api.submitReview(requestId, rating, comment),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] })
      queryClient.invalidateQueries({ queryKey: ['service-requests'] })
    },
  })
}
