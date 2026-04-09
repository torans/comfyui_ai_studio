<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GenerationAsset;
use App\Models\GenerationJob;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $pendingJobs = GenerationJob::pending()->count();
        $runningJobs = GenerationJob::running()->count();
        $failedJobs = GenerationJob::failed()->count();
        $recentAssets = GenerationAsset::with(['generationJob', 'user'])
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('dashboard', [
            'stats' => [
                'pending_jobs' => $pendingJobs,
                'running_jobs' => $runningJobs,
                'failed_jobs' => $failedJobs,
            ],
            'recent_assets' => $recentAssets,
        ]);
    }
}
