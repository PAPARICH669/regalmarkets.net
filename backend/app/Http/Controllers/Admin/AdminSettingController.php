<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function __construct(protected SettingsService $settings, protected AuditService $audit) {}

    public function index()
    {
        return response()->json($this->settings->all());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'roi_daily_percent'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'roi_return_multiple'    => ['nullable', 'numeric', 'min:1', 'max:100'],
            'min_deposit'            => ['nullable', 'numeric', 'min:0'],
            'min_withdrawal'         => ['nullable', 'numeric', 'min:0'],
            'max_withdrawal_daily'   => ['nullable', 'numeric', 'min:0'],
            'min_transfer'           => ['nullable', 'numeric', 'min:0'],
            'min_reinvest'           => ['nullable', 'numeric', 'min:0'],
            'withdrawal_fee'         => ['nullable', 'numeric', 'min:0'],
            'withdrawal_max_per_day' => ['nullable', 'integer', 'min:1', 'max:50'],
            'transfer_fee_percent'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sponsor_bonus_percents' => ['nullable', 'array'],
            'match_percents'         => ['nullable', 'array'],
            'maintenance_start'      => ['nullable', 'string'],
            'maintenance_end'        => ['nullable', 'string'],
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $this->settings->set($key, $value);
            }
        }

        $this->audit->log($request, 'settings.update', null, array_keys($data));
        return response()->json(['message' => 'Settings updated.', 'settings' => $this->settings->all()]);
    }
}
