<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends VerifyEmailBase
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        $this->queue = 'notifications';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        $subject = __('messages.email_verification_subject', [], 'tk');
        $greeting = __('messages.email_greeting', ['name' => $notifiable->name ?? 'Ulanyjy'], 'tk');
        $line1 = __('messages.email_verification_line_1', [], 'tk');
        $actionText = __('messages.email_verification_action', [], 'tk');
        $line2 = __('messages.email_verification_line_2', [], 'tk');

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.verify-email', [
                'subject' => $subject,
                'greeting' => $greeting,
                'line1' => $line1,
                'actionText' => $actionText,
                'line2' => $line2,
                'url' => $verificationUrl,
            ]);
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable): string
    {
        $backendSignedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $frontendBaseUrl = rtrim(Config::get('app.frontend_url', env('REACT_APP_URL')), '/');

        return "{$frontendBaseUrl}/email/verify?url=" . urlencode($backendSignedUrl);
    }
}
