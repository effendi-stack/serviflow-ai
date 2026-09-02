import { useState, FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { useServiceRequests, useCreateServiceRequest } from '../hooks/useServiceRequests'
import { StatusBadge, PriorityLabel } from '../components/StatusBadge'
import { useAuth } from '../hooks/useAuth'

export default function ServiceRequestsList() {
  const { user } = useAuth()
  const [statusFilter, setStatusFilter] = useState<string>('')
  const { data: requests, isLoading } = useServiceRequests(statusFilter ? { status: statusFilter } : undefined)
  const [showForm, setShowForm] = useState(false)

  return (
    <div className="p-4 md:p-8 max-w-6xl mx-auto">
      <header className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold">Service Requests</h1>
          <p className="text-slate-500 text-sm mt-1">Every request, from intake to closed.</p>
        </div>
        {user?.role === 'customer' && (
          <button className="btn-primary" onClick={() => setShowForm((s) => !s)}>
            {showForm ? 'Cancel' : 'New request'}
          </button>
        )}
      </header>

      {showForm && <NewRequestForm onDone={() => setShowForm(false)} />}

      <div className="flex gap-2 mb-4">
        {['', 'new', 'ready_for_assignment', 'assigned', 'in_progress', 'completed', 'closed'].map((s) => (
          <button
            key={s}
            onClick={() => setStatusFilter(s)}
            className={`text-xs font-mono px-3 py-1.5 rounded-md border ${
              statusFilter === s
                ? 'bg-amber-500 text-slate-950 border-amber-500'
                : 'border-slate-700 text-slate-400 hover:text-slate-100'
            }`}
          >
            {s === '' ? 'all' : s.replace(/_/g, ' ')}
          </button>
        ))}
      </div>

      <div className="card overflow-hidden">
        {isLoading ? (
          <div className="p-5 text-slate-500 text-sm">Loading…</div>
        ) : requests?.length === 0 ? (
          <div className="p-4 md:p-8 text-center text-slate-500 text-sm">No requests match this filter yet.</div>
        ) : (
          <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs font-mono uppercase text-slate-500 border-b border-slate-800">
                <th className="px-5 py-3 font-medium">Request</th>
                <th className="px-5 py-3 font-medium">Priority</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3 font-medium">Technician</th>
              </tr>
            </thead>
            <tbody>
              {requests?.map((r) => (
                <tr key={r.id} className="border-b border-slate-850 last:border-0 hover:bg-slate-850/60">
                  <td className="px-5 py-3">
                    <Link to={`/requests/${r.id}`} className="font-medium hover:text-amber-500">
                      #{r.id} {r.service_type}
                    </Link>
                  </td>
                  <td className="px-5 py-3"><PriorityLabel priority={r.priority} /></td>
                  <td className="px-5 py-3"><StatusBadge status={r.status} /></td>
                  <td className="px-5 py-3 text-slate-400">{r.assignment?.technician.name ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
          </div>
        )}
      </div>
    </div>
  )
}

function NewRequestForm({ onDone }: { onDone: () => void }) {
  const create = useCreateServiceRequest()
  const [serviceType, setServiceType] = useState('Air Conditioning')
  const [description, setDescription] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      await create.mutateAsync({ service_type: serviceType, description })
      setDescription('')
      onDone()
    } catch (err: any) {
      setError(err?.response?.data?.errors?.description?.[0] ?? 'Could not submit this request. Please try again.')
    }
  }

  return (
    <form onSubmit={handleSubmit} className="card p-5 mb-6 space-y-3">
      <div>
        <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Service type</label>
        <select
          value={serviceType}
          onChange={(e) => setServiceType(e.target.value)}
          className="w-full bg-slate-850 border border-slate-700 rounded-md px-3 py-2 text-sm"
        >
          <option>Air Conditioning</option>
          <option>Plumbing</option>
          <option>Electrical</option>
          <option>Appliance Repair</option>
        </select>
      </div>
      <div>
        <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Describe the problem</label>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          rows={3}
          className="w-full bg-slate-850 border border-slate-700 rounded-md px-3 py-2 text-sm"
          placeholder="My air conditioner isn't cold and water is dripping from the indoor unit."
          required
        />
      </div>
      <button type="submit" disabled={create.isPending} className="btn-primary">
        {create.isPending ? 'Submitting…' : 'Submit request'}
      </button>
      {error && <p className="text-signal-red text-xs">{error}</p>}
    </form>
  )
}
