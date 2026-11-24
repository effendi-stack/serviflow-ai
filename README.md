# ServiFlow AI

AI-powered field service management platform — Laravel + React/TypeScript + PostgreSQL/pgvector + Ollama, built entirely on a free/open-source stack.

Laravel is the source of truth. AI classifies, ranks, summarizes, and explains — deterministic business rules and human approval still control every important write.

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 + Laravel 11 |
| Frontend | React 18 + TypeScript + Tailwind + TanStack Query |
| Database | PostgreSQL 16 + pgvector |
| AI | Ollama (local LLM + local embeddings) |
| Queue/Cache | Redis |
| Containers | Docker Compose |
| Testing | Pest |
| CI | GitHub Actions |

## Architecture

```
React + TS  ──REST──▶  Laravel API ──▶ Postgres + pgvector
                              │
                              ├──▶ Redis (queues, cache)
                              │        │
                              │        ▼
                              │   Queue Worker ──▶ Ollama (chat + embeddings)
                              │
                              └──▶ AiProviderInterface (swap providers without touching business logic)
```

### Service request lifecycle

Every job moves through an explicit state machine (`ServiceRequest::TRANSITIONS`) — Laravel rejects any transition not listed here, regardless of which endpoint tries to trigger it:

```
   NEW ──▶ ANALYZING ──▶ READY_FOR_ASSIGNMENT ──▶ ASSIGNED ──▶ SCHEDULED ──▶ IN_PROGRESS ──▶ COMPLETED ──▶ REVIEWED ──▶ CLOSED
    │           │                  │                  │             │              │
    └───────────┴──────────────────┴──────────────────┴─────────────┴──────────────┘
                                    ▼
                               CANCELLED  (terminal; admin or the owning customer, any point before COMPLETED)
```

| Stage | Triggered by | What happens |
|---|---|---|
| NEW → ANALYZING → READY_FOR_ASSIGNMENT | System (queued job) | AI classifies the request; a failed classification still lands the job here for manual triage — it never gets stuck |
| READY_FOR_ASSIGNMENT | Admin | Accept or override the AI's category/priority/skill/duration (`POST /triage`) — this is what the rest of the system actually uses, not the AI's raw suggestion |
| → ASSIGNED | Admin | Assign a technician; AI ranks only technicians that already passed a deterministic eligibility filter (skill + on-duty + no conflict) |
| → SCHEDULED | Admin | Confirm an appointment time; rejected if it conflicts with the technician's other jobs |
| → IN_PROGRESS | Technician | Start the job |
| (within IN_PROGRESS) | Technician | Record notes/photos → generate an AI draft report → edit and finalize it |
| → COMPLETED | Technician | Requires a finalized report first; frees the technician back to available |
| → REVIEWED → CLOSED | Customer | Submitting a review closes the loop in one step |

## API documentation

Full OpenAPI 3.0 spec: [`backend/docs/openapi.yaml`](backend/docs/openapi.yaml) — every endpoint, request/response schema, and the validation-error shape used consistently across the API. Paste it into [Swagger Editor](https://editor.swagger.io) or any OpenAPI viewer for an interactive reference.

## AI safety & reliability

The AI layer follows one rule throughout: **the AI can suggest, only Laravel and a human can decide.**

- **AI output is untrusted input.** Every AI response is schema-validated (`JsonSchemaValidator`) before it touches anything — wrong type, out-of-enum value, or missing required field all raise `AIValidationException` and the response is discarded, not coerced. Fields the AI invents that aren't in the schema are silently dropped, never persisted.
- **The AI's original suggestion is immutable.** `ai_analyses` stores exactly what the model said, including the raw response. When an admin accepts or overrides it via `POST /triage`, the *final* values live on `ServiceRequest` itself — a separate set of columns — so there's always an honest before/after: what the AI said, and what a human actually approved. "Accepted" vs. "overridden" is computed by diffing the two, not trusted from the client.
- **Eligibility is a deterministic gate the AI never opens.** A technician must have the matching skill, be on duty, and have no scheduling conflict — checked in plain PHP/SQL — before the AI ever sees the candidate list. The AI ranks who's already eligible; it can't recommend someone who isn't.
- **Analytics numbers are computed in SQL, never by the model.** `POST /analytics/explain` hands the AI the exact aggregate `GET /analytics/summary` returns and asks it to narrate that in prose. The `stats` block in the response is always the real database aggregate; the AI's prose is advisory narration sitting next to it, never a second source of truth.
- **Every AI call is logged**, success or failure, to `ai_interactions` — feature, prompt, output, confidence, latency, and error if any. This is the audit trail behind every AI-influenced decision in the system.
- **Failures degrade safely.** A failed classification still leaves the request at `READY_FOR_ASSIGNMENT` for manual triage rather than stuck; transient connection failures are retried (`config('ai.max_retries')`); a failed knowledge-base document upload surfaces its error on the document itself rather than disappearing.

## Performance

Local LLM inference is the bottleneck, so the design keeps users from waiting on it:

- **No request waits for a full generation.** Classification runs in a background job; service reports and knowledge-base answers stream to the browser over Server-Sent Events (`/generate-report/stream`, `/knowledge/ask/stream`), so text appears as it is written.
- **Right-sized models, kept warm.** A small model (`OLLAMA_FAST_MODEL`) handles classification and ranking; the larger chat model handles long-form text. Per-feature temperature and token caps live in `config/ai.php`. `keep_alive` and `php artisan ai:warm` keep models loaded.
- **Separate queues under Horizon.** `ai-fast` (classification), `documents` (extraction + embedding, on a long-timeout connection) and `default`, so a large PDF import never delays a new request. AI worker counts follow `OLLAMA_NUM_PARALLEL`.
- **Fast retrieval.** Chunks are embedded in batches of 32 per Ollama call and searched through an HNSW pgvector index.
- **Caching.** Question embeddings, RAG answers (keyed on the retrieved chunks) and analytics explanations (keyed on the exact numbers) are cached in Redis; the dashboard summary is cached and cleared whenever a request or review changes. Cache hits are still logged in `ai_interactions` (`cache_hit`).
- **Database.** Foreign-key and filter columns are indexed, scheduling conflicts are a single SQL `EXISTS` query, and lazy loading throws outside production so N+1 queries can't slip in.

## Testing

```bash
docker compose exec app vendor/bin/pest
```

129 tests across scheduling (interval-overlap conflict detection), the full technician job workflow, customer review, AI triage and validation (malformed/missing/empty/unexpected-field responses), RAG retrieval and page-aware chunking, the complete status-transition matrix, authorization (role + ownership, including cross-technician and cross-customer access), and performance behaviour (queue routing, batched embeddings, the HNSW index, caching and SSE streaming).

## Quick start

```bash
git clone <this-repo> serviflow-ai && cd serviflow-ai
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose up -d --build

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

**Ollama (local AI).** On macOS, run Ollama natively rather than in Docker: Docker on macOS has no GPU access, so a containerised model is several times slower. The containers reach it at `host.docker.internal:11434`.

```bash
brew install ollama && ollama serve        # or install the Ollama app
ollama pull llama3.2:3b                    # fast model: classification, ranking
ollama pull llama3.1:8b                    # chat model: reports, RAG answers, analytics
ollama pull nomic-embed-text               # embeddings
docker compose exec app php artisan ai:warm   # preload models so the first request isn't slow
```

**On an 8 GB Mac**, set `OLLAMA_CHAT_MODEL=llama3.2:3b` in `backend/.env` (skip the 8B pull) and lower Docker Desktop's memory limit to about 2 GB (Settings → Resources); the containers use well under 1 GB. Otherwise macOS swaps model memory out between calls and AI latency jumps from under a second to several seconds.

On a Linux host with a GPU you can use the bundled container instead: `docker compose --profile ollama up -d`, then set `OLLAMA_BASE_URL=http://ollama:11434` in `backend/.env`. Models and per-feature limits (model tier, temperature, max tokens) live in `backend/config/ai.php`.

- App: http://localhost:5173 (frontend) / http://localhost:8000/api (backend)
- Demo logins (password `password` for all three): `admin@serviflow.test`, `technician@serviflow.test`, `customer@serviflow.test`
- The seeder builds a real scenario through every pipeline stage for the demo technician/customer (a job mid-triage, one scheduled, one in progress with notes recorded, one completed-and-invoiced, one closed-with-review) plus 30 randomized filler requests — so every page has something real to show immediately, not an empty state.

Run tests: `docker compose exec app vendor/bin/pest`

### Troubleshooting

**`Failed to open stream: vendor/autoload.php`** on `key:generate`/`migrate`/etc. — the `app`/`queue` containers bind-mount `./backend` over `/var/www/html` for live code reload, which shadows the `vendor/` folder `composer install` created during the image build (an anonymous volume in `docker-compose.yml` now prevents this on a fresh build). If you hit this on an existing checkout, just run composer inside the container once — it installs straight into the mounted folder and persists to disk:
```bash
docker compose exec app composer install
```

## Repository layout

```
backend/    Laravel API — see backend/app/{Actions,AI,Http,Jobs,Models,Services}
frontend/   React + TypeScript SPA
docker/     nginx + postgres init config
.github/    CI workflows
```

## Why these choices

- **Laravel** — routing, validation, ORM, queues, policies, and testing for a real business application, without extra frameworks bolted on.
- **PostgreSQL + pgvector** — one database for relational data *and* vector search; no separate vector store to operate.
- **Ollama** — local inference, so the AI layer costs nothing to run and isn't tied to a vendor.
- **Redis + Queues** — AI and document processing are slow and can fail; they run in the background so the HTTP request never blocks on an LLM call.
- **AIProviderInterface** — business logic depends on an interface, not a vendor SDK. Swapping in OpenAI or Anthropic later is one new class + one line in `AIServiceProvider`.
- **Human approval on every AI recommendation** — the AI ranks technicians and classifies tickets; Laravel validates and an admin approves before anything is written as final.

## Production deployment

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for free-tier hosting constraints (Ollama's memory footprint doesn't fit typical free web-service tiers), the persistent storage and long-running queue worker this app needs, and a full environment variable checklist.
