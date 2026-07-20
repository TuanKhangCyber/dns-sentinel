<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\RecaptchaService;
use App\Services\SystemSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    private const TYPES = [
        'site_name' => ['string', true], 'maintenance_notice' => ['string', true], 'registration_enabled' => ['boolean', true],
        'default_plan' => ['string', false], 'default_signup_credits' => ['integer', false], 'support_content' => ['string', true],
        'recaptcha_enabled' => ['boolean', false], 'recaptcha_login_enabled' => ['boolean', false],
        'recaptcha_register_enabled' => ['boolean', false], 'recaptcha_password_reset_enabled' => ['boolean', false],
        'recaptcha_type' => ['string', false], 'recaptcha_login_always_visible' => ['boolean', false],
        'recaptcha_min_score' => ['float', false], 'recaptcha_login_failure_threshold' => ['integer', false],
    ];

    public function index(RecaptchaService $recaptcha)
    {
        return view('admin.settings.index', ['settings' => SystemSetting::pluck('value', 'key'), 'plans' => Plan::where('is_active', true)->get(), 'recaptchaConfigured' => $recaptcha->configured()]);
    }

    public function update(Request $request, SystemSettingService $settings, RecaptchaService $recaptcha, AuditService $audit)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'], 'maintenance_notice' => ['nullable', 'string', 'max:1000'],
            'registration_enabled' => ['boolean'], 'default_plan' => ['required', Rule::exists('plans', 'code')->where('is_active', true)],
            'default_signup_credits' => ['required', 'integer', 'min:0', 'max:1000000'], 'support_content' => ['nullable', 'string', 'max:2000'],
            'recaptcha_enabled' => ['boolean'], 'recaptcha_login_enabled' => ['boolean'], 'recaptcha_register_enabled' => ['boolean'],
            'recaptcha_password_reset_enabled' => ['boolean'], 'recaptcha_type' => ['required', Rule::in(['checkbox', 'score'])],
            'recaptcha_login_always_visible' => ['boolean'], 'recaptcha_min_score' => ['required', 'numeric', 'between:0.1,1'],
            'recaptcha_login_failure_threshold' => ['required', 'integer', 'between:1,10'],
        ]);
        if ($request->boolean('recaptcha_enabled') && ! $recaptcha->configured()) {
            return back()->withErrors(['recaptcha_enabled' => __('platform.errors.recaptcha_keys_missing')]);
        }
        DB::transaction(function () use ($request, $data, $settings, $audit): void {
            $before = SystemSetting::whereIn('key', array_keys(self::TYPES))->pluck('value', 'key')->all();
            foreach (self::TYPES as $key => [$type, $public]) {
                $value = $type === 'boolean' ? $request->boolean($key) : ($data[$key] ?? '');
                $settings->set($key, $value, $type, $public, $request->user()->id, false);
            }
            $after = SystemSetting::whereIn('key', array_keys(self::TYPES))->pluck('value', 'key')->all();
            $audit->record($request->user(), 'settings.updated', SystemSetting::class, $before, $after, $request);
        }, 3);
        $settings->forgetMany(array_keys(self::TYPES));

        return back()->with('status', __('platform.saved'));
    }
}
