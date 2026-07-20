<time class="current-datetime" data-current-datetime datetime="{{ now()->toIso8601String() }}">
    <x-icon name="calendar" />
    <span data-current-datetime-label>{{ now()->format('d/m/Y H:i:s') }}</span>
</time>
