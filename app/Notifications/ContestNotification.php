<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContestNotification extends Notification
{
    use Queueable;

    protected $contest;

    /**
     * Create a new notification instance.
     */
    public function __construct($contest)
    {
        $this->contest = $contest;
    }

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
            ->subject('Golaýlaýan bäsleşik: ' . $this->contest->name)
            ->line('Bäsleşik ýakynda başlaýar!')
            ->line('Bäsleşigiň ady: ' . $this->contest->name)
            ->line('Başlaýan wagty: ' . $this->contest->start_date . ' UTC')
            ->line('Tamamlanýan wagty: ' . $this->contest->end_date . ' UTC')
            ->action('Bäsleşigi gör', url(env('REACT_APP_URL') . '/contest/' . $this->contest->id))
            ->line('Gatnaşýanyňyz üçin sag boluň!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
