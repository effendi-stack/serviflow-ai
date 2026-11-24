import { postStream } from './stream'
import { api } from './api'
import type { KnowledgeAnswer, KnowledgeDocument } from '../types'

export function askKnowledgeBaseStream(question: string, onToken: (text: string) => void) {
  return postStream<KnowledgeAnswer>('/knowledge/ask/stream', { question }, onToken)
}

export async function askKnowledgeBase(question: string) {
  const { data } = await api.post<{ data: KnowledgeAnswer }>('/knowledge/ask', { question })
  return data.data
}

export async function uploadDocument(title: string, category: string, file: File) {
  const form = new FormData()
  form.append('title', title)
  form.append('category', category)
  form.append('file', file)
  const { data } = await api.post<{ data: KnowledgeDocument }>('/knowledge/documents', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function listDocuments() {
  const { data } = await api.get<{ data: KnowledgeDocument[] }>('/knowledge/documents')
  return data.data
}

export async function deleteDocument(id: number) {
  await api.delete(`/knowledge/documents/${id}`)
}

export async function reindexDocument(id: number) {
  const { data } = await api.post<{ data: KnowledgeDocument }>(`/knowledge/documents/${id}/reindex`)
  return data.data
}
