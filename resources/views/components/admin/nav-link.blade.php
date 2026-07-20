@props(['href', 'active' => false, 'icon'])

<a href="{{ $href }}" @if($active) aria-current="page" @endif>
    <x-icon :name="$icon" />
    <span>{{ $slot }}</span>
</a>
