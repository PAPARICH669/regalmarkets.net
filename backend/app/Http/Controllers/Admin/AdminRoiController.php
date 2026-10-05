<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentPackage;
use App\Models\RoiLog;
use App\Services\AuditService;
use App\Services\RoiScheduleService;
use App\Services\RoiService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRoiController extends Controller
{
    public function __construct(
        protected RoiService $roi,
        protected SettingsService $settings,
        protected AuditService $audit,
        protected RoiScheduleService $schedule,
    ) {}

    public function status()
    {
        $today = Carbon::today()->toDateString();
        $activePrincipal = (float) InvestmentPackage::where('status', 'active')->sum('principal');
        $liability = (float) InvestmentPackage::where('status', 'active')
            ->select(DB::raw('COALESCE(SUM(total_return - total_paid),0) v'))->value('v');

        return response()->json([
            'default_percent'   => (float) $this->settings->get('roi_daily_percent'),
            'active_packages'   => InvestmentPackage::where('status', 'active')->count(),
            'active_principal'  => $activePrincipal,
            'roi_liability'     => $liability,
            'today'             => $today,
            'today_paid'        => (float) RoiLog::whereDate('roi_date', $today)->sum('amount'),
            'today_paid_count'  => RoiLog::whereDate('roi_date', $today)->count(),
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'percent'      => ['required', 'numeric', 'min:0', 'max:100'],
            'date'         => ['nullable', 'date'],
            'save_default' => ['boolean'],
        ]);

        $date = isset($data['date']) ? Carbon::parse($data['date']) : Carbon::today();

        // Optionally persist this rate as the new default daily %.
        if (! empty($data['save_default'])) {
            $this->settings->set('roi_daily_percent', $data['percent']);
        }

        $stats = $this->roi->runForDate($date, (float) $data['percent']);

        $this->audit->log($request, 'roi.run', null, [
            'percent' => $data['percent'], 'date' => $date->toDateString(), 'paid' => $stats['paid'],
        ]);

        return response()->json([
            'message' => "Commission run at {$data['percent']}% for {$date->toDateString()}.",
            'stats'   => $stats,
        ]);
    }

    /**
     * Preview the current month's daily ROI schedule (monthly_target mode). Shows
     * each day's %, whether it's locked (already paid), the running cumulative,
     * and the sum-vs-target check.
     */
    public function schedule(Request $request)
    {
        $month = $request->query('month')
            ? Carbon::parse($request->query('month') . '-01')
            : Carbon::today();

        return response()->json($this->schedule->currentMonthPreview($month));
    }

    /**
     * Regenerate the current month's schedule (new random fluctuation pattern).
     * Already-paid days are preserved; only the remaining days are re-rolled.
     */
    public function regenerate(Request $request)
    {
        $data = $request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $month = isset($data['month']) ? Carbon::parse($data['month'] . '-01') : Carbon::today();
        $result = $this->schedule->generateMonth($month, true);

        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['error'] ?? 'Failed to generate the schedule.'], 422);
        }

        $this->audit->log($request, 'roi.schedule.regenerate', null, [
            'month' => $month->format('Y-m'), 'generated_days' => $result['generated_days'] ?? 0,
        ]);

        return response()->json([
            'message'  => 'Monthly ROI schedule regenerated.',
            'result'   => $result,
            'schedule' => $this->schedule->currentMonthPreview($month),
        ]);
    }
}
