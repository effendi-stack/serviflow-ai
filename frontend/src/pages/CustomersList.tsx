import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useCustomers, useCustomer } from '../hooks/useCustomers'
import { StatusBadge } from '../components/StatusBadge'

export default function CustomersList() {
  const [search, setSearch] = useState('')
  const [expanded, setExpanded] = useState<number | null>(null)
  const { data: customers, isLoading } = useCustomers(search.trim() || undefined)

  return (
    <div className="p-4 md:p-8 max-w-4xl mx-auto">
      <header className="mb-6">
        <h1 className="font-display text-2xl font-bold">Customers</h1>
        <p className="text-slate-500 text-sm mt-1">Search by name, email, or phone.</p>
      </header>

      <input
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder="Search customers…"
        className="w-full bg-slate-850 border border-slate-700 rounded-md px-3 py-2 text-sm mb-4"
      />

      {isLoading ? (
        <p className="text-slate-500 text-sm">Loading…</p>
      ) : !customers || customers.length === 0 ? (
        <p className="text-slate-500 text-sm">No customers match that search.</p>
      ) : (
        <ul className="space-y-2">
          {customers.map((customer) => (
            <li key={customer.id} className="card p-4">
              <button
                className="w-full flex items-center justify-between text-left"
                onClick={() => setExpanded((cur) => (cur === customer.id ? null : customer.id))}
              >
                <div>
                  <div className="font-medium">{customer.name}</div>
                  <div className="text-slate-500 text-xs font-mono mt-0.5">
                    {customer.phone} · {customer.city ?? customer.address}
                  </div>
                </div>
                <span className="text-slate-400 font-mono text-xs">
                  {customer.service_requests_count ?? 0} request{(customer.service_requests_count ?? 0) === 1 ? '' : 's'}
                </span>
              </button>
              {expanded === customer.id && <CustomerDetail id={customer.id} />}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function CustomerDetail({ id }: { id: number }) {
  const { data: customer, isLoading } = useCustomer(id)

  if (isLoading || !customer) {
    return <p className="text-slate-500 text-xs mt-3">Loading details…</p>
  }

  return (
    <div className="mt-4 pt-4 border-t border-slate-800">
      <div className="text-xs font-mono uppercase text-slate-500 mb-2">Request history</div>
      {!customer.recent_requests || customer.recent_requests.length === 0 ? (
        <p className="text-slate-500 text-sm">No service requests yet.</p>
      ) : (
        <ul className="space-y-1.5">
          {customer.recent_requests.map((r) => (
            <li key={r.id} className="flex items-center justify-between text-sm">
              <Link to={`/requests/${r.id}`} className="text-signal-teal hover:underline">
                {r.service_type}
              </Link>
              <StatusBadge status={r.status} />
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
