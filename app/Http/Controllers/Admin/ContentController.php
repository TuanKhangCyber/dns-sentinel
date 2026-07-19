<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\FaqItem;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function index()
    {
        return view('admin.content.index', ['about' => ContentPage::where('slug', 'about')->firstOrFail(), 'faqs' => FaqItem::orderBy('category')->orderBy('sort_order')->get()]);
    }

    public function updateAbout(Request $request, ContentPage $page, AuditService $audit)
    {
        abort_unless($page->slug === 'about', 404);
        $data = $request->validate(['title_vi' => ['required', 'string', 'max:200'], 'title_en' => ['required', 'string', 'max:200'], 'content_vi' => ['required', 'string', 'max:20000'], 'content_en' => ['required', 'string', 'max:20000'], 'is_published' => ['boolean']]);
        $before = $page->toArray();
        $page->update(['title' => ['vi' => $data['title_vi'], 'en' => $data['title_en']], 'content' => ['vi' => $data['content_vi'], 'en' => $data['content_en']], 'is_published' => $request->boolean('is_published'), 'updated_by' => $request->user()->id]);
        $audit->record($request->user(), 'content.about_updated', $page, $before, $page->fresh()->toArray(), $request);

        return back()->with('status', __('platform.saved'));
    }

    public function storeFaq(Request $request, AuditService $audit)
    {
        $faq = FaqItem::create($this->faqData($request));
        $audit->record($request->user(), 'faq.created', $faq, [], $faq->toArray(), $request);

        return back()->with('status', __('platform.saved'));
    }

    public function updateFaq(Request $request, FaqItem $faq, AuditService $audit)
    {
        $before = $faq->toArray();
        $faq->update($this->faqData($request));
        $audit->record($request->user(), 'faq.updated', $faq, $before, $faq->fresh()->toArray(), $request);

        return back()->with('status', __('platform.saved'));
    }

    public function destroyFaq(Request $request, FaqItem $faq, AuditService $audit)
    {
        $before = $faq->toArray();
        $audit->record($request->user(), 'faq.deleted', $faq, $before, [], $request);
        $faq->delete();

        return back()->with('status', __('platform.saved'));
    }

    private function faqData(Request $request): array
    {
        $data = $request->validate(['question_vi' => ['required', 'string', 'max:1000'], 'question_en' => ['required', 'string', 'max:1000'], 'answer_vi' => ['required', 'string', 'max:10000'], 'answer_en' => ['required', 'string', 'max:10000'], 'category' => ['required', 'regex:/^[a-z][a-z0-9_]{1,79}$/'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'is_published' => ['boolean']]);

        return ['question' => ['vi' => $data['question_vi'], 'en' => $data['question_en']], 'answer' => ['vi' => $data['answer_vi'], 'en' => $data['answer_en']], 'category' => $data['category'], 'sort_order' => $data['sort_order'], 'is_published' => $request->boolean('is_published'), 'updated_by' => $request->user()->id];
    }
}
