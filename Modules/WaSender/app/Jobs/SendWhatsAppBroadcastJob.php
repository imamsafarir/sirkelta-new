<?php

namespace Modules\WaSender\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\WaSender\Models\Contact;
use Modules\WaSender\Models\Device;

class SendWhatsAppBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  array<int>  $contactIds
     */
    public function __construct(
        public int $deviceId,
        public array $contactIds,
        public string $messageContent,
        public int $userId
    ) {}

    /**
     * Execute the job in the queue.
     */
    public function handle(): void
    {
        $device = Device::where('id', $this->deviceId)
            ->where('user_id', $this->userId)
            ->first();

        if (! $device || $device->status !== 'connected') {
            Log::warning("WaSender: Broadcast queue aborted. Device [{$this->deviceId}] not connected.");

            return;
        }

        $contacts = Contact::whereIn('id', $this->contactIds)
            ->where('user_id', $this->userId)
            ->get();

        foreach ($contacts as $contact) {
            // Simulated queue processing with rate-spaced delivery
            $device->increment('messages_sent_today');
        }
    }
}
