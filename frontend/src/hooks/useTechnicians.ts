import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as api from '../services/technicians'

export function useTechnicians(filters?: { skill?: string; status?: string }) {
  return useQuery({
    queryKey: ['technicians', filters],
    queryFn: () => api.listTechnicians(filters),
  })
}

export function useTechnician(id: number) {
  return useQuery({
    queryKey: ['technicians', id],
    queryFn: () => api.getTechnician(id),
    enabled: !!id,
  })
}

export function useUpdateTechnicianStatus() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: 'available' | 'off_duty' }) =>
      api.updateTechnicianStatus(id, status),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['technicians'] }),
  })
}
