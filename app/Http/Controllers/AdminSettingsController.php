<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings'       => 'required|array',
            'settings.*.key' => 'required|string|exists:settings,key',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($request->settings as $item) {
            Setting::where('key', $item['key'])->update(['value' => $item['value'] ?? '']);
        }

        AuditLogService::log('settings.updated', null, [
            'keys_updated' => collect($request->settings)->pluck('key')->toArray(),
        ]);

        return response()->json([
            'message' => __('api.param_tres_mis_jour_avec_succ_'),
            'settings' => Setting::all()->groupBy('group'),
        ]);
    }
}
