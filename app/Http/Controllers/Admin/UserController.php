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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'plan' => ['nullable', Rule::exists('plans', 'code')], 'status' => ['nullable', Rule::in(['active', 'suspended'])], 'role' => ['nullable', Rule::in(['user', 'admin'])]]);
        $users = User::query()->with(['plan:id,code,name', 'wallet:user_id,balance'])
            ->withCount(['scans', 'reconHistories'])
            ->when(filled($validated['q'] ?? null), fn ($q) => $q->where(fn ($n) => $n->where('name', 'like', '%'.$validated['q'].'%')->orWhere('email', 'like', '%'.$validated['q'].'%')))
            ->when(filled($validated['plan'] ?? null), fn ($q) => $q->whereHas('plan', fn ($p) => $p->where('code', $validated['plan'])))
            ->when(filled($validated['status'] ?? null), fn ($q) => $q->where('status', $validated['status']))
            ->when(filled($validated['role'] ?? null), fn ($q) => $q->where('role', $validated['role']))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'plans' => Plan::orderBy('id')->get()]);
    }

    public function show(Request $request, User $user)
    {
        $filters = $request->validate([
            'ledger_type' => ['nullable', 'string', 'max:30'],
            'ledger_feature' => ['nullable', 'string', 'max:80'],
            'ledger_from' => ['nullable', 'date'],
            'ledger_to' => ['nullable', 'date', 'after_or_equal:ledger_from'],
        ]);
        $user->load(['plan', 'wallet']);
        $transactions = $user->creditTransactions()
            ->when(filled($filters['ledger_type'] ?? null), fn ($query) => $query->where('type', $filters['ledger_type']))
            ->when(filled($filters['ledger_feature'] ?? null), fn ($query) => $query->where('feature_code', $filters['ledger_feature']))
            ->when(filled($filters['ledger_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['ledger_from']))
            ->when(filled($filters['ledger_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['ledger_to']))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.users.show', [
            'managedUser' => $user,
            'plans' => Plan::orderBy('id')->get(),
            'transactions' => $transactions,
            'transactionTypes' => $user->creditTransactions()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type'),
            'transactionFeatures' => $user->creditTransactions()->whereNotNull('feature_code')->distinct()->orderBy('feature_code')->pluck('feature_code'),
        ]);
    }

    public function update(Request $request, User $user, MembershipService $memberships, AuditService $audit)
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)], 'role' => ['required', Rule::in(['user', 'admin'])],
            'status' => ['required', Rule::in(['active', 'suspended'])], 'membership_expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        DB::transaction(function () use ($request, $user, $data, $memberships, $audit): void {
            $managedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $activeAdmins = User::query()->where('role', 'admin')->where('status', 'active')->lockForUpdate()->get();
            if ($managedUser->isAdmin() && ($data['role'] !== 'admin' || $data['status'] === 'suspended') && $activeAdmins->count() <= 1) {
                throw ValidationException::withMessages(['role' => __('platform.errors.last_admin')]);
            }
            $before = $managedUser->only(['plan_id', 'role', 'status', 'membership_expires_at']);
            $memberships->changePlan($managedUser, Plan::findOrFail($data['plan_id']), $data['membership_expires_at'] ?? null);
            $managedUser->update(['role' => $data['role'], 'status' => $data['status']]);
            $audit->record($request->user(), 'user.updated', $managedUser, $before, $managedUser->only(['plan_id', 'role', 'status', 'membership_expires_at']), $request);
        }, 3);

        return back()->with('status', __('platform.saved'));
    }

    public function adjustCredits(Request $request, User $user, CreditService $credits, AuditService $audit)
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'], 'reason' => ['required', 'string', 'min:5', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        try {
            DB::transaction(function () use ($request, $user, $data, $credits, $audit): void {
                $transaction = $credits->adjust($user, $data['amount'], $request->user(), $data['reason'], $data['idempotency_key']);
                if ($transaction->wasRecentlyCreated) {
                    $audit->record($request->user(), 'credits.adjusted', $user, [], ['transaction_id' => $transaction->id, 'amount' => $transaction->amount, 'reason' => $data['reason']], $request);
                }
            }, 3);
        } catch (FeatureAccessException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('status', __('platform.saved'));
    }
}
