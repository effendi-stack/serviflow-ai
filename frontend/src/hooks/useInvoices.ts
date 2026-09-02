import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as api from '../services/invoices'

export function useInvoices(status?: string) {
  return useQuery({
    queryKey: ['invoices', status],
    queryFn: () => api.listInvoices(status),
  })
}

function useInvoiceMutation(requestId: number, fn: (id: number) => Promise<unknown>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => fn(requestId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] })
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
    },
  })
}

export function useSaveInvoiceDraft(requestId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ laborCost, partsCost }: { laborCost: number; partsCost: number }) =>
      api.saveInvoiceDraft(requestId, laborCost, partsCost),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['service-requests', requestId] })
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
    },
  })
}

export function useIssueInvoice(requestId: number) {
  return useInvoiceMutation(requestId, api.issueInvoice)
}

export function usePayInvoice(requestId: number) {
  return useInvoiceMutation(requestId, api.payInvoice)
}

export function useCancelInvoice(requestId: number) {
  return useInvoiceMutation(requestId, api.cancelInvoice)
}
