<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\FaqItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function about(): View
    {
        return view('content.about', ['page' => ContentPage::where('slug', 'about')->where('is_published', true)->firstOrFail()]);
    }

    public function faq(Request $request): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:80']]);
        $items = FaqItem::query()->where('is_published', true)
            ->when(filled($validated['category'] ?? null), fn ($query) => $query->where('category', $validated['category']))
            ->when(filled($validated['q'] ?? null), function ($query) use ($validated) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where(fn ($nested) => $nested->where('question', 'like', $term)->orWhere('answer', 'like', $term));
            })->orderBy('category')->orderBy('sort_order')->get();

        return view('content.faq', ['items' => $items]);
    }
}
