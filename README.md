# ServiFlow AI

ServiFlow AI is a field service management application that helps businesses manage service requests, assign technicians, schedule jobs, and keep track of work progress.

I built this project using Laravel and React, with PostgreSQL for data storage and Ollama to handle the AI features. The idea is to bring service management and AI assistance together in one application, without relying on paid AI APIs or services.

The AI helps with tasks such as understanding service requests, suggesting suitable technicians, generating service reports, and answering questions based on stored documents. Important decisions, such as approving requests and assigning technicians, are still handled through the application's normal business logic and admin approval.

## Technologies Used

| Part | Technology |
|---|---|
| Backend | PHP 8.3, Laravel 11 |
| Frontend | React 18, TypeScript, Tailwind CSS |
| Data Fetching | TanStack Query |
| Database | PostgreSQL 16, pgvector |
| AI | Ollama (local LLM and embeddings) |
| Queue & Cache | Redis |
| Containers | Docker Compose |
| Testing | Pest |
| CI/CD | GitHub Actions |

## How It Works

The frontend communicates with the Laravel backend through REST APIs. Laravel handles the main application logic, database operations, authentication, and business rules.

AI-related tasks are processed through background jobs so that users don't have to wait for longer operations to finish. Ollama runs the language models locally, while PostgreSQL and pgvector are used to store and search document embeddings.

Here's a simplified overview of the application:

```text
React + TypeScript
        |
        | REST API
        v
    Laravel API
        |
        +------ PostgreSQL + pgvector
        |
        +------ Redis
        |          |
        |          v
        |      Queue Worker
        |          |
        |          v
        |        Ollama
        |     (LLM + Embeddings)
        |
        +------ AI Provider Interface
```

The AI provider interface also makes it easier to introduce another AI provider in the future without changing the main application logic.

## Service Request Workflow

The main part of ServiFlow AI is the service request workflow.

When a customer submits a request, the system sends it for AI analysis. The AI helps identify the service category, priority, required skills, and estimated duration. An admin can then review the suggestions before assigning the job to a technician.

Each request follows a defined sequence of statuses:

```text
NEW
  |
  v
ANALYZING
  |
  v
READY_FOR_ASSIGNMENT
  |
  v
ASSIGNED
  |
  v
SCHEDULED
  |
  v
IN_PROGRESS
  |
  v
COMPLETED
  |
  v
REVIEWED
  |
  v
CLOSED
```

A request can also be cancelled before it reaches the completed stage.

Here's what happens at each stage:

| Status | Who handles it | Description |
|---|---|---|
| New | Customer | A customer submits a service request. |
| Analyzing | System | The AI processes the request and suggests its category, priority, required skills, and estimated duration. |
| Ready for Assignment | Admin | The admin reviews the AI suggestions and makes any necessary changes. |
| Assigned | Admin | A suitable technician is selected for the job. |
| Scheduled | Admin | The appointment date and time are confirmed. |
| In Progress | Technician | The technician starts working on the assigned job. |
| Completed | Technician | The technician submits and finalizes the service report. |
| Reviewed | Customer | The customer submits feedback about the completed service. |
| Closed | System | The request is closed after the customer submits a review. |

The application also checks technician availability before allowing an assignment. It considers their skills, working status, and existing appointments to prevent scheduling conflicts.

If AI analysis fails, the request can still move forward for manual review. This prevents an AI issue from stopping the normal workflow.

## AI Features

AI is used to assist with different parts of the application rather than taking over the entire workflow.

The current implementation includes:

- **Service Request Analysis:** Reads customer requests and suggests a suitable category, priority, required skills, and estimated job duration.
- **Technician Recommendation:** Ranks available technicians based on their suitability for a particular job.
- **Service Report Generation:** Helps technicians prepare reports using their recorded notes and photos. The generated report can be edited before it is finalized.
- **Document Processing:** Extracts content from uploaded documents and creates embeddings for later searches.
- **Knowledge Base Assistant:** Allows users to ask questions based on documents stored in the system.
- **Analytics Explanation:** Uses AI to explain existing dashboard statistics in a more understandable format.

### AI Validation and Approval

One important thing I considered when developing these features was how the application handles AI-generated responses.

AI output is not always reliable, so I added validation before using its responses in the application.

Some of the checks include:

- Validating AI responses against the expected JSON schema.
- Rejecting responses with missing or invalid fields.
- Keeping the original AI suggestions separate from the values approved by an admin.
- Checking technician eligibility through Laravel before asking AI to rank candidates.
- Calculating dashboard statistics directly from the database instead of asking AI to generate numbers.

I also keep a record of AI interactions, including the feature used, prompt, response, confidence, processing time, and any errors.

This makes it easier to understand what happened when an AI operation fails or produces an unexpected result.

## Performance Considerations

Since the application uses locally hosted language models, AI processing can take some time depending on the model and available hardware.

I used a few approaches to make the application more responsive:

- **Background Processing:** Service request analysis and document processing run through Laravel queues instead of blocking the API request.
- **Streaming Responses:** Service report generation and knowledge base answers use Server-Sent Events (SSE), allowing responses to appear as they are generated.
- **Different AI Models:** Smaller models are used for faster tasks such as classification and technician ranking, while larger models handle longer responses.
- **Model Loading:** Ollama's `keep_alive` setting and the `php artisan ai:warm` command help keep models ready for use.
- **Separate Queues:** AI classification, document processing, and general jobs use separate queues so that heavier tasks don't delay other operations.
- **Vector Search:** Document embeddings are generated in batches and searched using a PostgreSQL HNSW index through pgvector.
- **Redis Caching:** Frequently used embeddings, knowledge base answers, analytics explanations, and dashboard summaries are cached to reduce repeated processing.
- **Database Indexing:** Frequently queried columns are indexed, and scheduling conflicts are checked using SQL queries.

## API Documentation

The API documentation is available in:

`backend/docs/openapi.yaml`

It contains the available endpoints, request and response structures, and validation error formats.

You can open the file using [Swagger Editor](https://editor.swagger.io) to explore the API documentation.

## Running Tests

I use Pest to test the main application features and make sure the different workflows behave as expected.

To run the test suite:

```bash
docker compose exec app vendor/bin/pest
```

The current test suite contains 129 tests covering:

- Service request and technician workflows.
- Technician scheduling and appointment conflicts.
- Customer reviews.
- AI request analysis and response validation.
- Document processing and knowledge base retrieval.
- Service request status transitions.
- User roles, permissions, and ownership checks.
- Queue processing, caching, and streaming responses.

## Getting Started

### 1. Clone the repository

```bash
git clone <this-repo> serviflow-ai
cd serviflow-ai
```

### 2. Set up environment files

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

### 3. Start the application

```bash
docker compose up -d --build
```

### 4. Generate the Laravel application key

```bash
docker compose exec app php artisan key:generate
```

### 5. Run database migrations and seeders

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

### 6. Set up Ollama

Ollama is used to run the AI models locally.

For macOS, I recommend installing and running Ollama directly on the host machine instead of inside Docker. Docker Desktop on macOS does not provide direct GPU access to containers, which can make local model inference slower.

Install Ollama:

```bash
brew install ollama
ollama serve
```

Download the models:

```bash
ollama pull llama3.2:3b
ollama pull llama3.1:8b
ollama pull nomic-embed-text
```

The models are used for different purposes:

| Model | Purpose |
|---|---|
| llama3.2:3b | Classification and technician ranking |
| llama3.1:8b | Reports, knowledge base answers, and analytics explanations |
| nomic-embed-text | Document embeddings and semantic search |

Once the models are downloaded, run:

```bash
docker compose exec app php artisan ai:warm
```

This helps load the models before using the AI features for the first time.

**For Macs with 8 GB RAM:**

Running multiple models can use quite a lot of memory. If you're using an 8 GB Mac, you can set `OLLAMA_CHAT_MODEL=llama3.2:3b` in `backend/.env` and skip downloading the 8B model.

You can also reduce Docker Desktop's memory allocation to around 2 GB to leave more memory available for Ollama.

**For Linux with a GPU:**

You can also run Ollama through the included Docker Compose profile:

```bash
docker compose --profile ollama up -d
```

Then update the Ollama URL in `backend/.env`:

```env
OLLAMA_BASE_URL=http://ollama:11434
```

AI model settings, including model selection, temperature, and token limits, can be found in:

`backend/config/ai.php`

### 7. Access the application

Once everything is running, you can access the application here:

- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api

### Demo Accounts

You can use the following accounts to explore the different user roles.

| Role | Email | Password |
|---|---|---|
| Admin | admin@serviflow.test | password |
| Technician | technician@serviflow.test | password |
| Customer | customer@serviflow.test | password |

The database seeder also creates sample service requests at different stages of the workflow. This includes requests waiting for triage, scheduled jobs, ongoing work with recorded notes, completed jobs, and closed requests with customer reviews.

It also generates 30 additional sample requests so the dashboard and other pages have data to display when you first open the application.

### Troubleshooting

**Error: `Failed to open stream: vendor/autoload.php`**

If you encounter this error when running commands such as `key:generate` or `migrate`, it may be caused by the backend directory being mounted into the Docker container.

The mounted directory can hide the `vendor` folder created during the Docker image build.

To resolve this, run Composer inside the application container:

```bash
docker compose exec app composer install
```

This installs the dependencies directly into the mounted backend directory.

## Project Structure

The repository is organised into two main application directories, along with the Docker and CI configuration files.

```text
serviflow-ai/
│
├── backend/       Laravel API
│   └── app/
│       ├── Actions/
│       ├── AI/
│       ├── Http/
│       ├── Jobs/
│       ├── Models/
│       └── Services/
│
├── frontend/      React + TypeScript application
│
├── docker/        Nginx and PostgreSQL configuration
│
└── .github/       GitHub Actions workflows
```

## Why I Chose This Stack

I wanted to build the application using technologies that work well together without depending on expensive external services.

A few reasons behind the choices:

- **Laravel:** Handles the backend, API, authentication, validation, database operations, queues, and application rules.
- **React + TypeScript:** Provides a component-based frontend with type checking to make the code easier to maintain.
- **PostgreSQL + pgvector:** Allows me to store application data and document embeddings in the same database instead of maintaining a separate vector database.
- **Ollama:** Makes it possible to run AI models locally without depending on paid AI API services.
- **Redis:** Handles caching and background job processing.
- **AI Provider Interface:** Keeps the AI integration separate from the main application logic, making it easier to add other providers such as OpenAI or Anthropic later.
- **Pest:** Provides a straightforward way to test the application's workflows, business rules, and AI-related functionality.

I also wanted to make sure that AI suggestions would not automatically change important business data. That's why the application uses validation, normal business rules, and admin approval for important decisions.

## Deployment

The deployment guide is available here:

`docs/DEPLOYMENT.md`

It covers the hosting requirements, environment variables, persistent storage, and queue worker configuration.

One thing to consider before deploying is that locally hosted Ollama models require more memory and resources than typical free web hosting services provide. The application also needs persistent storage and long-running queue workers for its background processes.

