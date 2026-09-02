import { useServiceRequests } from '../hooks/useServiceRequests'
import { useAuth } from '../hooks/useAuth'
import { StatusBadge, PriorityLabel } from '../components/StatusBadge'
import { Link } from 'react-router-dom'

const PIPELINE_STAGES = [
  'new',
  'analyzing',
  'ready_for_assignment',
  'assigned',
  'in_progress',
  'completed',
] as const

export default function Dashboard() {
  const { user } = useAuth()
  const { data: requests, isLoading } = useServiceRequests()

  const counts = PIPELINE_STAGES.reduce<Record<string, number>>((acc, stage) => {
    acc[stage] = requests?.filter((r) => r.status === stage).length ?? 0
    return acc
  }, {})

  return (
    <div className="p-4 md:p-8 max-w-6xl mx-auto">
      <header className="mb-8">
        <h1 className="font-display text-2xl font-bold">Welcome back, {user?.name?.split(' ')[0]}</h1>
        <p className="text-slate-500 text-sm mt-1">Here's where every job in the pipeline currently sits.</p>
      </header>

      <div className="grid grid-cols-3 md:grid-cols-6 gap-3 mb-10">
        {PIPELINE_STAGES.map((stage) => (
          <div key={stage} className="card p-4">
            <div className="text-2xl font-display font-bold text-amber-500">{counts[stage]}</div>
            <div className="text-xs font-mono uppercase text-slate-500 mt-1">{stage.replace(/_/g, ' ')}</div>
          </div>
        ))}
      </div>

      <div className="card">
        <div className="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
          <h2 className="font-display font-semibold">Recent requests</h2>
          <Link to="/requests" className="text-xs text-amber-500 hover:underline">
            View all →
          </Link>
        </div>
        {isLoading ? (
          <div className="p-5 text-slate-500 text-sm">Loading…</div>
        ) : !requests || requests.length === 0 ? (
          <div className="p-5 text-slate-500 text-sm">No service requests yet.</div>
        ) : (
          <table className="w-full text-sm">
            <tbody>
              {requests?.slice(0, 8).map((r) => (
                <tr key={r.id} className="border-b border-slate-850 last:border-0 hover:bg-slate-850/60">
                  <td className="px-5 py-3">
                    <Link to={`/requests/${r.id}`} className="font-medium hover:text-amber-500">
                      #{r.id} {r.service_type}
                    </Link>
                    <div className="text-slate-500 text-xs truncate max-w-md">{r.description}</div>
                  </td>
                  <td className="px-5 py-3"><PriorityLabel priority={r.priority} /></td>
                  <td className="px-5 py-3"><StatusBadge status={r.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
