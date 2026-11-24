# Production Deployment Guide

This guide covers some things to consider when deploying ServiFlow AI to a production environment.

The project currently uses Docker Compose for local development. While the same services can be used in production, a few things need to be configured differently, especially when it comes to AI processing, database storage, and background jobs.

There isn't a fixed hosting provider required for this project. The deployment setup will depend on where you want to host the application and how you plan to run the AI models.

## 1. Hosting the AI Models (Ollama)

One of the main things to consider before deploying this application is Ollama.

ServiFlow AI uses Ollama to run language models locally. This avoids depending on paid AI APIs, but it also means the server needs enough memory and processing power to run the models.

For example, the default chat model, `llama3.1`, requires several GB of RAM just to load. This can be a problem with free hosting plans, where the available memory is often limited to around 512 MB or 1 GB.

There are a few ways to handle this:

### Option 1: Use a VPS

A VPS with at least 4 GB of RAM is a more suitable option if you want to keep the current Ollama setup.

You can run the application and Ollama using Docker Compose on the same server. This keeps the deployment close to the local development environment.

You may also find cloud providers offering free trial credits, which can help with initial testing. However, check the available resources and how long those credits last.

### Option 2: Use an External AI Provider

Another option is to replace Ollama with a hosted AI provider such as OpenAI, Anthropic, or Groq.

The application already uses `AIProviderInterface` to separate the AI integration from the main business logic. This means you can add another provider without having to rewrite the existing AI features.

The main changes would be:

- Create a new provider class implementing `AIProviderInterface`.
- Register the new provider in `AIServiceProvider`.
- Update the relevant environment configuration.
- Remove the Ollama service if it is no longer needed.

The advantage is that you won't need to manage the memory and hardware required to run language models yourself. However, using an external provider may introduce API costs.

### Option 3: Use a Managed Ollama Host

You can also look into managed services that host Ollama for you.

This allows you to keep using the existing Ollama integration without managing the server resources yourself. The main thing to configure is the connection URL.

Whichever option you choose, the AI features are designed to work through the provider interface. Classes such as `TicketClassifier`, `AnalyticsExplainer`, and `KnowledgeAssistant` do not communicate directly with Ollama.

This makes it easier to change AI providers without affecting the rest of the application.

## 2. Persistent Storage

There are two important types of data that need to be preserved when deploying the application.

### Uploaded Knowledge Base Documents

Uploaded PDF documents are stored in:

`storage/app/knowledge-documents`

These files are managed by `KnowledgeController::uploadDocument`.

For production, I recommend using external object storage such as AWS S3 or another S3-compatible service instead of relying on the server's local filesystem.

Laravel already supports this through its filesystem configuration, so you can configure:

```env
FILESYSTEM_DISK=s3
```

You will also need to provide the appropriate `AWS_*` environment variables for your storage provider.

This helps ensure uploaded documents remain available even when the application is redeployed or moved to another server.

### PostgreSQL Database

The local Docker Compose setup uses a PostgreSQL volume called `pgdata`.

For production, you can use either a self-hosted PostgreSQL server or a managed PostgreSQL service.

One important thing to check is whether the database supports the `pgvector` extension, since the application uses it for storing and searching document embeddings.

You should be able to run:

```sql
CREATE EXTENSION vector;
```

Make sure this works before running the application migrations. Not every managed PostgreSQL provider enables pgvector by default.

## 3. Background Queue Worker

The queue worker is an important part of this application and must be running in production.

Several features rely on background jobs, including:

- AI classification of service requests.
- Processing uploaded documents.
- Generating document embeddings.
- AI-powered report generation.

These jobs use Redis through Laravel's queue system:

```env
QUEUE_CONNECTION=redis
```

The application currently uses jobs such as:

- `ProcessServiceRequest`
- `ProcessDocument`
- `GenerateEmbedding`

If the queue worker is not running, these jobs will remain in Redis without being processed. For example, a service request may remain in the `NEW` status, or an uploaded document may stay in the `uploaded` state.

To avoid this, production needs a continuously running queue worker.

For a typical deployment, you can run the worker separately from the main application using:

```bash
php artisan queue:work
```

If you're using a PaaS platform, you may need to create a separate worker service that uses the same application code and environment configuration.

The existing `queue` service in `docker-compose.yml` provides an example of how this is set up.

A scheduled task or cron job alone is not a replacement for a continuously running queue worker.

## 4. Environment Variables

Before deploying, make sure the production environment variables are configured correctly.

Apart from the usual Laravel settings such as `APP_KEY`, `APP_URL`, and `DB_*`, there are a few other variables worth checking.

| Variables | Description |
|---|---|
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | Configure Redis for caching, sessions, and background jobs, as used in this project's environment setup. For Laravel 11, use `CACHE_STORE` instead of the older `CACHE_DRIVER` variable. |
| `AI_PROVIDER`, `OLLAMA_BASE_URL`, `OLLAMA_CHAT_MODEL`, `OLLAMA_EMBEDDING_MODEL` | Configure which AI provider and models the application should use. Make sure the URL points to the actual AI service. |
| `AI_REQUEST_TIMEOUT`, `AI_MAX_RETRIES` | Control how long the application waits for an AI response and how many times it retries certain failed requests. You may need a longer timeout when using a remote AI provider. |
| `RAG_CHUNK_SIZE`, `RAG_CHUNK_OVERLAP`, `RAG_TOP_K` | Configure how documents are divided into smaller chunks and how many relevant chunks are retrieved during knowledge base searches. The current defaults are configured around `nomic-embed-text`. |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `FRONTEND_URL` | Configure the frontend domain and Laravel Sanctum session authentication. These values need to match your actual deployed frontend and backend domains. |
| `FILESYSTEM_DISK`, `AWS_*` | Configure where uploaded knowledge base documents are stored. Use the appropriate S3-compatible storage settings for production. |

Pay particular attention to the frontend and backend URLs when deploying them separately.

Incorrect Sanctum, session, or domain settings can cause authentication and CORS issues, even when the API itself is working correctly.

Also, make sure you use `CACHE_STORE` rather than `CACHE_DRIVER`. The latter was used in older Laravel versions and is not the correct configuration key for this Laravel 11 project.

## 5. Current Docker Compose Setup

The included `docker-compose.yml` is mainly intended for local development.

It uses bind mounts for the application source code and runs the frontend using the Vite development server.

This is convenient during development because code changes can be reflected without rebuilding the entire application.

However, the current setup is not intended to be used directly as a production deployment configuration.

Some things that would need to be handled separately include:

- Building the frontend for production instead of running `npm run dev`.
- Configuring a web server and HTTPS/TLS.
- Managing database backups and persistent storage.
- Running queue workers as separate long-running processes.
- Managing application secrets and production environment variables.
- Planning for additional application instances if the system needs to scale.

You can still use the existing Docker Compose file as a reference for the services and how they communicate with each other. However, I recommend preparing a separate production configuration based on your chosen hosting environment.

