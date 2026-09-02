import clsx from 'clsx'
import type { RequestStatus, Priority } from '../types'

const STATUS_COLORS: Record<RequestStatus, string> = {
  new: 'bg-slate-700 text-slate-200',
  analyzing: 'bg-signal-teal/20 text-signal-teal',
  ready_for_assignment: 'bg-signal-teal/20 text-signal-teal',
  assigned: 'bg-amber-500/20 text-amber-500',
  scheduled: 'bg-amber-500/20 text-amber-500',
  in_progress: 'bg-amber-500/30 text-amber-500',
  completed: 'bg-emerald-500/20 text-emerald-400',
  reviewed: 'bg-emerald-500/20 text-emerald-400',
  closed: 'bg-slate-800 text-slate-400',
  cancelled: 'bg-signal-red/20 text-signal-red',
}

export function StatusBadge({ status }: { status: RequestStatus }) {
  return (
    <span className={clsx('px-2 py-0.5 rounded text-xs font-mono uppercase tracking-wide', STATUS_COLORS[status])}>
      {status.replace(/_/g, ' ')}
    </span>
  )
}

const PRIORITY_COLORS: Record<Priority, string> = {
  low: 'text-slate-400',
  medium: 'text-amber-500',
  high: 'text-signal-red',
  critical: 'text-signal-red font-bold',
}

export function PriorityLabel({ priority }: { priority: Priority | null }) {
  if (!priority) return <span className="text-slate-600">—</span>
  return <span className={clsx('text-xs font-mono uppercase', PRIORITY_COLORS[priority])}>{priority}</span>
}
