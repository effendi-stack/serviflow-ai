<?php

use App\Models\AiAnalysis;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Review;
use App\Models\ServiceNote;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Regression guard for the demo seeder (roadmap item 30): the whole
 * point of the rewrite was known, working demo logins and requests that
 * actually exercise every workflow -- this locks that in so a future
 * change can't silently regress back to "admin is the only usable
 * account" or "every status is a bare, feature-less row".
 */
it('seeds known, working demo accounts for all three roles', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (['admin@serviflow.test', 'technician@serviflow.test', 'customer@serviflow.test'] as $email) {
        $user = User::where('email', $email)->first();
        expect($user)->not->toBeNull();
        expect(Hash::check('password', $user->password))->toBeTrue();
    }

    expect(User::where('email', 'admin@serviflow.test')->first()->role)->toBe('admin');
    expect(User::where('email', 'technician@serviflow.test')->first()->role)->toBe('technician');
    expect(User::where('email', 'customer@serviflow.test')->first()->role)->toBe('customer');
});

it('seeds a request at every stage of the lifecycle, not just bare rows', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (ServiceRequest::TRANSITIONS as $status => $_) {
        expect(ServiceRequest::where('status', $status)->exists())
            ->toBeTrue("Expected at least one seeded request with status [{$status}]");
    }

    // A completed request should have a finalized report and a draft
    // invoice, not an empty shell -- these are the fields the UI
    // actually renders.
    $completedWithReport = ServiceRequest::where('status', ServiceRequest::STATUS_COMPLETED)
        ->whereHas('serviceNote', fn ($q) => $q->whereNotNull('final_report'))
        ->exists();
    expect($completedWithReport)->toBeTrue();

    $closedWithPaidInvoiceAndReview = ServiceRequest::where('status', ServiceRequest::STATUS_CLOSED)
        ->whereHas('invoice', fn ($q) => $q->where('status', 'paid'))
        ->whereHas('review')
        ->exists();
    expect($closedWithPaidInvoiceAndReview)->toBeTrue();

    expect(AiAnalysis::count())->toBeGreaterThan(0);
    expect(ServiceNote::count())->toBeGreaterThan(0);
    expect(Invoice::count())->toBeGreaterThan(0);
    expect(Review::count())->toBeGreaterThan(0);
});

it('seeds an indexed knowledge base document so the assistant is not empty on first load', function () {
    $this->seed(DatabaseSeeder::class);

    $document = Document::where('status', 'indexed')->first();
    expect($document)->not->toBeNull();
    expect($document->chunks()->count())->toBeGreaterThan(0);
});
