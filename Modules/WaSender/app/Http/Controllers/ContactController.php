<?php

namespace Modules\WaSender\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\WaSender\Models\Contact;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = $user->wasenderContacts();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        if ($group = $request->input('group')) {
            if ($group === 'Umum') {
                $query->where(function ($q) {
                    $q->where('group_name', 'Umum')
                        ->orWhereNull('group_name')
                        ->orWhere('group_name', '');
                });
            } elseif ($group !== 'all' && $group !== '__all__' && $group !== 'Semua Kontak') {
                $query->where('group_name', $group);
            }
        }

        $contacts = $query->latest()->paginate(15)->withQueryString()->through(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'group_name' => $c->group_name ?? 'Umum',
                'notes' => $c->notes,
                'created_at' => $c->created_at->format('d M Y'),
            ];
        });

        // Kategori grup unik beserta jumlah kontaknya
        $groupsWithCounts = $user->wasenderContacts()
            ->select('group_name')
            ->selectRaw('count(*) as count')
            ->groupBy('group_name')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->group_name ?: 'Umum',
                    'count' => (int) $item->count,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();

        $groups = array_values(array_unique(array_map(fn ($g) => $g['name'], $groupsWithCounts)));
        if (! in_array('Umum', $groups, true)) {
            array_unshift($groups, 'Umum');
        }

        // Templates list untuk Tab Draft Pesan
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
                    'created_at' => $t->created_at->format('d M Y'),
                ];
            });

        $isPro = $user->isPro('wasender');

        return Inertia::render('WaSender::Contacts/Index', [
            'contacts' => $contacts,
            'groups' => $groups,
            'groupsWithCounts' => $groupsWithCounts,
            'templates' => $templates,
            'defaultTab' => $request->input('tab', 'contacts'),
            'filters' => [
                'search' => $request->input('search', ''),
                'group' => $request->input('group', 'all'),
            ],
            'quota' => [
                'total_saved' => $user->wasenderContacts()->count(),
                'max_contacts' => $isPro ? -1 : 100,
                'is_unlimited' => $isPro,
                'remaining' => $isPro ? 'Unlimited' : max(0, 100 - $user->wasenderContacts()->count()),
                'can_import_csv' => true,
            ],
            'templatesQuota' => [
                'total_saved' => $templates->count(),
                'max_templates' => $isPro ? -1 : 10,
                'is_unlimited' => $isPro,
                'remaining' => $isPro ? 'Unlimited' : max(0, 10 - $templates->count()),
                'can_use_spintax' => $isPro,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $cleanPhone = Contact::normalizePhone($validated['phone']);

        if (empty($cleanPhone)) {
            return redirect()->back()->withErrors(['phone' => 'Format nomor WhatsApp tidak valid.']);
        }

        $user = $request->user();

        $user->wasenderContacts()->updateOrCreate(
            ['phone' => $cleanPhone],
            [
                'name' => $validated['name'],
                'group_name' => trim((string) ($validated['group_name'] ?? '')) ?: 'Umum',
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Kontak berhasil disimpan.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        if ($contact->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $cleanPhone = Contact::normalizePhone($validated['phone']);

        if (empty($cleanPhone)) {
            return redirect()->back()->withErrors(['phone' => 'Format nomor WhatsApp tidak valid.']);
        }

        $contact->update([
            'name' => $validated['name'],
            'phone' => $cleanPhone,
            'group_name' => trim((string) ($validated['group_name'] ?? '')) ?: 'Umum',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Kontak berhasil diperbarui.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        if ($contact->user_id !== auth()->id()) {
            abort(403);
        }

        $contact->delete();

        return redirect()->back()->with('success', 'Kontak berhasil dihapus.');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required_without:csv_file', 'nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'csv_file' => ['required_without:file', 'nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'group_name' => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('file') ?? $request->file('csv_file');
        $defaultGroup = trim((string) $request->input('group_name', '')) ?: 'Umum';

        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return redirect()->back()->with('error', 'Gagal membaca berkas CSV.');
        }

        $imported = 0;
        $skipped = 0;
        $rowNumber = 0;

        $nameCol = 0;
        $phoneCol = 1;
        $groupCol = 2;

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $rowNumber++;

            if ($rowNumber === 1) {
                // Deteksi header kolom jika ada
                $firstCell = strtolower(trim((string) ($row[0] ?? '')));
                if (in_array($firstCell, ['name', 'nama', 'kontak'], true)) {
                    foreach ($row as $idx => $header) {
                        $h = strtolower(trim((string) $header));
                        if (in_array($h, ['name', 'nama'], true)) {
                            $nameCol = $idx;
                        } elseif (in_array($h, ['phone', 'telepon', 'whatsapp', 'nomor', 'no_hp'], true)) {
                            $phoneCol = $idx;
                        } elseif (in_array($h, ['group', 'grup', 'kategori'], true)) {
                            $groupCol = $idx;
                        }
                    }

                    continue;
                }
            }

            $rawPhone = trim((string) ($row[$phoneCol] ?? ''));
            $cleanPhone = Contact::normalizePhone($rawPhone);

            if (empty($cleanPhone)) {
                $skipped++;

                continue;
            }

            $name = trim((string) ($row[$nameCol] ?? ''));
            if (empty($name)) {
                $name = 'Kontak '.substr($cleanPhone, -4);
            }

            $groupName = trim((string) ($row[$groupCol] ?? '')) ?: $defaultGroup;

            $request->user()->wasenderContacts()->updateOrCreate(
                ['phone' => $cleanPhone],
                [
                    'name' => $name,
                    'group_name' => $groupName,
                ]
            );

            $imported++;
        }

        fclose($handle);

        return redirect()->back()->with('success', "Impor selesai: {$imported} kontak berhasil disimpan, {$skipped} dilewati.");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $group = $request->input('group');

        $query = $user->wasenderContacts();

        if ($group && $group !== 'all' && $group !== '__all__') {
            $query->where('group_name', $group);
        }

        $fileName = 'kontak-wasender-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nama', 'Nomor WhatsApp', 'Grup', 'Catatan']);

            $query->chunk(500, function ($contacts) use ($out) {
                foreach ($contacts as $c) {
                    fputcsv($out, [
                        $c->name,
                        $c->phone,
                        $c->group_name ?? 'Umum',
                        $c->notes ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
