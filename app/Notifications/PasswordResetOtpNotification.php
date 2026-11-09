<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $otp,
        public string $userName
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
            ->subject('Kode OTP Pemulihan Kata Sandi Sirkelta')
            ->greeting('Halo, '.$this->userName.'!')
            ->line('Kami menerima permintaan pengaturan ulang kata sandi untuk akun Sirkelta Anda.')
            ->line('Gunakan kode verifikasi 6-digit berikut untuk mengatur ulang kata sandi:')
            ->line('**'.$this->otp.'**')
            ->line('Kode OTP ini hanya berlaku selama 15 menit. Jangan berikan kode ini kepada siapa pun demi keamanan akun Anda.')
            ->line('Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini dan akun Anda tetap aman.')
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
            'user_name' => $this->userName,
        ];
    }
}
