import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { getAnalyticsSummary, explainAnalytics } from '../services/analytics'
import { AiBadge } from '../components/AiBadge'

export default function Analytics() {
  const { data: stats, isLoading } = useQuery({ queryKey: ['analytics-summary'], queryFn: getAnalyticsSummary })
  const [question, setQuestion] = useState('Why did service requests increase this month?')
  const explain = useMutation({ mutationFn: explainAnalytics })

  if (isLoading || !stats) {
    return <div className="p-4 md:p-8 text-slate-500 text-sm">Loading…</div>
  }

  return (
    <div className="p-4 md:p-8 max-w-5xl mx-auto space-y-8">
      <header>
        <h1 className="font-display text-2xl font-bold">Operational Analytics</h1>
        <p className="text-slate-500 text-sm mt-1">Real numbers from Postgres. AI only narrates, never invents.</p>
      </header>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Stat label="Total jobs" value={stats.total_jobs} />
        <Stat label="Avg. completion" value={stats.average_completion_minutes ? `${stats.average_completion_minutes}m` : '—'} />
        <Stat label="Avg. rating" value={stats.average_customer_rating ?? '—'} />
        <Stat label="Closed" value={stats.by_status['closed'] ?? 0} />
      </div>

      <div className="card p-5">
        <h2 className="font-display font-semibold mb-3">By status</h2>
        <div className="space-y-2">
          {Object.entries(stats.by_status).map(([status, count]) => (
            <div key={status} className="flex items-center gap-3">
              <div className="w-40 text-xs font-mono uppercase text-slate-500">{status.replace(/_/g, ' ')}</div>
              <div className="flex-1 bg-slate-850 rounded h-2 overflow-hidden">
                <div
                  className="bg-amber-500 h-full"
                  style={{ width: `${stats.total_jobs > 0 ? (count / stats.total_jobs) * 100 : 0}%` }}
                />
              </div>
              <div className="text-sm w-8 text-right">{count}</div>
            </div>
          ))}
        </div>
      </div>

      <div className="card p-5">
        <div className="flex items-center gap-2 mb-3">
          <h2 className="font-display font-semibold">Ask about the data</h2>
          <AiBadge>grounded in real stats</AiBadge>
        </div>
        <div className="flex gap-2 mb-4">
          <input
            value={question}
            onChange={(e) => setQuestion(e.target.value)}
            className="flex-1 bg-slate-850 border border-slate-700 rounded-md px-3 py-2 text-sm"
          />
          <button className="btn-primary" onClick={() => explain.mutate(question)} disabled={explain.isPending}>
            {explain.isPending ? 'Thinking…' : 'Explain'}
          </button>
        </div>
        {explain.data && <p className="text-sm text-slate-300 whitespace-pre-wrap">{explain.data.explanation}</p>}
        {explain.isError && <p className="text-signal-red text-xs">Could not get an explanation right now — try again in a moment.</p>}
      </div>
    </div>
  )
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="card p-4">
      <div className="text-2xl font-display font-bold text-amber-500">{value}</div>
      <div className="text-xs font-mono uppercase text-slate-500 mt-1">{label}</div>
    </div>
  )
}
