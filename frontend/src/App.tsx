import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './hooks/useAuth'
import AppShell from './layouts/AppShell'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import ServiceRequestsList from './pages/ServiceRequestsList'
import ServiceRequestDetail from './pages/ServiceRequestDetail'
import KnowledgeAssistant from './pages/KnowledgeAssistant'
import Analytics from './pages/Analytics'
import TechniciansList from './pages/TechniciansList'
import CustomersList from './pages/CustomersList'
import InvoicesList from './pages/InvoicesList'

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()

  if (loading) return <div className="min-h-screen flex items-center justify-center text-slate-500">Loading…</div>
  if (!user) return <Navigate to="/login" replace />

  return <>{children}</>
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <AppShell />
          </ProtectedRoute>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="requests" element={<ServiceRequestsList />} />
        <Route path="requests/:id" element={<ServiceRequestDetail />} />
        <Route path="knowledge" element={<KnowledgeAssistant />} />
        <Route path="analytics" element={<Analytics />} />
        <Route path="technicians" element={<TechniciansList />} />
        <Route path="customers" element={<CustomersList />} />
        <Route path="invoices" element={<InvoicesList />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
