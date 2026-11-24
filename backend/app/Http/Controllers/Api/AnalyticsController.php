<?php

namespace App\Http\Controllers\Api;

use App\AI\AnalyticsExplainer;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * All numbers in the `summary` payload are computed with real SQL
 * aggregates. The optional `explanation` field is the only AI-touched
 * part of this endpoint, and it is always handed these same numbers
 * rather than being asked to produce its own (spec section 6).
 */
class AnalyticsController extends Controller
{
    public const SUMMARY_CACHE_KEY = 'analytics:summary';

    /**
     * Upper bound on staleness. The cache is also cleared whenever a
     * service request or review changes (see AppServiceProvider), so in
     * practice the dashboard reflects writes immediately.
     */
    public const SUMMARY_CACHE_TTL_SECONDS = 600;

    public function summary(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $stats = $this->aggregate();

        return response()->json(['data' => $stats]);
    }

    public function explain(Request $request, AnalyticsExplainer $explainer)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);

        $stats = $this->aggregate();
        $explanation = $explainer->explain($data['question'], $stats);

        return response()->json(['data' => ['stats' => $stats, 'explanation' => $explanation]]);
    }

    private function aggregate(): array
    {
        return Cache::remember(self::SUMMARY_CACHE_KEY, self::SUMMARY_CACHE_TTL_SECONDS, fn () => $this->computeAggregate());
    }

    private function computeAggregate(): array
    {
        $totalsByStatus = ServiceRequest::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $avgCompletionMinutes = ServiceRequest::query()
            ->whereNotNull('scheduled_at')
            ->where('status', ServiceRequest::STATUS_CLOSED)
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - scheduled_at)) / 60) as avg_minutes')
            ->value('avg_minutes');

        $avgRating = Review::query()->avg('rating');

        $requestsPerMonth = ServiceRequest::query()
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        return [
            'total_jobs' => (int) ServiceRequest::query()->count(),
            'by_status' => $totalsByStatus->all(),
            'average_completion_minutes' => $avgCompletionMinutes ? round((float) $avgCompletionMinutes, 1) : null,
            'average_customer_rating' => $avgRating ? round((float) $avgRating, 2) : null,
            'requests_per_month' => $requestsPerMonth->all(),
        ];
    }
}
