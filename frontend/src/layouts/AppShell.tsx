import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import clsx from 'clsx'

const NAV = [
  { to: '/', label: 'Dashboard', roles: ['admin', 'technician', 'customer'] },
  { to: '/requests', label: 'Service Requests', roles: ['admin', 'technician', 'customer'] },
  { to: '/technicians', label: 'Technicians', roles: ['admin'] },
  { to: '/customers', label: 'Customers', roles: ['admin'] },
  { to: '/invoices', label: 'Invoices', roles: ['admin'] },
  { to: '/knowledge', label: 'Knowledge Assistant', roles: ['admin', 'technician'] },
  { to: '/analytics', label: 'Analytics', roles: ['admin'] },
]

export default function AppShell() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)

  async function handleLogout() {
    await logout()
    navigate('/login')
  }

  const visibleNav = NAV.filter((item) => !user || item.roles.includes(user.role))

  return (
    <div className="min-h-screen flex flex-col md:flex-row">
      {/* Mobile top bar */}
      <header className="md:hidden flex items-center justify-between px-4 py-3 border-b border-slate-800 bg-slate-900">
        <div>
          <div className="font-display font-bold text-lg tracking-tight text-amber-500">ServiFlow</div>
        </div>
        <button
          onClick={() => setMenuOpen(true)}
          className="text-slate-300 p-2 -mr-2"
          aria-label="Open menu"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M3 6h18M3 12h18M3 18h18" strokeLinecap="round" />
          </svg>
        </button>
      </header>

      {/* Mobile drawer backdrop */}
      {menuOpen && (
        <div className="md:hidden fixed inset-0 bg-black/60 z-40" onClick={() => setMenuOpen(false)} />
      )}

      <aside
        className={clsx(
          'w-64 shrink-0 border-r border-slate-800 bg-slate-900 flex flex-col z-50',
          'fixed inset-y-0 left-0 transform transition-transform md:static md:transform-none',
          menuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        )}
      >
        <div className="px-5 py-5 border-b border-slate-800 flex items-center justify-between">
          <div>
            <div className="font-display font-bold text-lg tracking-tight text-amber-500">ServiFlow</div>
            <div className="text-xs text-slate-500 font-mono">AI field ops</div>
          </div>
          <button onClick={() => setMenuOpen(false)} className="md:hidden text-slate-500 p-1" aria-label="Close menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 6L6 18M6 6l12 12" strokeLinecap="round" />
            </svg>
          </button>
        </div>
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          {visibleNav.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              onClick={() => setMenuOpen(false)}
              className={({ isActive }) =>
                clsx(
                  'block px-3 py-2 rounded-md text-sm font-medium transition-colors',
                  isActive ? 'bg-slate-800 text-amber-500' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-850',
                )
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
        <div className="px-5 py-4 border-t border-slate-800 text-sm">
          <div className="text-slate-200 font-medium">{user?.name}</div>
          <div className="text-slate-500 text-xs uppercase tracking-wide font-mono">{user?.role}</div>
          <button onClick={handleLogout} className="mt-3 text-slate-500 hover:text-signal-red text-xs">
            Sign out
          </button>
        </div>
      </aside>
      <main className="flex-1 overflow-y-auto min-w-0">
        <Outlet />
      </main>
    </div>
  )
}
