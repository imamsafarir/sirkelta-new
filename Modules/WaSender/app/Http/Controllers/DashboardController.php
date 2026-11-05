<?php

namespace Modules\WaSender\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. Sesi aktif ekstensi Chrome
        $activeSession = $user->wasenderExtensionSessions()
            ->where('status', 'active')
            ->latest()
            ->first();

        $sessionData = null;
        if ($activeSession) {
            $sessionData = [
                'id' => $activeSession->id,
                'device_name' => $activeSession->device_name ?? 'Browser Chrome',
                'ip_address' => $activeSession->ip_address,
                'status' => $activeSession->status,
                'is_active' => $activeSession->isCurrentlyActive(),
                'last_heartbeat_at' => $activeSession->last_heartbeat_at?->diffForHumans() ?? 'Baru saja',
                'created_at' => $activeSession->created_at->format('d M Y H:i'),
            ];
        }

        // 2. Ringkasan statistik
        $totalContacts = $user->wasenderContacts()->count();
        $totalGroups = $user->wasenderContacts()
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->count('group_name');
        $totalTemplates = $user->wasenderMessageTemplates()->count();
        $todayUsage = $user->getTodayUsage();

        return Inertia::render('WaSender::Dashboard', [
            'activeSession' => $sessionData,
            'stats' => [
                'contacts_count' => $totalContacts,
                'groups_count' => $totalGroups,
                'templates_count' => $totalTemplates,
                'sent_today' => $todayUsage->messages_sent,
            ],
            'downloadUrl' => route('wasender.download.extension'),
        ]);
    }

    public function revokeExtensionSession(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->wasenderExtensionSessions()
            ->where('status', 'active')
            ->update(['status' => 'revoked']);

        return redirect()->back()->with('success', 'Sesi ekstensi Chrome berhasil diputuskan.');
    }

    public function downloadExtension(Request $request): BinaryFileResponse
    {
        $zipPath = public_path('downloads/wasender-extension.zip');

        if (! file_exists($zipPath)) {
            abort(404, 'File ekstensi belum tersedia untuk diunduh.');
        }

        // Sinkronisasi otomatis config.json di dalam zip dengan URL server saat ini
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive;
            if ($zip->open($zipPath) === true) {
                $appUrl = rtrim((string) ($request->schemeAndHttpHost() ?: config('app.url') ?: request()->root()), '/');
                $apiUrl = $appUrl.'/api/extension';

                $configContent = json_encode([
                    'api_url' => $apiUrl,
                    'app_url' => $appUrl,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                $zip->addFromString('config.json', $configContent);
                $zip->close();
            }
        }

        return response()->download($zipPath, 'wasender-chrome-extension.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
