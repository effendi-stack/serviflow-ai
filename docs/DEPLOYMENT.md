# Production deployment notes

This app is built and tested against the Docker Compose setup in this
repo. Deploying it beyond a local machine means making a few decisions
the compose file makes for you locally — this doc lays those out rather
than prescribing one hosting provider.

## The one constraint that shapes everything else: Ollama

Ollama runs local inference, which is what makes the AI layer free to
run — but it needs real memory and, ideally, a GPU. `llama3.1` (the
default chat model) wants several GB of RAM just to load. That doesn't
fit the free tier of typical PaaS platforms (Render, Railway, Fly.io
free tiers are usually 512MB–1GB).

Realistic options, in order of how much they resemble local dev:

1. **A small VPS with enough RAM** (4GB+) running the same Docker
   Compose setup, including the `ollama` container. Closest to this
   repo as-is; cheapest true "free-ish" option is a VPS free-tier credit
   (e.g. a cloud provider's trial credits), not a PaaS free tier.
2. **Swap `AIProviderInterface`** for a hosted provider (OpenAI,
   Anthropic, Groq, etc.) and drop the `ollama` container entirely.
   This is the intended escape hatch — business logic depends on the
   interface, not Ollama specifically, so this is one new class
   implementing `AIProviderInterface` plus one line in
   `AIServiceProvider` to bind it. You lose the "$0 to run" property in
   exchange for not needing to host an LLM yourself.
3. **A managed Ollama host** (a few exist commercially) if you want to
   keep the interface unchanged but not operate the GPU/RAM yourself.

Whichever you choose, `OLLAMA_BASE_URL` (or a new provider's
equivalent config) is the only thing that changes — nothing in
`app/AI/TicketClassifier`, `AnalyticsExplainer`, `KnowledgeAssistant`,
etc. talks to Ollama directly.

## Persistent storage

Two things need to survive a redeploy, and won't on most PaaS
platforms' default ephemeral filesystem:

- **Uploaded knowledge base PDFs** (`storage/app/knowledge-documents`,
  written by `KnowledgeController::uploadDocument`). Use S3-compatible
  object storage (`FILESYSTEM_DISK=s3` and the matching `AWS_*` env
  vars — Laravel's filesystem abstraction already supports this, no
  code change needed) rather than local disk in production.
- **The Postgres volume** (`pgdata` in `docker-compose.yml`). Any
  managed Postgres works; just make sure `pgvector` is available as an
  extension on it (`CREATE EXTENSION vector;` needs to succeed) — not
  every managed Postgres offering ships it by default.

## The queue worker is not optional

AI classification, document processing/embedding, and report
generation all run through `QUEUE_CONNECTION=redis` as background
jobs (`ProcessServiceRequest`, `ProcessDocument`, `GenerateEmbedding`).
If nothing is running `php artisan queue:work`, those jobs sit in Redis
forever and requests stay stuck at `NEW`/documents stay stuck at
`uploaded`. This needs a long-running process, not a request-triggered
one — on PaaS platforms that means a second "worker" service pointed
at the same codebase (see `docker-compose.yml`'s `queue` service for
exactly that pattern), not a cron job.

## Environment variable checklist

Beyond the standard Laravel `APP_KEY`/`APP_URL`/`DB_*` set:

| Variable | Notes |
|---|---|
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | All `redis` in this repo's `.env.example`. `CACHE_STORE` (not `CACHE_DRIVER` — that's the Laravel 10 name and does nothing on Laravel 11) is the key that actually matters; this repo's `phpunit.xml` had exactly this stale key name until it was caught in testing. |
| `AI_PROVIDER`, `OLLAMA_BASE_URL`, `OLLAMA_CHAT_MODEL`, `OLLAMA_EMBEDDING_MODEL` | Point these at wherever Ollama (or its replacement) actually runs — never `localhost` once these are separate services. |
| `AI_REQUEST_TIMEOUT`, `AI_MAX_RETRIES` | Widen `AI_REQUEST_TIMEOUT` if the model host is remote/slower than local Ollama; `AI_MAX_RETRIES` covers transient connection failures only (a real 4xx/5xx from the provider is not retried — see `OllamaProvider`'s docblock). |
| `RAG_CHUNK_SIZE`, `RAG_CHUNK_OVERLAP`, `RAG_TOP_K` | Tune per embedding model; the defaults are sized for `nomic-embed-text`. |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `FRONTEND_URL` | Must match the real deployed frontend origin, or the SPA's auth will silently fail CORS/session checks. |
| `FILESYSTEM_DISK` + `AWS_*` | See persistent storage above — don't leave this as `local` in production. |

## What's still local-only

The included `docker-compose.yml` is a development setup (bind-mounted
source, `npm run dev` for the frontend, no TLS termination, no
horizontal scaling). Treat it as the reference for *which services
exist and how they depend on each other*, not as a production
deployment manifest to run as-is.
