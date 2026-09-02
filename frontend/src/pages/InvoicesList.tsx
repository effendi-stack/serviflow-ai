import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useInvoices } from '../hooks/useInvoices'

const STATUS_OPTIONS = ['', 'draft', 'issued', 'paid', 'cancelled']

const STATUS_STYLES: Record<string, string> = {
  draft: 'bg-slate-700 text-slate-200',
  issued: 'bg-amber-500/20 text-amber-500',
  paid: 'bg-emerald-500/20 text-emerald-400',
  cancelled: 'bg-signal-red/20 text-signal-red',
}

export default function InvoicesList() {
  const [status, setStatus] = useState('')
  const { data: invoices, isLoading } = useInvoices(status || undefined)

  const totalOutstanding = (invoices ?? [])
    .filter((inv) => inv.status === 'issued')
    .reduce((sum, inv) => sum + inv.total, 0)

  return (
    <div className="p-4 md:p-8 max-w-4xl mx-auto">
      <header className="mb-6">
        <h1 className="font-display text-2xl font-bold">Invoices</h1>
        <p className="text-slate-500 text-sm mt-1">
          {totalOutstanding > 0 ? `RM ${totalOutstanding.toFixed(2)} outstanding across issued invoices.` : 'Billing history across all jobs.'}
        </p>
      </header>

      <select
        value={status}
        onChange={(e) => setStatus(e.target.value)}
        className="bg-slate-850 border border-slate-700 rounded-md px-3 py-2 text-sm mb-4"
      >
        {STATUS_OPTIONS.map((s) => (
          <option key={s} value={s}>
            {s === '' ? 'All statuses' : s}
          </option>
        ))}
      </select>

      {isLoading ? (
        <p className="text-slate-500 text-sm">Loading…</p>
      ) : !invoices || invoices.length === 0 ? (
        <p className="text-slate-500 text-sm">No invoices yet.</p>
      ) : (
        <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs font-mono uppercase text-slate-500 border-b border-slate-800">
              <th className="py-2">Customer</th>
              <th className="py-2">Total</th>
              <th className="py-2">Status</th>
              <th className="py-2">Issued</th>
              <th className="py-2">Paid</th>
            </tr>
          </thead>
          <tbody>
            {invoices.map((inv) => (
              <tr key={inv.id} className="border-b border-slate-850">
                <td className="py-2.5">
                  {inv.service_request_id ? (
                    <Link to={`/requests/${inv.service_request_id}`} className="text-signal-teal hover:underline">
                      {inv.customer_name ?? `Request #${inv.service_request_id}`}
                    </Link>
                  ) : (
                    inv.customer_name ?? '—'
                  )}
                </td>
                <td className="py-2.5 font-mono">RM {inv.total.toFixed(2)}</td>
                <td className="py-2.5">
                  <span className={`px-2 py-0.5 rounded text-xs font-mono uppercase tracking-wide ${STATUS_STYLES[inv.status] ?? ''}`}>
                    {inv.status}
                  </span>
                </td>
                <td className="py-2.5 text-slate-500 text-xs font-mono">
                  {inv.issued_at ? new Date(inv.issued_at).toLocaleDateString() : '—'}
                </td>
                <td className="py-2.5 text-slate-500 text-xs font-mono">
                  {inv.paid_at ? new Date(inv.paid_at).toLocaleDateString() : '—'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        </div>
      )}
    </div>
  )
}
