@extends('layouts.app')

@section('title', __('ui.login_history'))

@section('content')
<section class="platform-hero compact-hero">
    <p class="eyebrow">{{ __('ui.account_activity') }}</p>
    <h1><x-icon name="history" /> {{ __('ui.login_history') }}</h1>
    <p>{{ __('ui.login_history_intro') }}</p>
</section>

<section class="platform-card">
    @if($histories->isEmpty())
        <p class="empty-state">{{ __('ui.no_login_history') }}</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('ui.login_at') }}</th>
                        <th>{{ __('ui.logout_at') }}</th>
                        <th>{{ __('ui.ip_address') }}</th>
                        <th>{{ __('ui.device') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($histories as $history)
                        <tr>
                            <td>{{ $history->login_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                @if($history->logout_at)
                                    {{ $history->logout_at->format('d/m/Y H:i:s') }}
                                @else
                                    <span class="status-badge safe">{{ __('ui.active_session') }}</span>
                                @endif
                            </td>
                            <td>{{ $history->ip_address ?? '—' }}</td>
                            <td class="login-device">{{ $history->user_agent ?? __('ui.unknown') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $histories->links() }}
    @endif
</section>
@endsection
