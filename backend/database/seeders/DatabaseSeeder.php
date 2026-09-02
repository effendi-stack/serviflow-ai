<?php

namespace Database\Seeders;

use App\Models\AiAnalysis;
use App\Models\Customer;
use App\Models\Document;
use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds demo data (spec section 17, roadmap item 30). Two things the
 * previous version of this seeder didn't do, which mattered for anyone
 * actually trying the demo:
 *
 * 1. Every technician/customer had a randomly-generated email with no
 *    documented login -- only the admin account was actually usable.
 *    A few of each now get fixed, memorable credentials.
 * 2. Every seeded request landed in a "bare" version of its status
 *    (no AiAnalysis, no scheduled_at, no service note, no invoice) --
 *    so most of the UI's own workflows had nothing real to show on a
 *    fresh install. The scenario requests below are hand-built through
 *    each stage of the pipeline instead, so logging in as the demo
 *    technician or customer shows a coherent story, not an empty shell.
 */
class DatabaseSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@serviflow.test',
            'password' => Hash::make(self::PASSWORD),
        ]);

        $demoTechnician = Technician::factory()->create([
            'user_id' => User::factory()->technician()->create([
                'name' => 'Alex Tan',
                'email' => 'technician@serviflow.test',
                'password' => Hash::make(self::PASSWORD),
            ])->id,
            'skills' => ['air_conditioning', 'electrical'],
            'status' => 'available',
            'rating_avg' => 4.8,
        ]);

        $demoCustomer = Customer::factory()->create([
            'user_id' => User::factory()->customer()->create([
                'name' => 'Priya Nair',
                'email' => 'customer@serviflow.test',
                'password' => Hash::make(self::PASSWORD),
            ])->id,
            'name' => 'Priya Nair',
        ]);

        $technicians = $demoTechnician->newCollection([$demoTechnician])
            ->merge(Technician::factory()->count(5)->create());

        $customers = $demoCustomer->newCollection([$demoCustomer])
            ->merge(Customer::factory()->count(14)->create());

        $this->seedScenarios($demoTechnician, $demoCustomer, $admin);
        $this->seedBulkFiller($technicians, $customers);
        $this->seedKnowledgeBase();

        $this->command->info('Seeded 1 admin, '.$technicians->count().' technicians, '.$customers->count().' customers.');
        $this->command->info('Demo logins (password: "'.self::PASSWORD.'"): admin@serviflow.test, technician@serviflow.test, customer@serviflow.test');
    }

    /**
     * One request per pipeline stage, all involving the demo technician
     * and/or demo customer, so their accounts have something real to
     * look at immediately after logging in.
     */
    private function seedScenarios(Technician $technician, Customer $customer, User $admin): void
    {
        // Stage 1: just submitted, AI triage still running.
        $this->makeRequest($customer, ServiceRequest::STATUS_ANALYZING, [
            'service_type' => 'Air Conditioning',
            'description' => 'AC unit blowing warm air, tripped the breaker twice this week.',
        ]);

        // Stage 2: AI has classified it, waiting on admin triage.
        $awaitingTriage = $this->makeRequest($customer, ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, [
            'service_type' => 'Air Conditioning',
            'description' => 'Outdoor unit making a loud grinding noise on startup.',
        ]);
        $this->attachAiAnalysis($awaitingTriage, accepted: null, admin: $admin);

        // Stage 3: triaged and assigned, not yet scheduled.
        $assigned = $this->makeRequest($customer, ServiceRequest::STATUS_ASSIGNED, [
            'service_type' => 'Electrical',
            'description' => 'Living room outlet stopped working, breaker is fine.',
            'category' => 'electrical',
            'priority' => 'medium',
            'required_skill' => 'electrical',
            'estimated_duration_minutes' => 60,
        ]);
        $this->attachAiAnalysis($assigned, accepted: true, admin: $admin);
        $this->attachAssignment($assigned, $technician, $admin, wasAiRecommended: true);

        // Stage 4: scheduled, appointment confirmed.
        $scheduled = $this->makeRequest($customer, ServiceRequest::STATUS_SCHEDULED, [
            'service_type' => 'Air Conditioning',
            'description' => 'Annual maintenance and filter replacement.',
            'category' => 'air_conditioning',
            'priority' => 'low',
            'required_skill' => 'air_conditioning',
            'estimated_duration_minutes' => 90,
            'scheduled_at' => now()->addDays(2)->setTime(10, 0),
        ]);
        $this->attachAiAnalysis($scheduled, accepted: true, admin: $admin);
        $this->attachAssignment($scheduled, $technician, $admin, wasAiRecommended: true);

        // Stage 5: technician is on-site, notes recorded, report not yet finalized.
        $inProgress = $this->makeRequest($customer, ServiceRequest::STATUS_IN_PROGRESS, [
            'service_type' => 'Air Conditioning',
            'description' => 'No cold air at all, unit runs but nothing comes out of the vents.',
            'category' => 'air_conditioning',
            'priority' => 'high',
            'required_skill' => 'air_conditioning',
            'estimated_duration_minutes' => 120,
            'scheduled_at' => now()->subHours(1),
        ]);
        $this->attachAiAnalysis($inProgress, accepted: true, admin: $admin);
        $this->attachAssignment($inProgress, $technician, $admin, wasAiRecommended: true, assignmentStatus: 'started');
        $inProgress->serviceNote()->create([
            'technician_id' => $technician->id,
            'raw_notes' => 'Refrigerant level low, found a small leak at the flare fitting. Topping up and resealing.',
            'photo_paths' => ['demo/before-leak.jpg'],
        ]);

        // Stage 6: completed, report finalized, invoice drafted but not issued.
        $completed = $this->makeRequest($customer, ServiceRequest::STATUS_COMPLETED, [
            'service_type' => 'Air Conditioning',
            'description' => 'Unit short-cycling every few minutes.',
            'category' => 'air_conditioning',
            'priority' => 'medium',
            'required_skill' => 'air_conditioning',
            'estimated_duration_minutes' => 90,
            'scheduled_at' => now()->subDays(1),
        ]);
        $this->attachAiAnalysis($completed, accepted: true, admin: $admin);
        $this->attachAssignment($completed, $technician, $admin, wasAiRecommended: true, assignmentStatus: 'completed');
        $completed->serviceNote()->create([
            'technician_id' => $technician->id,
            'raw_notes' => 'Dirty condenser coil was causing short-cycling. Cleaned coil, checked pressures, unit now runs a full cycle.',
            'ai_generated_report' => 'Diagnosed short-cycling caused by a dirty condenser coil restricting airflow. Cleaned the coil and verified refrigerant pressures were within spec after the repair. Unit completed a full cooling cycle without further short-cycling during testing.',
            'final_report' => 'Diagnosed short-cycling caused by a dirty condenser coil restricting airflow. Cleaned the coil and verified refrigerant pressures were within spec. Unit completed a full cooling cycle without issue during testing. Recommend annual coil cleaning going forward.',
            'finalized_by' => $admin->id,
            'finalized_at' => now()->subHours(20),
        ]);
        $completed->invoice()->create([
            'labor_cost' => 120.00,
            'parts_cost' => 0.00,
            'total' => 120.00,
            'status' => 'draft',
        ]);

        // Stage 7: fully closed loop -- issued + paid invoice, customer review.
        $closed = $this->makeRequest($customer, ServiceRequest::STATUS_CLOSED, [
            'service_type' => 'Plumbing',
            'description' => 'Kitchen sink leaking under the cabinet.',
            'category' => 'plumbing',
            'priority' => 'medium',
            'required_skill' => 'plumbing',
            'estimated_duration_minutes' => 75,
            'scheduled_at' => now()->subDays(5),
        ]);
        $this->attachAiAnalysis($closed, accepted: false, admin: $admin, overriddenCategory: 'plumbing');
        $this->attachAssignment($closed, $technician, $admin, wasAiRecommended: false, assignmentStatus: 'completed');
        $closed->serviceNote()->create([
            'technician_id' => $technician->id,
            'raw_notes' => 'Replaced worn P-trap gasket, tested for leaks under load.',
            'final_report' => 'Replaced the worn P-trap gasket under the kitchen sink and tested the line under load for 15 minutes with no further leaking.',
            'finalized_by' => $admin->id,
            'finalized_at' => now()->subDays(4),
        ]);
        $closed->invoice()->create([
            'labor_cost' => 90.00,
            'parts_cost' => 15.00,
            'total' => 105.00,
            'status' => 'paid',
            'issued_at' => now()->subDays(4),
            'paid_at' => now()->subDays(3),
        ]);
        $closed->review()->create([
            'technician_id' => $technician->id,
            'rating' => 5,
            'comment' => 'Quick, tidy, and explained exactly what was wrong. Would book again.',
        ]);

        // Stage 8: cancelled -- demonstrates the cancel flow.
        $cancelled = $this->makeRequest($customer, ServiceRequest::STATUS_CANCELLED, [
            'service_type' => 'General Maintenance',
            'description' => 'Squeaky ceiling fan in the guest room.',
        ]);
        StatusHistory::create([
            'service_request_id' => $cancelled->id,
            'from_status' => ServiceRequest::STATUS_NEW,
            'to_status' => ServiceRequest::STATUS_CANCELLED,
            'note' => 'Customer fixed it themselves.',
            'created_at' => now()->subHours(3),
        ]);
    }

    private function makeRequest(Customer $customer, string $status, array $overrides = []): ServiceRequest
    {
        return ServiceRequest::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'status' => $status,
        ], $overrides));
    }

    private function attachAiAnalysis(ServiceRequest $request, ?bool $accepted, User $admin, ?string $overriddenCategory = null): AiAnalysis
    {
        $category = $request->category ?? 'air_conditioning';
        $priority = $request->priority ?? 'medium';
        $skill = $request->required_skill ?? 'air_conditioning';
        $duration = $request->estimated_duration_minutes ?? 90;

        return AiAnalysis::create([
            'service_request_id' => $request->id,
            'category' => $category,
            'priority' => $priority,
            'issue' => 'diagnostic_visit',
            'estimated_duration_minutes' => $duration,
            'required_skill' => $skill,
            'confidence' => 0.82,
            'raw_response' => ['category' => $category, 'priority' => $priority],
            'accepted_by_admin' => $accepted,
            'reviewed_by' => $accepted === null ? null : $admin->id,
        ]);
    }

    private function attachAssignment(
        ServiceRequest $request,
        Technician $technician,
        User $admin,
        bool $wasAiRecommended,
        string $assignmentStatus = 'assigned',
    ): void {
        $request->assignment()->create([
            'technician_id' => $technician->id,
            'assigned_by' => $admin->id,
            'status' => $assignmentStatus,
            'was_ai_recommended' => $wasAiRecommended,
            'ai_rank_score' => $wasAiRecommended ? 91.5 : null,
            'ai_rationale' => $wasAiRecommended ? 'Closest on-duty technician with a matching skill and no scheduling conflicts.' : null,
        ]);
    }

    private function seedBulkFiller($technicians, $customers): void
    {
        $statuses = [
            ServiceRequest::STATUS_NEW,
            ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
            ServiceRequest::STATUS_ASSIGNED,
            ServiceRequest::STATUS_IN_PROGRESS,
            ServiceRequest::STATUS_COMPLETED,
            ServiceRequest::STATUS_REVIEWED,
            ServiceRequest::STATUS_CLOSED,
            ServiceRequest::STATUS_CANCELLED,
        ];

        foreach (range(1, 30) as $i) {
            $customer = $customers->random();
            $status = $statuses[array_rand($statuses)];

            $request = ServiceRequest::factory()->create([
                'customer_id' => $customer->id,
                'status' => $status,
                'category' => fake()->randomElement(['air_conditioning', 'plumbing', 'electrical']),
                'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            ]);

            if (in_array($status, [
                ServiceRequest::STATUS_ASSIGNED,
                ServiceRequest::STATUS_IN_PROGRESS,
                ServiceRequest::STATUS_COMPLETED,
                ServiceRequest::STATUS_REVIEWED,
                ServiceRequest::STATUS_CLOSED,
            ], true)) {
                $request->assignment()->create([
                    'technician_id' => $technicians->random()->id,
                    'status' => $status === ServiceRequest::STATUS_ASSIGNED ? 'assigned' : 'started',
                ]);
            }

            if (in_array($status, [ServiceRequest::STATUS_COMPLETED, ServiceRequest::STATUS_REVIEWED, ServiceRequest::STATUS_CLOSED], true)) {
                $request->review()->create([
                    'technician_id' => $request->assignment?->technician_id,
                    'rating' => fake()->numberBetween(3, 5),
                    'comment' => fake()->sentence(),
                ]);
            }
        }
    }

    /**
     * A small indexed document so the Knowledge Assistant isn't empty on
     * a fresh install. Embeddings here are synthetic (not real Ollama
     * output) -- good enough to demonstrate the list/detail UI, not
     * meant to produce meaningful similarity search results.
     */
    private function seedKnowledgeBase(): void
    {
        $document = Document::create([
            'title' => 'AC Unit Troubleshooting Guide (demo)',
            'category' => 'troubleshooting',
            'file_path' => 'knowledge-documents/demo-ac-guide.pdf',
            'status' => 'indexed',
            'page_count' => 2,
        ]);

        $chunks = [
            [1, 'Error code E4 indicates a compressor overload. Reset the breaker, wait 10 minutes, and restart the unit before dispatching a technician.'],
            [1, 'Short-cycling is most commonly caused by a dirty condenser coil restricting airflow, or a refrigerant leak reducing system pressure.'],
            [2, 'For units under warranty, do not open the sealed refrigerant system without confirming warranty status first -- this can void coverage.'],
        ];

        foreach ($chunks as $index => [$page, $text]) {
            $chunk = $document->chunks()->create([
                'chunk_index' => $index,
                'page_number' => $page,
                'content' => $text,
            ]);

            $vector = array_fill(0, 768, 0.0);
            $vector[$index] = 1.0; // distinct synthetic vector per chunk
            $literal = '['.implode(',', $vector).']';
            DB::statement('UPDATE document_chunks SET embedding = ? WHERE id = ?', [$literal, $chunk->id]);
        }
    }
}
