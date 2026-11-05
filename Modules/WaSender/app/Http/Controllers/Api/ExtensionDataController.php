<?php

namespace Modules\WaSender\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\WaSender\Models\Contact;

class ExtensionDataController extends Controller
{
    public function contacts(Request $request): JsonResponse
    {
        $user = $request->user();

        $contacts = $user->wasenderContacts()
            ->latest()
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'group_name' => $c->group_name ?? 'Umum',
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $contacts,
            'total' => $contacts->count(),
        ]);
    }

    public function saveContacts(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['required', 'string', 'max:50'],
            'group_name' => ['nullable', 'string', 'max:100'],
        ]);

        $groupName = trim((string) ($validated['group_name'] ?? '')) ?: 'Umum';
        $contactsData = $validated['contacts'];

        $savedCount = 0;
        $skippedCount = 0;

        foreach ($contactsData as $item) {
            $rawPhone = (string) ($item['phone'] ?? '');
            $cleanPhone = Contact::normalizePhone($rawPhone);
            if (empty($cleanPhone)) {
                $skippedCount++;

                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if (empty($name)) {
                $name = 'Kontak '.substr($cleanPhone, -4);
            }

            $user->wasenderContacts()->updateOrCreate(
                ['phone' => $cleanPhone],
                [
                    'name' => $name,
                    'group_name' => $groupName,
                ]
            );

            $savedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menyimpan {$savedCount} kontak ke grup '{$groupName}'.",
            'saved_count' => $savedCount,
            'skipped_count' => $skippedCount,
            'total' => $user->wasenderContacts()->count(),
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        $user = $request->user();

        $templates = $user->wasenderMessageTemplates()
            ->latest()
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'content' => $t->content,
                    'category' => $t->category ?? 'Umum',
                    'has_random_code' => (bool) $t->has_random_code,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $templates,
            'total' => $templates->count(),
        ]);
    }
}
