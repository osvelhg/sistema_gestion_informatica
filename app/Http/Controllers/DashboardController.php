<?php

namespace App\Http\Controllers;

use App\Services\EquipmentFileService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(EquipmentFileService $service): Response
    {
        return Inertia::render('Dashboard', [
            'statistics' => $service->statistics(),
        ]);
    }
}
