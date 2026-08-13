<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqItemRequest;
use App\Http\Requests\Admin\UpdateFaqItemRequest;
use App\Models\FaqItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FaqItemController extends Controller
{
    public function index(): Response
    {
        $faqItems = FaqItem::query()->orderBy('order')->paginate(20)->withQueryString();

        return Inertia::render('Admin/FaqItems/Index', [
            'faqItems' => $faqItems,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/FaqItems/Form');
    }

    public function store(StoreFaqItemRequest $request): RedirectResponse
    {
        FaqItem::create($request->validated());

        return redirect()->route('admin.faq-items.index')->with('success', 'Question FAQ créée.');
    }

    public function edit(FaqItem $faqItem): Response
    {
        return Inertia::render('Admin/FaqItems/Form', [
            'faqItem' => [
                'id' => $faqItem->id,
                'question' => $faqItem->getTranslations('question'),
                'answer' => $faqItem->getTranslations('answer'),
                'order' => $faqItem->order,
            ],
        ]);
    }

    public function update(UpdateFaqItemRequest $request, FaqItem $faqItem): RedirectResponse
    {
        $faqItem->update($request->validated());

        return redirect()->route('admin.faq-items.index')->with('success', 'Question FAQ mise à jour.');
    }

    public function destroy(FaqItem $faqItem): RedirectResponse
    {
        $faqItem->delete();

        return redirect()->route('admin.faq-items.index')->with('success', 'Question FAQ supprimée.');
    }
}
