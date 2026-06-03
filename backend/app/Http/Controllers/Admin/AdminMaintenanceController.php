<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\MaintenanceService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class AdminMaintenanceController extends Controller
{
    public function __construct(
        protected MaintenanceService $maintenance,
        protected SettingsService $settings,
        protected AuditService $audit,
    ) {}

    public function status()
    {
        return response()->json($this->maintenance->status());
    }

    public function toggle(Request $request)
    {
        $data = $request->validate(['manual' => ['required', 'boolean']]);
        $this->settings->set('maintenance_manual', $data['manual']);
        $this->maintenance->sync();
        $this->audit->log($request, 'maintenance.toggle', null, $data);
        return response()->json(['message' => 'Maintenance mode updated.', 'status' => $this->maintenance->status()]);
    }
}
