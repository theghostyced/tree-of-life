<?php

namespace App\Http\Controllers\Mentor;

use App\Data\OnboardingProgress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('mentor/Dashboard', [
            'onboarding' => OnboardingProgress::forUser($request->user())->toArray(),
        ]);
    }
}
