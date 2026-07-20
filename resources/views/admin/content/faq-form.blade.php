<form class="admin-form" method="POST" action="{{ $faq ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" data-submit-lock>
    @csrf @if($faq)@method('PUT')@endif
    <div class="form-section-grid"><label>{{ __('platform.question_vi') }}<input name="question_vi" value="{{ $faq?->question['vi'] ?? '' }}" required maxlength="1000"></label><label>{{ __('platform.question_en') }}<input name="question_en" value="{{ $faq?->question['en'] ?? '' }}" required maxlength="1000"></label></div>
    <div class="form-section-grid"><label>{{ __('platform.answer_vi') }}<textarea name="answer_vi" required maxlength="10000">{{ $faq?->answer['vi'] ?? '' }}</textarea></label><label>{{ __('platform.answer_en') }}<textarea name="answer_en" required maxlength="10000">{{ $faq?->answer['en'] ?? '' }}</textarea></label></div>
    <div class="form-section-grid"><label>{{ __('platform.category') }}<input name="category" value="{{ $faq?->category ?? 'general' }}" required pattern="[a-z][a-z0-9_]{1,79}"></label><label>{{ __('platform.order') }}<input type="number" name="sort_order" value="{{ $faq?->sort_order ?? 0 }}" min="0" max="65535" required></label></div>
    <label class="admin-switch"><input type="checkbox" name="is_published" value="1" @checked($faq?->is_published ?? true)><span></span>{{ __('platform.published') }}</label><button>{{ __('platform.save') }}</button>
</form>
