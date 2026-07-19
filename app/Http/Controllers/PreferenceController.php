<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['nullable', Rule::in(['vi', 'en'])],
            'country' => ['nullable', Rule::in(['VN', 'US', 'GB', 'JP', 'KR', 'SG'])],
        ]);

        if (isset($validated['locale'])) {
            $request->session()->put('locale', $validated['locale']);
        }

        if (isset($validated['country'])) {
            $request->session()->put('country', $validated['country']);
        }

        return back();
    }
}
