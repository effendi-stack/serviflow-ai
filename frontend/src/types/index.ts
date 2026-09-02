export type Role = 'admin' | 'technician' | 'customer'

export type RequestStatus =
  | 'new'
  | 'analyzing'
  | 'ready_for_assignment'
  | 'assigned'
  | 'scheduled'
  | 'in_progress'
  | 'completed'
  | 'reviewed'
  | 'closed'
  | 'cancelled'

export type Priority = 'low' | 'medium' | 'high' | 'critical'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  technician_id?: number | null
  customer_id?: number | null
}

export interface Customer {
  id: number
  name: string
  phone: string | null
  email?: string
  address: string
  city: string | null
  service_requests_count?: number
  recent_requests?: Array<{ id: number; service_type: string; status: RequestStatus; created_at: string }>
}

export interface Technician {
  id: number
  name: string
  skills: string[]
  status: 'available' | 'on_job' | 'off_duty'
  rating_avg: number
  active_jobs_count?: number
  recent_jobs?: Array<{
    service_request_id: number
    service_type: string | null
    status: RequestStatus | null
    scheduled_at: string | null
  }>
}

export interface AiAnalysis {
  category: string
  priority: Priority
  issue: string
  estimated_duration_minutes: number
  required_skill: string
  confidence: number
  accepted_by_admin: boolean | null
}

export interface Assignment {
  id: number
  technician: Technician
  status: string
  was_ai_recommended: boolean
  ai_rank_score: number | null
  ai_rationale: string | null
}

export interface Invoice {
  id: number
  status: 'draft' | 'issued' | 'paid' | 'cancelled'
  labor_cost: number
  parts_cost: number
  total: number
  issued_at: string | null
  paid_at: string | null
  service_request_id?: number
  customer_name?: string | null
}

export interface ServiceNote {
  id: number
  raw_notes: string
  ai_generated_report: string | null
  final_report: string | null
  photo_paths: string[]
  is_finalized: boolean
  finalized_at: string | null
  created_at: string
  updated_at: string
}

export interface StatusHistoryEntry {
  id: number
  from_status: RequestStatus | null
  to_status: RequestStatus
  note: string | null
  created_at: string
}

export interface Review {
  id: number
  rating: number
  comment: string | null
  created_at: string
}

export interface ServiceRequest {
  id: number
  service_type: string
  description: string
  status: RequestStatus
  category: string | null
  priority: Priority | null
  required_skill: string | null
  estimated_duration_minutes: number | null
  preferred_date: string | null
  preferred_time: string | null
  scheduled_at: string | null
  address: string
  customer?: Customer
  ai_analysis?: AiAnalysis
  assignment?: Assignment
  invoice?: Invoice
  service_note?: ServiceNote
  status_history?: StatusHistoryEntry[]
  review?: Review
  created_at: string
  updated_at: string
}

export interface RecommendedTechnician {
  technician: Technician
  score: number
  rationale: string
}

export interface KnowledgeAnswer {
  answer: string
  sources: Array<{
    document_id: number
    document_title: string
    page_number: number | null
    excerpt: string
  }>
}

export interface KnowledgeDocument {
  id: number
  title: string
  category: string | null
  status: 'uploaded' | 'processing' | 'indexed' | 'failed'
  page_count: number | null
  processing_error: string | null
  chunk_count: number | null
  created_at: string
}

export interface AnalyticsSummary {
  total_jobs: number
  by_status: Record<string, number>
  average_completion_minutes: number | null
  average_customer_rating: number | null
  requests_per_month: Record<string, number>
}
