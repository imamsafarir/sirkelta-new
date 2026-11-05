<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    /**
     * Get or create active chat session for current user or guest.
     */
    public function getActiveChat(Request $request): JsonResponse
    {
        $user = $request->user();
        $guestToken = $request->query('guest_token');

        $ticket = null;

        if ($user) {
            $ticket = SupportTicket::where('user_id', $user->id)
                ->where('status', 'open')
                ->latest()
                ->first();
        } elseif ($guestToken) {
            $ticket = SupportTicket::where('guest_token', $guestToken)
                ->where('status', 'open')
                ->latest()
                ->first();
        }

        if (! $ticket) {
            return response()->json([
                'ticket' => null,
                'messages' => [],
            ]);
        }

        $messages = $ticket->messages()->orderBy('created_at', 'asc')->get();

        return response()->json([
            'ticket' => $ticket,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a support chat message (Guest or Authenticated).
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'guest_name' => ['nullable', 'string', 'max:150'],
            'guest_email' => ['nullable', 'string', 'email', 'max:255'],
            'guest_token' => ['nullable', 'string', 'max:100'],
        ], [
            'message.required' => 'Pesan aduan tidak boleh kosong.',
            'message.max' => 'Pesan aduan maksimal 2.000 karakter.',
            'guest_email.email' => 'Format alamat email tidak valid.',
        ]);

        $user = $request->user();
        $guestToken = $validated['guest_token'] ?? null;

        $ticket = null;

        if ($user) {
            $ticket = SupportTicket::where('user_id', $user->id)
                ->where('status', 'open')
                ->latest()
                ->first();
        } elseif ($guestToken) {
            $ticket = SupportTicket::where('guest_token', $guestToken)
                ->where('status', 'open')
                ->latest()
                ->first();
        }

        // If no active open ticket exists, create a new one
        if (! $ticket) {
            if (! $user && ! $guestToken) {
                $guestToken = (string) Str::uuid();
            }

            $ticketNumber = 'ADU-'.date('ym').'-'.strtoupper(Str::random(4));

            $ticket = SupportTicket::create([
                'ticket_number' => $ticketNumber,
                'user_id' => $user?->id,
                'guest_name' => $user ? $user->name : ($validated['guest_name'] ?? 'Tamu'),
                'guest_email' => $user ? $user->email : ($validated['guest_email'] ?? null),
                'guest_token' => $guestToken,
                'subject' => 'Aduan & Bantuan Layanan',
                'status' => 'open',
            ]);
        }

        // Create the message
        $message = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'message' => $validated['message'],
            'is_read' => false,
        ]);

        $messages = $ticket->messages()->orderBy('created_at', 'asc')->get();

        return response()->json([
            'ticket' => $ticket,
            'messages' => $messages,
            'guest_token' => $ticket->guest_token,
        ]);
    }
}
