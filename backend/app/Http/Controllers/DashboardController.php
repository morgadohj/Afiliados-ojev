<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $affiliates = Affiliate::query()
            ->with('createdBy:id,name')
            ->latest('application_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Affiliate $affiliate): array => [
                'id' => $affiliate->id,
                'folio' => $affiliate->folio,
                'full_name' => collect([
                    $affiliate->first_name,
                    $affiliate->paternal_last_name,
                    $affiliate->maternal_last_name,
                ])->filter()->implode(' '),
                'application_date' => $affiliate->application_date->format('Y-m-d'),
                'branch' => $affiliate->oje_v_branch,
                'status' => $affiliate->status,
                'registered_by' => $affiliate->createdBy ? [
                    'id' => $affiliate->createdBy->id,
                    'name' => $affiliate->createdBy->name,
                ] : null,
            ]);

        return Inertia::render('dashboard', [
            'affiliates' => $affiliates,
            'summary' => [
                'total' => Affiliate::query()->count(),
                'administrative' => Affiliate::query()->whereNotNull('created_by_user_id')->count(),
                'public' => Affiliate::query()->whereNull('created_by_user_id')->count(),
            ],
        ]);
    }
}
