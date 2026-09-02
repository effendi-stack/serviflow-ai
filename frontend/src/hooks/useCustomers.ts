import { useQuery } from '@tanstack/react-query'
import * as api from '../services/customers'

export function useCustomers(search?: string) {
  return useQuery({
    queryKey: ['customers', search],
    queryFn: () => api.listCustomers(search),
  })
}

export function useCustomer(id: number) {
  return useQuery({
    queryKey: ['customers', id],
    queryFn: () => api.getCustomer(id),
    enabled: !!id,
  })
}
