<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\FaqItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:80'], 'published' => ['nullable', Rule::in(['1', '0'])]]);
        $faqs = FaqItem::query()
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where(fn ($nested) => $nested->where('question', 'like', '%'.$filters['q'].'%')->orWhere('answer', 'like', '%'.$filters['q'].'%')))
            ->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category', $filters['category']))
            ->when(array_key_exists('published', $filters) && $filters['published'] !== null, fn ($query) => $query->where('is_published', $filters['published'] === '1'))
            ->orderBy('category')->orderBy('sort_order')->paginate(15)->withQueryString();

        return view('admin.content.index', ['about' => ContentPage::where('slug', 'about')->firstOrFail(), 'faqs' => $faqs, 'categories' => FaqItem::distinct()->orderBy('category')->pluck('category')]);
    }

    public function updateAbout(Request $request, ContentPage $page, AuditService $audit)
    {
        abort_unless($page->slug === 'about', 404);
        $data = $request->validate(['title_vi' => ['required', 'string', 'max:200'], 'title_en' => ['required', 'string', 'max:200'], 'content_vi' => ['required', 'string', 'max:20000'], 'content_en' => ['required', 'string', 'max:20000'], 'is_published' => ['boolean']]);
        DB::transaction(function () use ($request, $page, $data, $audit): void {
            $before = $page->toArray();
            $page->update(['title' => ['vi' => $data['title_vi'], 'en' => $data['title_en']], 'content' => ['vi' => $data['content_vi'], 'en' => $data['content_en']], 'is_published' => $request->boolean('is_published'), 'updated_by' => $request->user()->id]);
            $audit->record($request->user(), 'content.about_updated', $page, $before, $page->fresh()->toArray(), $request);
        }, 3);

        return back()->with('status', __('platform.saved'));
    }

    public function storeFaq(Request $request, AuditService $audit)
    {
        DB::transaction(function () use ($request, $audit): void {
            $faq = FaqItem::create($this->faqData($request));
            $audit->record($request->user(), 'faq.created', $faq, [], $faq->toArray(), $request);
        }, 3);

        return back()->with('status', __('platform.saved'));
    }

    public function updateFaq(Request $request, FaqItem $faq, AuditService $audit)
    {
        DB::transaction(function () use ($request, $faq, $audit): void {
            $before = $faq->toArray();
            $faq->update($this->faqData($request));
            $audit->record($request->user(), 'faq.updated', $faq, $before, $faq->fresh()->toArray(), $request);
        }, 3);

        return back()->with('status', __('platform.saved'));
    }

    public function destroyFaq(Request $request, FaqItem $faq, AuditService $audit)
    {
        DB::transaction(function () use ($request, $faq, $audit): void {
            $before = $faq->toArray();
            $audit->record($request->user(), 'faq.deleted', $faq, $before, [], $request);
            $faq->delete();
        }, 3);

        return back()->with('status', __('platform.saved'));
    }

    private function faqData(Request $request): array
    {
        $data = $request->validate(['question_vi' => ['required', 'string', 'max:1000'], 'question_en' => ['required', 'string', 'max:1000'], 'answer_vi' => ['required', 'string', 'max:10000'], 'answer_en' => ['required', 'string', 'max:10000'], 'category' => ['required', 'regex:/^[a-z][a-z0-9_]{1,79}$/'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'is_published' => ['boolean']]);

        return ['question' => ['vi' => $data['question_vi'], 'en' => $data['question_en']], 'answer' => ['vi' => $data['answer_vi'], 'en' => $data['answer_en']], 'category' => $data['category'], 'sort_order' => $data['sort_order'], 'is_published' => $request->boolean('is_published'), 'updated_by' => $request->user()->id];
    }
}
