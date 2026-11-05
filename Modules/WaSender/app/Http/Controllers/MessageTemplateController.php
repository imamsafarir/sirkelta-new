<?php

namespace Modules\WaSender\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\WaSender\Models\MessageTemplate;

class MessageTemplateController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('wasender.contacts.index', ['tab' => 'templates']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'has_random_code' => ['nullable', 'boolean'],
        ]);

        $request->user()->wasenderMessageTemplates()->create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => trim((string) ($validated['category'] ?? '')) ?: 'Umum',
            'has_random_code' => $request->boolean('has_random_code'),
        ]);

        return redirect()->back()->with('success', 'Draft template pesan berhasil disimpan.');
    }

    public function update(Request $request, MessageTemplate $template): RedirectResponse
    {
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'has_random_code' => ['nullable', 'boolean'],
        ]);

        $template->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => trim((string) ($validated['category'] ?? '')) ?: 'Umum',
            'has_random_code' => $request->boolean('has_random_code'),
        ]);

        return redirect()->back()->with('success', 'Draft template pesan berhasil diperbarui.');
    }

    public function destroy(Request $request, MessageTemplate $template): RedirectResponse
    {
        if ($template->user_id !== auth()->id()) {
            abort(403);
        }

        $template->delete();

        return redirect()->back()->with('success', 'Draft template pesan berhasil dihapus.');
    }
}
