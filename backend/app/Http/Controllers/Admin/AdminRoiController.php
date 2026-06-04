<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentPackage;
use App\Models\RoiLog;
use App\Services\AuditService;
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
}
