<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class CustomResetPasswordNotification extends Notification
{
    use Queueable;

    public $token;

    public static $createUrlCallback;

    public static $toMailCallback;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        return $this->buildMailMessage($this->resetUrl($notifiable));
    }

    protected function resetUrl($notifiable)
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return url(env('REACT_APP_URL') . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email));
    }

    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject(Lang::get('Paroly çalşmak üçin habar'))
            ->line(Lang::get('Siz hasabyňyzyň parolyny üýtgetmek üçin iberen haýyşyňyz esasynda siz bu habary aldyňyz.'))
            ->action(Lang::get('Paroly täzele'), $url)
            ->line(Lang::get('Paroly üýtgetmek üçin berlen wagt :count minutdan.', ['count' => config('auth.passwords.' . config('auth.defaults.passwords') . '.tamamlanýar')]))
            ->line(Lang::get('Eger siz hasabyňyzyň parolyny üýtgetmek üçin haýyşnama ugratmadyk bolsaňyz, bu habara üns bermäň.'));
    }

    public static function createUrlUsing($callback)
    {
        static::$createUrlCallback = $callback;
    }

    public static function toMailUsing($callback)
    {
        static::$toMailCallback = $callback;
    }
}
