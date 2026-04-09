<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GenerationAsset;
use App\Models\GenerationJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $stats = [
            'pending' => GenerationJob::where('status', 'pending')->count(),
            'running' => GenerationJob::where('status', 'running')->count(),
            'failed' => GenerationJob::where('status', 'failed')->count(),
            'recent_assets' => GenerationAsset::count(),
        ];

        if (file_exists(resource_path('js/Pages/Dashboard.tsx'))) {
            return Inertia::render('Dashboard', ['stats' => $stats]);
        }

        return view('dashboard', ['stats' => $stats]);
    }
}
