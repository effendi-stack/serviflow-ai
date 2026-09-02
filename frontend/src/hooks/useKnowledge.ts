import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as api from '../services/knowledge'

export function useDocuments() {
  return useQuery({
    queryKey: ['knowledge-documents'],
    queryFn: api.listDocuments,
    // Documents move uploaded -> processing -> indexed/failed in the
    // background; poll while anything is still in flight so the status
    // badges update without a manual refresh.
    refetchInterval: (query) => {
      const docs = query.state.data
      return docs?.some((d) => d.status === 'uploaded' || d.status === 'processing') ? 3000 : false
    },
  })
}

export function useUploadDocument() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ title, category, file }: { title: string; category: string; file: File }) =>
      api.uploadDocument(title, category, file),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['knowledge-documents'] }),
  })
}

export function useDeleteDocument() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.deleteDocument(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['knowledge-documents'] }),
  })
}

export function useReindexDocument() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.reindexDocument(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['knowledge-documents'] }),
  })
}
