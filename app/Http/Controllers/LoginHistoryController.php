<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $histories = $request->user()
            ->loginHistories()
            ->latest('login_at')
            ->paginate(20);

        return view('auth.history', compact('histories'));
    }
}
