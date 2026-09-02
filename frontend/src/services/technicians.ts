import { api } from './api'
import type { Technician } from '../types'

export async function listTechnicians(filters?: { skill?: string; status?: string }) {
  const { data } = await api.get<{ data: Technician[] }>('/technicians', { params: filters })
  return data.data
}

export async function getTechnician(id: number) {
  const { data } = await api.get<{ data: Technician }>(`/technicians/${id}`)
  return data.data
}

export async function updateTechnicianStatus(id: number, status: 'available' | 'off_duty') {
  const { data } = await api.patch<{ data: Technician }>(`/technicians/${id}/status`, { status })
  return data.data
}
