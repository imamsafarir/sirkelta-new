<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\WebsiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSupportController extends Controller
{
    /**
     * Display list of incoming support complaints (Aduan Masuk).
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'open');

        $query = SupportTicket::query()
            ->with(['user:id,name,username,email'])
            ->withCount([
                'messages',
                'messages as unread_count' => function ($q) {
                    $q->where('sender_type', 'user')->where('is_read', false);
                },
            ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $tickets = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'open_count' => SupportTicket::where('status', 'open')->count(),
            'resolved_count' => WebsiteSetting::getResolvedComplaintsCount(),
            'unread_messages' => SupportMessage::where('sender_type', 'user')->where('is_read', false)->count(),
        ];

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'currentStatus' => $status,
            'search' => $request->search,
        ]);
    }

    /**
     * Show ticket details and chat thread.
     */
    public function show(SupportTicket $ticket): Response
    {
        // Mark all user messages as read by admin
        $ticket->messages()
            ->where('sender_type', 'user')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $ticket->load([
            'user:id,name,username,email',
            'messages' => fn ($q) => $q->orderBy('created_at', 'asc'),
        ]);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Send an admin reply to a ticket.
     */
    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'message.required' => 'Pesan balasan tidak boleh kosong.',
            'message.max' => 'Pesan balasan maksimal 2.000 karakter.',
        ]);

        $ticket->messages()->create([
            'sender_type' => 'admin',
            'message' => $validated['message'],
            'is_read' => false,
        ]);

        return back()->with('success', 'Balasan aduan berhasil dikirim ke pengguna.');
    }

    /**
     * Mark ticket as resolved, wipe all chat messages cleanly, and count resolved complaint.
     */
    public function resolveAndClean(SupportTicket $ticket): RedirectResponse
    {
        $ticketNumber = $ticket->ticket_number;

        $ticket->resolveAndCleanChat();

        return redirect()->route('admin.support.index')->with(
            'success',
            "Aduan #{$ticketNumber} berhasil ditandai selesai! Seluruh riwayat chat telah terhapus bersih dan total aduan selesai berhasil dihitung."
        );
    }
}
