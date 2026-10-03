<?php

namespace App\Http\Controllers;

use App\Services\PromptMetricsService;
use App\Services\Security\AdminAuditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected PromptMetricsService $metrics,
        protected AdminAuditor $auditor,
    ) {}

    public function index(): View
    {
        return view('admin.dashboard', ['metricas' => $this->metrics->summary()]);
    }

    public function resetMetrics(): RedirectResponse
    {
        $at = $this->metrics->resetToNow();

        $this->auditor->record('admin_metrics_reset', null, [
            'metrics_reset_at' => $at->format('Y-m-d H:i:s'),
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('sucesso', 'Métricas consideradas desde '.$at->timezone((string) config('app.timezone'))->format('d/m/Y H:i').'.');
    }

    public function clearMetricsReset(): RedirectResponse
    {
        $this->metrics->clearReset();

        $this->auditor->record('admin_metrics_reset_cleared');

        return redirect()
            ->route('admin.dashboard')
            ->with('sucesso', 'Métricas voltam a considerar todo o histórico.');
    }
}
