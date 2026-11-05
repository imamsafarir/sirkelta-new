<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $otp,
        public string $newEmail
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Verifikasi Penggantian Email Akun Sirkelta')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Kami menerima permintaan untuk mengganti alamat email akun Sirkelta Anda menjadi: '.$this->newEmail)
            ->line('Gunakan kode verifikasi 6-digit berikut untuk mengonfirmasi perubahan:')
            ->line('**'.$this->otp.'**')
            ->line('Kode ini hanya berlaku selama 15 menit. Jangan berikan kode ini kepada siapapun.')
            ->line('Jika Anda tidak pernah merasa meminta penggantian email ini, abaikan pesan ini dan segera periksa keamanan kata sandi akun Anda.')
            ->salutation('Salam hangat, Tim Sirkelta');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'otp' => $this->otp,
            'new_email' => $this->newEmail,
        ];
    }
}
