import { useParams } from 'react-router-dom'
import { useState, useEffect, FormEvent } from 'react'
import {
  useServiceRequest,
  useRecommendedTechnicians,
  useAssignTechnician,
  useScheduleJob,
  useStartJob,
  useUpsertServiceNote,
  useGenerateReport,
  useFinalizeReport,
  useCompleteJob,
  useSubmitReview,
  useReviewAiAnalysis,
} from '../hooks/useServiceRequests'
import { useSaveInvoiceDraft, useIssueInvoice, usePayInvoice, useCancelInvoice } from '../hooks/useInvoices'
import { StatusBadge, PriorityLabel } from '../components/StatusBadge'
import { AiBadge } from '../components/AiBadge'
import { useAuth } from '../hooks/useAuth'
import type { ServiceRequest, Priority } from '../types'

const CATEGORIES = ['air_conditioning', 'plumbing', 'electrical', 'appliance_repair', 'pest_control', 'general_maintenance', 'other']
const PRIORITIES = ['low', 'medium', 'high', 'critical']

function errorMessage(err: any, field: string, fallback: string) {
  return err?.response?.data?.errors?.[field]?.[0] ?? err?.response?.data?.message ?? fallback
}

export default function ServiceRequestDetail() {
  const { id } = useParams()
  const requestId = Number(id)
  const { user } = useAuth()
  const { data: request, isLoading } = useServiceRequest(requestId)
  const [showRecommendations, setShowRecommendations] = useState(false)
  const { data: recommendations } = useRecommendedTechnicians(requestId, showRecommendations)
  const assign = useAssignTechnician(requestId)
  const schedule = useScheduleJob(requestId)
  const start = useStartJob(requestId)
  const upsertNote = useUpsertServiceNote(requestId)
  const generateReport = useGenerateReport(requestId)
  const finalizeReport = useFinalizeReport(requestId)
  const completeJob = useCompleteJob(requestId)
  const submitReview = useSubmitReview(requestId)
  const saveInvoiceDraft = useSaveInvoiceDraft(requestId)
  const issueInvoice = useIssueInvoice(requestId)
  const payInvoice = usePayInvoice(requestId)
  const cancelInvoice = useCancelInvoice(requestId)
  const [scheduledAt, setScheduledAt] = useState('')
  const [scheduleError, setScheduleError] = useState<string | null>(null)
  const [startError, setStartError] = useState<string | null>(null)
  const [rawNotes, setRawNotes] = useState('')
  const [photoInput, setPhotoInput] = useState('')
  const [photoPaths, setPhotoPaths] = useState<string[]>([])
  const [finalReport, setFinalReport] = useState('')
  const [workflowError, setWorkflowError] = useState<string | null>(null)
  const [rating, setRating] = useState(0)
  const [reviewComment, setReviewComment] = useState('')
  const [reviewError, setReviewError] = useState<string | null>(null)
  const [laborCost, setLaborCost] = useState('')
  const [partsCost, setPartsCost] = useState('')
  const [invoiceError, setInvoiceError] = useState<string | null>(null)

  const note = request?.service_note

  // Prefill the editable fields whenever the underlying note changes
  // (e.g. after a fresh AI draft comes back) -- but never clobber notes
  // the technician is actively editing that already match what's saved.
  useEffect(() => {
    if (!note) return
    setRawNotes((prev) => (prev === '' ? note.raw_notes : prev))
    setPhotoPaths((prev) => (prev.length === 0 ? note.photo_paths : prev))
    setFinalReport((prev) => (prev === '' ? (note.final_report ?? note.ai_generated_report ?? '') : prev))
  }, [note])

  useEffect(() => {
    if (request?.invoice && request.invoice.status === 'draft') {
      setLaborCost((prev) => (prev === '' ? String(request.invoice!.labor_cost) : prev))
      setPartsCost((prev) => (prev === '' ? String(request.invoice!.parts_cost) : prev))
    }
  }, [request?.invoice])

  if (isLoading || !request) {
    return <div className="p-4 md:p-8 text-slate-500 text-sm">Loading…</div>
  }

  const canAssign = user?.role === 'admin' && request.status === 'ready_for_assignment' && !!request.required_skill
  const needsTriage = user?.role === 'admin' && request.status === 'ready_for_assignment' && !request.required_skill
  const canSchedule = user?.role === 'admin' && request.status === 'assigned'
  const isMyJob = user?.role === 'technician' && request.assignment?.technician.id === user.technician_id
  const canStart = isMyJob && request.status === 'scheduled'
  const canWorkJob = isMyJob && request.status === 'in_progress'
  const isFinalized = note?.is_finalized ?? false
  const canReview = user?.role === 'customer' && request.status === 'completed' && !request.review
  const canManageInvoice = user?.role === 'admin' && ['completed', 'reviewed', 'closed'].includes(request.status)

  function handleSchedule() {
    if (!scheduledAt) return
    setScheduleError(null)
    schedule.mutate(new Date(scheduledAt).toISOString(), {
      onError: (err: any) => setScheduleError(errorMessage(err, 'scheduled_at', 'Could not schedule this appointment.')),
    })
  }

  function handleStart() {
    setStartError(null)
    start.mutate(undefined, {
      onError: (err: any) => setStartError(errorMessage(err, 'status', 'Could not start this job.')),
    })
  }

  function handleSaveNotes() {
    if (!rawNotes.trim()) return
    setWorkflowError(null)
    upsertNote.mutate(
      { rawNotes, photoPaths },
      { onError: (err: any) => setWorkflowError(errorMessage(err, 'raw_notes', 'Could not save notes.')) },
    )
  }

  function handleGenerateReport() {
    setWorkflowError(null)
    generateReport.mutate(undefined, {
      onError: (err: any) => setWorkflowError(errorMessage(err, 'raw_notes', 'Could not generate a report.')),
    })
  }

  function handleFinalize() {
    if (!finalReport.trim()) return
    setWorkflowError(null)
    finalizeReport.mutate(finalReport, {
      onError: (err: any) => setWorkflowError(errorMessage(err, 'final_report', 'Could not finalize this report.')),
    })
  }

  function handleComplete() {
    setWorkflowError(null)
    completeJob.mutate(undefined, {
      onError: (err: any) => setWorkflowError(errorMessage(err, 'status', 'Could not complete this job.')),
    })
  }

  function handleSubmitReview() {
    if (!rating) return
    setReviewError(null)
    submitReview.mutate(
      { rating, comment: reviewComment.trim() || undefined },
      { onError: (err: any) => setReviewError(errorMessage(err, 'rating', 'Could not submit your review.')) },
    )
  }

  function handleSaveInvoiceDraft() {
    const labor = Number(laborCost)
    const parts = Number(partsCost)
    if (Number.isNaN(labor) || Number.isNaN(parts)) return
    setInvoiceError(null)
    saveInvoiceDraft.mutate(
      { laborCost: labor, partsCost: parts },
      { onError: (err: any) => setInvoiceError(errorMessage(err, 'labor_cost', 'Could not save this invoice.')) },
    )
  }

  function handleIssueInvoice() {
    setInvoiceError(null)
    issueInvoice.mutate(undefined, {
      onError: (err: any) => setInvoiceError(errorMessage(err, 'status', 'Could not issue this invoice.')),
    })
  }

  function handlePayInvoice() {
    setInvoiceError(null)
    payInvoice.mutate(undefined, {
      onError: (err: any) => setInvoiceError(errorMessage(err, 'status', 'Could not mark this invoice paid.')),
    })
  }

  function handleCancelInvoice() {
    setInvoiceError(null)
    cancelInvoice.mutate(undefined, {
      onError: (err: any) => setInvoiceError(errorMessage(err, 'status', 'Could not cancel this invoice.')),
    })
  }

  function addPhoto() {
    if (!photoInput.trim()) return
    setPhotoPaths((paths) => [...paths, photoInput.trim()])
    setPhotoInput('')
  }

  return (
    <div className="p-4 md:p-8 max-w-4xl mx-auto space-y-6">
      <header>
        <div className="flex items-center gap-3">
          <h1 className="font-display text-2xl font-bold">
            #{request.id} — {request.service_type}
          </h1>
          <StatusBadge status={request.status} />
        </div>
        <p className="text-slate-400 mt-2">{request.description}</p>
        <p className="text-slate-600 text-sm mt-1 font-mono">{request.address}</p>
      </header>

      {needsTriage && <TriageForm request={request} requestId={requestId} />}

      {!needsTriage && (request.ai_analysis || request.required_skill) && (
        <section className="card p-5">
          <div className="flex items-center gap-2 mb-3">
            <AiBadge>{request.ai_analysis?.accepted_by_admin === false ? 'AI triage — overridden' : 'AI triage'}</AiBadge>
            {request.ai_analysis && (
              <span className="text-xs text-slate-500 font-mono">
                confidence {Math.round(request.ai_analysis.confidence * 100)}%
              </span>
            )}
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <Field label="Category" value={request.category ?? request.ai_analysis?.category} />
            <Field label="Priority">
              <PriorityLabel priority={(request.priority ?? request.ai_analysis?.priority) as any} />
            </Field>
            <Field label="Est. duration" value={`${request.estimated_duration_minutes ?? request.ai_analysis?.estimated_duration_minutes} min`} />
            <Field label="Required skill" value={request.required_skill ?? request.ai_analysis?.required_skill} />
          </div>
        </section>
      )}

      {request.assignment ? (
        <section className="card p-5">
          <h2 className="font-display font-semibold mb-3">Assigned technician</h2>
          <div className="flex items-center justify-between">
            <div>
              <div className="font-medium">{request.assignment.technician.name}</div>
              <div className="text-slate-500 text-xs">{request.assignment.technician.skills.join(', ')}</div>
            </div>
            {request.assignment.was_ai_recommended && (
              <AiBadge>AI-recommended · {request.assignment.ai_rank_score?.toFixed(0)}/100</AiBadge>
            )}
          </div>
          {request.assignment.ai_rationale && (
            <p className="text-slate-500 text-xs mt-2 italic">"{request.assignment.ai_rationale}"</p>
          )}
          {request.scheduled_at && (
            <p className="text-slate-400 text-sm mt-3 font-mono">
              Scheduled for {new Date(request.scheduled_at).toLocaleString()}
            </p>
          )}
          {canStart && (
            <div className="mt-4 pt-4 border-t border-slate-800">
              <button className="btn-primary text-sm" disabled={start.isPending} onClick={handleStart}>
                {start.isPending ? 'Starting…' : 'Start job'}
              </button>
              {startError && <p className="text-signal-red text-xs mt-2">{startError}</p>}
            </div>
          )}
        </section>
      ) : (
        canAssign && (
          <section className="card p-5">
            <div className="flex items-center justify-between mb-3">
              <h2 className="font-display font-semibold">Assign a technician</h2>
              {!showRecommendations && (
                <button className="btn-secondary text-sm" onClick={() => setShowRecommendations(true)}>
                  Get AI recommendations
                </button>
              )}
            </div>
            {recommendations && (
              <div className="space-y-2">
                {recommendations.map((rec) => (
                  <div
                    key={rec.technician.id}
                    className="flex items-center justify-between border border-slate-800 rounded-md px-4 py-3"
                  >
                    <div>
                      <div className="font-medium flex items-center gap-2">
                        {rec.technician.name}
                        <AiBadge>{rec.score.toFixed(0)}/100</AiBadge>
                      </div>
                      <div className="text-slate-500 text-xs mt-0.5">{rec.rationale}</div>
                    </div>
                    <button
                      className="btn-primary text-sm"
                      disabled={assign.isPending}
                      onClick={() =>
                        assign.mutate({
                          technicianId: rec.technician.id,
                          aiMeta: {
                            was_ai_recommended: true,
                            ai_score: rec.score,
                            ai_rationale: rec.rationale,
                          },
                        })
                      }
                    >
                      Assign
                    </button>
                  </div>
                ))}
              </div>
            )}
          </section>
        )
      )}

      {canSchedule && (
        <section className="card p-5">
          <h2 className="font-display font-semibold mb-3">Schedule appointment</h2>
          <div className="flex items-center gap-3">
            <input
              type="datetime-local"
              className="bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
              value={scheduledAt}
              min={new Date().toISOString().slice(0, 16)}
              onChange={(e) => setScheduledAt(e.target.value)}
            />
            <button
              className="btn-primary text-sm"
              disabled={!scheduledAt || schedule.isPending}
              onClick={handleSchedule}
            >
              {schedule.isPending ? 'Scheduling…' : 'Confirm schedule'}
            </button>
          </div>
          {scheduleError && <p className="text-signal-red text-xs mt-2">{scheduleError}</p>}
        </section>
      )}

      {canWorkJob && (
        <section className="card p-5 space-y-4">
          <h2 className="font-display font-semibold">Job report</h2>

          <div>
            <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Field notes</label>
            <textarea
              className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
              rows={4}
              value={rawNotes}
              disabled={isFinalized}
              onChange={(e) => setRawNotes(e.target.value)}
              placeholder="What did you find, what did you do, what's the outcome?"
            />
          </div>

          <div>
            <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Photos</label>
            <div className="flex gap-2">
              <input
                className="flex-1 bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
                value={photoInput}
                disabled={isFinalized}
                onChange={(e) => setPhotoInput(e.target.value)}
                placeholder="photo path or URL"
              />
              <button className="btn-secondary text-sm" disabled={isFinalized} onClick={addPhoto}>
                Add
              </button>
            </div>
            {photoPaths.length > 0 && (
              <ul className="mt-2 text-xs text-slate-400 font-mono space-y-1">
                {photoPaths.map((p, i) => (
                  <li key={i}>{p}</li>
                ))}
              </ul>
            )}
          </div>

          {!isFinalized && (
            <button className="btn-secondary text-sm" disabled={!rawNotes.trim() || upsertNote.isPending} onClick={handleSaveNotes}>
              {upsertNote.isPending ? 'Saving…' : 'Save notes'}
            </button>
          )}

          {note && !isFinalized && (
            <div className="pt-4 border-t border-slate-800 space-y-3">
              <div className="flex items-center justify-between">
                <label className="block text-xs font-mono uppercase text-slate-500">AI draft report</label>
                <button
                  className="btn-secondary text-xs"
                  disabled={generateReport.isPending}
                  onClick={handleGenerateReport}
                >
                  {generateReport.isPending ? 'Generating…' : note.ai_generated_report ? 'Regenerate' : 'Generate AI draft'}
                </button>
              </div>
              {note.ai_generated_report && <AiBadge>AI-drafted — review before finalizing</AiBadge>}
              <textarea
                className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
                rows={4}
                value={finalReport}
                onChange={(e) => setFinalReport(e.target.value)}
                placeholder="Edit the AI draft above, or write the final report yourself."
              />
              <button className="btn-primary text-sm" disabled={!finalReport.trim() || finalizeReport.isPending} onClick={handleFinalize}>
                {finalizeReport.isPending ? 'Finalizing…' : 'Finalize report'}
              </button>
            </div>
          )}

          {isFinalized && (
            <div className="pt-4 border-t border-slate-800 space-y-3">
              <label className="block text-xs font-mono uppercase text-slate-500">Final report</label>
              <p className="text-sm text-slate-300 whitespace-pre-wrap">{note?.final_report}</p>
              <button className="btn-primary text-sm" disabled={completeJob.isPending} onClick={handleComplete}>
                {completeJob.isPending ? 'Completing…' : 'Complete job'}
              </button>
            </div>
          )}

          {workflowError && <p className="text-signal-red text-xs">{workflowError}</p>}
        </section>
      )}

      {note && !canWorkJob && (note.final_report || note.raw_notes) && (
        <section className="card p-5">
          <h2 className="font-display font-semibold mb-3">Service report</h2>
          <p className="text-sm text-slate-300 whitespace-pre-wrap">{note.final_report ?? note.raw_notes}</p>
          {note.photo_paths.length > 0 && (
            <ul className="mt-3 text-xs text-slate-500 font-mono space-y-1">
              {note.photo_paths.map((p, i) => (
                <li key={i}>{p}</li>
              ))}
            </ul>
          )}
        </section>
      )}

      {canReview && (
        <section className="card p-5 space-y-3">
          <h2 className="font-display font-semibold">Rate this service</h2>
          <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map((n) => (
              <button
                key={n}
                type="button"
                onClick={() => setRating(n)}
                className={`text-2xl leading-none ${n <= rating ? 'text-amber-500' : 'text-slate-700'}`}
                aria-label={`${n} star${n > 1 ? 's' : ''}`}
              >
                ★
              </button>
            ))}
          </div>
          <textarea
            className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
            rows={3}
            value={reviewComment}
            onChange={(e) => setReviewComment(e.target.value)}
            placeholder="How did it go? (optional)"
          />
          <button className="btn-primary text-sm" disabled={!rating || submitReview.isPending} onClick={handleSubmitReview}>
            {submitReview.isPending ? 'Submitting…' : 'Submit review'}
          </button>
          {reviewError && <p className="text-signal-red text-xs">{reviewError}</p>}
        </section>
      )}

      {request.review && (
        <section className="card p-5">
          <h2 className="font-display font-semibold mb-3">Your review</h2>
          <div className="text-amber-500 text-lg leading-none">{'★'.repeat(request.review.rating)}{'☆'.repeat(5 - request.review.rating)}</div>
          {request.review.comment && <p className="text-slate-300 text-sm mt-2">{request.review.comment}</p>}
        </section>
      )}

      {(request.invoice || canManageInvoice) && (
        <section className="card p-5">
          <h2 className="font-display font-semibold mb-3">Invoice</h2>

          {request.invoice && (
            <>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm mb-2">
                <Field label="Labor" value={`RM ${request.invoice.labor_cost.toFixed(2)}`} />
                <Field label="Parts" value={`RM ${request.invoice.parts_cost.toFixed(2)}`} />
                <Field label="Total" value={`RM ${request.invoice.total.toFixed(2)}`} />
              </div>
              <InvoiceStatusBadge status={request.invoice.status} />
            </>
          )}

          {canManageInvoice && (!request.invoice || request.invoice.status === 'draft') && (
            <div className="mt-4 pt-4 border-t border-slate-800 space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Labor cost (RM)</label>
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    value={laborCost}
                    onChange={(e) => setLaborCost(e.target.value)}
                    className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Parts cost (RM)</label>
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    value={partsCost}
                    onChange={(e) => setPartsCost(e.target.value)}
                    className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
                  />
                </div>
              </div>
              <div className="flex gap-3">
                <button
                  className="btn-primary text-sm"
                  disabled={laborCost === '' || partsCost === '' || saveInvoiceDraft.isPending}
                  onClick={handleSaveInvoiceDraft}
                >
                  {saveInvoiceDraft.isPending ? 'Saving…' : request.invoice ? 'Update draft' : 'Save draft'}
                </button>
                {request.invoice && (
                  <button className="btn-secondary text-sm" disabled={issueInvoice.isPending} onClick={handleIssueInvoice}>
                    {issueInvoice.isPending ? 'Issuing…' : 'Issue invoice'}
                  </button>
                )}
              </div>
            </div>
          )}

          {canManageInvoice && request.invoice?.status === 'issued' && (
            <div className="mt-4 pt-4 border-t border-slate-800 flex gap-3">
              <button className="btn-primary text-sm" disabled={payInvoice.isPending} onClick={handlePayInvoice}>
                {payInvoice.isPending ? 'Marking paid…' : 'Mark paid'}
              </button>
              <button className="text-signal-red text-sm" disabled={cancelInvoice.isPending} onClick={handleCancelInvoice}>
                Cancel invoice
              </button>
            </div>
          )}

          {canManageInvoice && request.invoice?.status === 'draft' && (
            <div className="mt-3">
              <button className="text-signal-red text-xs" disabled={cancelInvoice.isPending} onClick={handleCancelInvoice}>
                Cancel invoice
              </button>
            </div>
          )}

          {invoiceError && <p className="text-signal-red text-xs mt-2">{invoiceError}</p>}
        </section>
      )}
    </div>
  )
}

function Field({ label, value, children }: { label: string; value?: string; children?: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs font-mono uppercase text-slate-500">{label}</div>
      <div className="mt-0.5">{children ?? value}</div>
    </div>
  )
}

const INVOICE_STATUS_STYLES: Record<string, string> = {
  draft: 'bg-slate-700 text-slate-200',
  issued: 'bg-amber-500/20 text-amber-500',
  paid: 'bg-emerald-500/20 text-emerald-400',
  cancelled: 'bg-signal-red/20 text-signal-red',
}

function InvoiceStatusBadge({ status }: { status: string }) {
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-mono uppercase tracking-wide ${INVOICE_STATUS_STYLES[status] ?? ''}`}>
      {status}
    </span>
  )
}

function TriageForm({ request, requestId }: { request: ServiceRequest; requestId: number }) {
  const review = useReviewAiAnalysis(requestId)
  const ai = request.ai_analysis
  const [category, setCategory] = useState(ai?.category ?? CATEGORIES[0])
  const [priority, setPriority] = useState<Priority>(ai?.priority ?? 'medium')
  const [requiredSkill, setRequiredSkill] = useState(ai?.required_skill ?? '')
  const [duration, setDuration] = useState(ai?.estimated_duration_minutes ?? 60)
  const [error, setError] = useState<string | null>(null)

  function handleSubmit(e: FormEvent) {
    e.preventDefault()
    if (!requiredSkill.trim()) return
    setError(null)
    review.mutate(
      { category, priority, required_skill: requiredSkill.trim(), estimated_duration_minutes: duration },
      { onError: (err: any) => setError(errorMessage(err, 'required_skill', 'Could not save triage.')) },
    )
  }

  return (
    <section className="card p-5">
      <div className="flex items-center gap-2 mb-3">
        <AiBadge>{ai ? 'Review AI triage' : 'Manual triage — AI classification unavailable'}</AiBadge>
        {ai && <span className="text-xs text-slate-500 font-mono">confidence {Math.round(ai.confidence * 100)}%</span>}
      </div>
      {!ai && (
        <p className="text-slate-500 text-xs mb-3">
          The AI wasn't able to classify this request. Fill in the details below to route it to the right technician.
        </p>
      )}
      <form onSubmit={handleSubmit} className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
          <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Category</label>
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2"
          >
            {CATEGORIES.map((c) => (
              <option key={c} value={c}>
                {c.replace(/_/g, ' ')}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Priority</label>
          <select
            value={priority}
            onChange={(e) => setPriority(e.target.value as Priority)}
            className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2"
          >
            {PRIORITIES.map((p) => (
              <option key={p} value={p}>
                {p}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Required skill</label>
          <input
            value={requiredSkill}
            onChange={(e) => setRequiredSkill(e.target.value)}
            placeholder="ac_repair"
            className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2"
            required
          />
        </div>
        <div>
          <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Est. duration (min)</label>
          <input
            type="number"
            min={15}
            max={480}
            value={duration}
            onChange={(e) => setDuration(Number(e.target.value))}
            className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2"
          />
        </div>
        <div className="col-span-2">
          <button type="submit" className="btn-primary text-sm" disabled={!requiredSkill.trim() || review.isPending}>
            {review.isPending ? 'Saving…' : ai ? 'Confirm classification' : 'Save classification'}
          </button>
          {error && <p className="text-signal-red text-xs mt-2">{error}</p>}
        </div>
      </form>
    </section>
  )
}
