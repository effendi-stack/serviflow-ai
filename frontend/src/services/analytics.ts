import { api } from './api'
import type { AnalyticsSummary } from '../types'

export async function getAnalyticsSummary() {
  const { data } = await api.get<{ data: AnalyticsSummary }>('/analytics/summary')
  return data.data
}

export async function explainAnalytics(question: string) {
  const { data } = await api.post<{ data: { stats: AnalyticsSummary; explanation: string } }>(
    '/analytics/explain',
    { question },
  )
  return data.data
}
