<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\FeatureAccessException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CreditService;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'plan' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(['active', 'suspended'])], 'role' => ['nullable', Rule::in(['user', 'admin'])]]);
        $users = User::query()->with(['plan:id,code,name', 'wallet:user_id,balance'])
            ->withCount(['scans', 'reconHistories'])
            ->when(filled($validated['q'] ?? null), fn ($q) => $q->where(fn ($n) => $n->where('name', 'like', '%'.$validated['q'].'%')->orWhere('email', 'like', '%'.$validated['q'].'%')))
            ->when(filled($validated['plan'] ?? null), fn ($q) => $q->whereHas('plan', fn ($p) => $p->where('code', $validated['plan'])))
            ->when(filled($validated['status'] ?? null), fn ($q) => $q->where('status', $validated['status']))
            ->when(filled($validated['role'] ?? null), fn ($q) => $q->where('role', $validated['role']))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'plans' => Plan::orderBy('id')->get()]);
    }

    public function show(User $user)
    {
        $user->load(['plan', 'wallet']);
        $transactions = $user->creditTransactions()->latest()->paginate(20);

        return view('admin.users.show', ['managedUser' => $user, 'plans' => Plan::orderBy('id')->get(), 'transactions' => $transactions]);
    }

    public function update(Request $request, User $user, MembershipService $memberships, AuditService $audit)
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)], 'role' => ['required', Rule::in(['user', 'admin'])],
            'status' => ['required', Rule::in(['active', 'suspended'])], 'membership_expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        if ($user->isAdmin() && ($data['role'] !== 'admin' || $data['status'] === 'suspended') && User::where('role', 'admin')->where('status', 'active')->count() <= 1) {
            return back()->withErrors(['role' => __('platform.errors.last_admin')]);
        }
        $before = $user->only(['plan_id', 'role', 'status', 'membership_expires_at']);
        $memberships->changePlan($user, Plan::findOrFail($data['plan_id']), $data['membership_expires_at'] ?? null);
        $user->update(['role' => $data['role'], 'status' => $data['status']]);
        $audit->record($request->user(), 'user.updated', $user, $before, $user->only(['plan_id', 'role', 'status', 'membership_expires_at']), $request);

        return back()->with('status', __('platform.saved'));
    }

    public function adjustCredits(Request $request, User $user, CreditService $credits, AuditService $audit)
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        try {
            $transaction = $credits->adjust($user, $data['amount'], $request->user(), $data['reason']);
        } catch (FeatureAccessException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }
        $audit->record($request->user(), 'credits.adjusted', $user, [], ['transaction_id' => $transaction->id, 'amount' => $transaction->amount, 'reason' => $data['reason']], $request);

        return back()->with('status', __('platform.saved'));
    }
}
