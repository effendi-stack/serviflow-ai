import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTechnicians, useTechnician, useUpdateTechnicianStatus } from '../hooks/useTechnicians'
import { StatusBadge } from '../components/StatusBadge'

const STATUS_OPTIONS = ['', 'available', 'on_job', 'off_duty']

export default function TechniciansList() {
  const [skill, setSkill] = useState('')
  const [status, setStatus] = useState('')
  const [expanded, setExpanded] = useState<number | null>(null)
  const { data: technicians, isLoading } = useTechnicians({
    skill: skill.trim() || undefined,
    status: status || undefined,
  })

  return (
    <div className="p-4 md:p-8 max-w-4xl mx-auto">
      <header className="mb-6">
        <h1 className="font-display text-2xl font-bold">Technicians</h1>
        <p className="text-slate-500 text-sm mt-1">Skills, availability, and current workload across the team.</p>
      </header>

      <div className="flex gap-3 mb-4">
        <input
          value={skill}
          onChange={(e) => setSkill(e.target.value)}
          placeholder="Filter by skill (e.g. ac_repair)"
          className="bg-slate-850 border border-slate-700 rounded-md px-3 py-2 text-sm flex-1"
        />
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="bg-slate-850 border border-slate-700 rounded-md px-3 py-2 text-sm"
        >
          {STATUS_OPTIONS.map((s) => (
            <option key={s} value={s}>
              {s === '' ? 'All statuses' : s.replace(/_/g, ' ')}
            </option>
          ))}
        </select>
      </div>

      {isLoading ? (
        <p className="text-slate-500 text-sm">Loading…</p>
      ) : !technicians || technicians.length === 0 ? (
        <p className="text-slate-500 text-sm">No technicians match those filters.</p>
      ) : (
        <ul className="space-y-2">
          {technicians.map((tech) => (
            <li key={tech.id} className="card p-4">
              <button
                className="w-full flex items-center justify-between text-left"
                onClick={() => setExpanded((cur) => (cur === tech.id ? null : tech.id))}
              >
                <div>
                  <div className="font-medium">{tech.name}</div>
                  <div className="text-slate-500 text-xs font-mono mt-0.5">{tech.skills.join(', ')}</div>
                </div>
                <div className="flex items-center gap-4 text-sm">
                  <span className="text-slate-400 font-mono text-xs">
                    {tech.active_jobs_count ?? 0} active job{(tech.active_jobs_count ?? 0) === 1 ? '' : 's'}
                  </span>
                  <TechnicianStatusBadge status={tech.status} />
                </div>
              </button>
              {expanded === tech.id && <TechnicianDetail id={tech.id} />}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function TechnicianStatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    available: 'bg-emerald-500/20 text-emerald-400',
    on_job: 'bg-amber-500/20 text-amber-500',
    off_duty: 'bg-slate-700 text-slate-400',
  }
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-mono uppercase tracking-wide ${styles[status] ?? ''}`}>
      {status.replace(/_/g, ' ')}
    </span>
  )
}

function TechnicianDetail({ id }: { id: number }) {
  const { data: technician, isLoading } = useTechnician(id)
  const updateStatus = useUpdateTechnicianStatus()
  const [error, setError] = useState<string | null>(null)

  function toggle(next: 'available' | 'off_duty') {
    setError(null)
    updateStatus.mutate(
      { id, status: next },
      { onError: (err: any) => setError(err?.response?.data?.errors?.status?.[0] ?? 'Could not update status.') },
    )
  }

  if (isLoading || !technician) {
    return <p className="text-slate-500 text-xs mt-3">Loading details…</p>
  }

  return (
    <div className="mt-4 pt-4 border-t border-slate-800">
      {technician.status !== 'on_job' && (
        <div className="flex gap-2 mb-4">
          {technician.status !== 'available' && (
            <button className="btn-secondary text-xs" disabled={updateStatus.isPending} onClick={() => toggle('available')}>
              Mark available
            </button>
          )}
          {technician.status !== 'off_duty' && (
            <button className="btn-secondary text-xs" disabled={updateStatus.isPending} onClick={() => toggle('off_duty')}>
              Take off duty
            </button>
          )}
        </div>
      )}
      {error && <p className="text-signal-red text-xs mb-3">{error}</p>}

      <div className="text-xs font-mono uppercase text-slate-500 mb-2">Recent jobs</div>
      {!technician.recent_jobs || technician.recent_jobs.length === 0 ? (
        <p className="text-slate-500 text-sm">No job history yet.</p>
      ) : (
        <ul className="space-y-1.5">
          {technician.recent_jobs.map((job) => (
            <li key={job.service_request_id} className="flex items-center justify-between text-sm">
              <Link to={`/requests/${job.service_request_id}`} className="text-signal-teal hover:underline">
                {job.service_type ?? `Request #${job.service_request_id}`}
              </Link>
              {job.status && <StatusBadge status={job.status} />}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
