<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\FaqItem;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            'free' => ['name' => 'Free', 'description' => 'Core DNS investigation for individual users.', 'monthly_credit_allowance' => 25, 'history_retention_days' => 7],
            'plus' => ['name' => 'Plus', 'description' => 'Advanced recon, scanning, reports and longer history.', 'monthly_credit_allowance' => 500, 'history_retention_days' => 90],
        ];
        foreach ($plans as $code => $attributes) {
            Plan::firstOrCreate(['code' => $code], [...$attributes, 'is_active' => true]);
        }

        foreach (config('features.registry', []) as $code => $definition) {
            $feature = Feature::firstOrCreate(['code' => $code], [
                'name' => $definition['name'], 'description' => $definition['description'] ?? null,
                'category' => $definition['category'], 'is_enabled' => $definition['enabled'],
                'is_visible' => $definition['visible'], 'credit_cost' => $definition['cost'],
                'sort_order' => $definition['sort_order'] ?? 0,
            ]);
            foreach ($definition['plans'] as $planCode) {
                $plan = Plan::where('code', $planCode)->firstOrFail();
                if (! $feature->plans()->whereKey($plan->id)->exists()) {
                    $feature->plans()->attach($plan->id, ['is_enabled' => true]);
                }
            }
        }

        foreach ($this->settings() as $key => [$value, $type, $public]) {
            SystemSetting::firstOrCreate(['key' => $key], ['value' => (string) $value, 'type' => $type, 'is_public' => $public]);
        }

        ContentPage::firstOrCreate(['slug' => 'about'], [
            'title' => ['vi' => 'Giới thiệu', 'en' => 'About'],
            'content' => [
                'vi' => 'KiemTraDNS là công cụ điều tra DNS và bảo mật dành cho hệ thống được cấp phép. Ứng dụng kết hợp DNS, RDAP, SSL, security headers, Nmap và bản đồ Leaflet. Google reCAPTCHA có thể được tải trên các biểu mẫu cần chống spam và abuse.',
                'en' => 'KiemTraDNS is a DNS and security investigation tool for authorized systems. It combines DNS, RDAP, SSL, security headers, Nmap and Leaflet maps. Google reCAPTCHA may be loaded on forms that require spam and abuse protection.',
            ],
            'is_published' => true,
        ]);

        foreach ($this->faqs() as $item) {
            FaqItem::firstOrCreate(['code' => $item['code']], $item);
        }
    }

    private function settings(): array
    {
        return [
            'site_name' => [config('app.name', 'KiemTraDNS'), 'string', true],
            'maintenance_notice' => ['', 'string', true],
            'registration_enabled' => ['1', 'boolean', true],
            'default_plan' => [config('features.default_plan', 'free'), 'string', false],
            'default_signup_credits' => [(string) config('features.signup_credits', 10), 'integer', false],
            'recaptcha_enabled' => [config('recaptcha.enabled') ? '1' : '0', 'boolean', false],
            'recaptcha_login_enabled' => [config('recaptcha.login_enabled') ? '1' : '0', 'boolean', false],
            'recaptcha_register_enabled' => [config('recaptcha.register_enabled') ? '1' : '0', 'boolean', false],
            'recaptcha_password_reset_enabled' => [config('recaptcha.password_reset_enabled') ? '1' : '0', 'boolean', false],
            'recaptcha_min_score' => [(string) config('recaptcha.minimum_score', 0.5), 'float', false],
            'recaptcha_login_failure_threshold' => [(string) config('recaptcha.login_failure_threshold', 2), 'integer', false],
            'support_content' => ['', 'string', true],
        ];
    }

    private function faqs(): array
    {
        $items = [
            ['What is the difference between Free and Plus?', 'Free includes core DNS tools. Plus enables advanced recon, scanner, comparison and report capabilities.', 'Free và Plus khác nhau thế nào?', 'Free gồm công cụ DNS cơ bản. Plus mở recon nâng cao, scanner, so sánh và báo cáo.', 'membership'],
            ['Why does reCAPTCHA sometimes appear during login?', 'It is requested after repeated failed attempts to reduce automated abuse. It does not replace your password.', 'Vì sao đăng nhập đôi lúc yêu cầu reCAPTCHA?', 'reCAPTCHA được yêu cầu sau nhiều lần đăng nhập sai để giảm abuse tự động và không thay thế mật khẩu.', 'privacy'],
            ['What should I do if reCAPTCHA does not load?', 'Check network or content blockers and try again. Contact support if verification remains unavailable.', 'Làm gì khi reCAPTCHA không tải?', 'Kiểm tra mạng hoặc trình chặn nội dung rồi thử lại. Liên hệ hỗ trợ nếu vẫn không xác minh được.', 'privacy'],
            ['Does reCAPTCHA replace account authentication?', 'No. It is an anti-bot signal; authentication and rate limiting still apply.', 'reCAPTCHA có thay thế xác thực tài khoản không?', 'Không. Đây là tín hiệu chống bot; xác thực và rate limiting vẫn được áp dụng.', 'privacy'],
        ];

        return array_map(fn ($row, $index) => [
            'code' => 'default_faq_'.($index + 1), 'question' => ['en' => $row[0], 'vi' => $row[2]], 'answer' => ['en' => $row[1], 'vi' => $row[3]],
            'category' => $row[4], 'sort_order' => $index, 'is_published' => true,
        ], $items, array_keys($items));
    }
}
