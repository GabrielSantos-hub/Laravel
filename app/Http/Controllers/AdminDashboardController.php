<?php

namespace App\Http\Controllers;

use App\Services\PromptMetricsService;
use Illuminate\View\View;

/**
 * Painel de métricas do administrador. O acesso é garantido pelo grupo
 * `auth` + `can:admin` das rotas.
 */
class AdminDashboardController extends Controller
{
    public function __construct(
        protected PromptMetricsService $metrics
    ) {}

    public function index(): View
    {
        return view('admin.dashboard', ['metricas' => $this->metrics->summary()]);
    }
}
