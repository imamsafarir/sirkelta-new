<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountActivationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $activationUrl,
        public string $email
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
            ->subject('Aktivasi Akun & Pembuatan Kata Sandi Sirkelta')
            ->greeting('Halo!')
            ->line('Terima kasih telah mendaftar di Sirkelta menggunakan alamat email: '.$this->email)
            ->line('Tinggal 1 langkah lagi! Klik tombol di bawah ini untuk mengonfirmasi email dan membuat Username serta Kata Sandi akun Anda:')
            ->action('Aktifkan Akun & Buat Password', $this->activationUrl)
            ->line('Tautan aktivasi ini hanya berlaku selama 24 jam demi keamanan akun Anda.')
            ->line('Jika Anda tidak pernah merasa mendaftar di Sirkelta, Anda dapat mengabaikan email ini dengan aman.')
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
            'email' => $this->email,
            'activation_url' => $this->activationUrl,
        ];
    }
}
