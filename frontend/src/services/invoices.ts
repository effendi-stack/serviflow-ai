import { api } from './api'
import type { Invoice } from '../types'

export async function listInvoices(status?: string) {
  const { data } = await api.get<{ data: Invoice[] }>('/invoices', { params: status ? { status } : {} })
  return data.data
}

export async function saveInvoiceDraft(requestId: number, laborCost: number, partsCost: number) {
  const { data } = await api.post<{ data: Invoice }>(`/service-requests/${requestId}/invoice`, {
    labor_cost: laborCost,
    parts_cost: partsCost,
  })
  return data.data
}

export async function issueInvoice(requestId: number) {
  const { data } = await api.post<{ data: Invoice }>(`/service-requests/${requestId}/invoice/issue`)
  return data.data
}

export async function payInvoice(requestId: number) {
  const { data } = await api.post<{ data: Invoice }>(`/service-requests/${requestId}/invoice/pay`)
  return data.data
}

export async function cancelInvoice(requestId: number) {
  const { data } = await api.post<{ data: Invoice }>(`/service-requests/${requestId}/invoice/cancel`)
  return data.data
}
