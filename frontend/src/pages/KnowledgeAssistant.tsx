import { useState, FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { askKnowledgeBase } from '../services/knowledge'
import { useDocuments, useUploadDocument, useDeleteDocument, useReindexDocument } from '../hooks/useKnowledge'
import { useAuth } from '../hooks/useAuth'
import { AiBadge } from '../components/AiBadge'
import type { KnowledgeAnswer } from '../types'

const CATEGORIES = ['manual', 'error_codes', 'sop', 'warranty', 'troubleshooting']

const STATUS_STYLES: Record<string, string> = {
  uploaded: 'text-slate-400',
  processing: 'text-amber-500',
  indexed: 'text-signal-teal',
  failed: 'text-signal-red',
}

export default function KnowledgeAssistant() {
  const { user } = useAuth()
  const [question, setQuestion] = useState('')
  const [history, setHistory] = useState<Array<{ question: string; answer: KnowledgeAnswer }>>([])
  const ask = useMutation({ mutationFn: askKnowledgeBase })

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    if (!question.trim()) return
    const answer = await ask.mutateAsync(question)
    setHistory((h) => [...h, { question, answer }])
    setQuestion('')
  }

  return (
    <div className="p-4 md:p-8 max-w-3xl mx-auto">
      <header className="mb-6">
        <h1 className="font-display text-2xl font-bold flex items-center gap-2">
          Knowledge Assistant <AiBadge>RAG</AiBadge>
        </h1>
        <p className="text-slate-500 text-sm mt-1">
          Ask against uploaded manuals, SOPs, and error-code references. Every answer cites its source.
        </p>
      </header>

      {user?.role === 'admin' && <DocumentManager />}

      <div className="space-y-4 mb-6">
        {history.map((h, i) => (
          <div key={i} className="space-y-2">
            <div className="text-slate-300 font-medium">{h.question}</div>
            <div className="card p-4">
              <p className="text-sm whitespace-pre-wrap">{h.answer.answer}</p>
              {h.answer.sources.length > 0 && (
                <div className="mt-3 pt-3 border-t border-slate-800 space-y-2">
                  {h.answer.sources.map((s, j) => (
                    <div key={j} className="text-xs text-slate-500">
                      <span className="font-mono text-signal-teal">
                        {s.document_title}
                        {s.page_number ? ` p.${s.page_number}` : ''}
                      </span>
                      <span className="ml-2 italic">"{s.excerpt}…"</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        ))}
        {ask.isPending && <div className="text-slate-500 text-sm">Searching the knowledge base…</div>}
      </div>

      <form onSubmit={handleSubmit} className="flex gap-2">
        <input
          value={question}
          onChange={(e) => setQuestion(e.target.value)}
          placeholder="What should I check when this AC shows E5?"
          className="flex-1 bg-slate-850 border border-slate-700 rounded-md px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
        <button type="submit" className="btn-primary" disabled={ask.isPending}>
          Ask
        </button>
      </form>
    </div>
  )
}

function DocumentManager() {
  const { data: documents, isLoading } = useDocuments()
  const upload = useUploadDocument()
  const deleteDoc = useDeleteDocument()
  const reindex = useReindexDocument()
  const [title, setTitle] = useState('')
  const [category, setCategory] = useState(CATEGORIES[0])
  const [file, setFile] = useState<File | null>(null)
  const [error, setError] = useState<string | null>(null)

  function handleUpload(e: FormEvent) {
    e.preventDefault()
    if (!title.trim() || !file) return
    setError(null)
    upload.mutate(
      { title, category, file },
      {
        onSuccess: () => {
          setTitle('')
          setFile(null)
        },
        onError: (err: any) => setError(err?.response?.data?.message ?? 'Could not upload this document.'),
      },
    )
  }

  return (
    <section className="card p-5 mb-8">
      <h2 className="font-display font-semibold mb-3">Knowledge base documents</h2>

      <form onSubmit={handleUpload} className="flex flex-wrap items-end gap-2 mb-4 pb-4 border-b border-slate-800">
        <div className="flex-1 min-w-[160px]">
          <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Title</label>
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className="w-full bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
            placeholder="AC Unit Service Manual"
          />
        </div>
        <div>
          <label className="block text-xs font-mono uppercase text-slate-500 mb-1">Category</label>
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="bg-slate-900 border border-slate-800 rounded-md px-3 py-2 text-sm"
          >
            {CATEGORIES.map((c) => (
              <option key={c} value={c}>
                {c.replace(/_/g, ' ')}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-mono uppercase text-slate-500 mb-1">PDF file</label>
          <input
            type="file"
            accept="application/pdf"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="text-sm"
          />
        </div>
        <button type="submit" className="btn-primary text-sm" disabled={!title.trim() || !file || upload.isPending}>
          {upload.isPending ? 'Uploading…' : 'Upload'}
        </button>
      </form>
      {error && <p className="text-signal-red text-xs mb-3">{error}</p>}

      {isLoading ? (
        <p className="text-slate-500 text-sm">Loading documents…</p>
      ) : !documents || documents.length === 0 ? (
        <p className="text-slate-500 text-sm">No documents uploaded yet.</p>
      ) : (
        <ul className="space-y-2">
          {documents.map((doc) => (
            <li key={doc.id} className="flex items-center justify-between text-sm py-1.5">
              <div>
                <span className="font-medium">{doc.title}</span>
                <span className="text-slate-500 ml-2 font-mono text-xs">
                  {doc.category?.replace(/_/g, ' ')} · {doc.page_count ?? '?'} pages · {doc.chunk_count ?? 0} chunks
                </span>
                <span className={`ml-2 font-mono text-xs uppercase ${STATUS_STYLES[doc.status] ?? ''}`}>{doc.status}</span>
                {doc.status === 'failed' && doc.processing_error && (
                  <div className="text-signal-red text-xs mt-0.5">{doc.processing_error}</div>
                )}
              </div>
              <div className="flex gap-3 shrink-0">
                <button
                  className="text-slate-400 hover:text-amber-500 text-xs"
                  disabled={reindex.isPending}
                  onClick={() => reindex.mutate(doc.id)}
                >
                  Reindex
                </button>
                <button
                  className="text-slate-400 hover:text-signal-red text-xs"
                  disabled={deleteDoc.isPending}
                  onClick={() => {
                    if (confirm(`Delete "${doc.title}"? This can't be undone.`)) deleteDoc.mutate(doc.id)
                  }}
                >
                  Delete
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
