import { api } from './api'
import type { Customer } from '../types'

export async function listCustomers(search?: string) {
  const { data } = await api.get<{ data: Customer[] }>('/customers', { params: search ? { search } : {} })
  return data.data
}

export async function getCustomer(id: number) {
  const { data } = await api.get<{ data: Customer }>(`/customers/${id}`)
  return data.data
}
