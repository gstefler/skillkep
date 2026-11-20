<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeWithPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $password,
        public bool $isReset = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject($this->isReset ? "Új jelszó a(z) {$app} rendszerhez" : "Üdvözöljük a(z) {$app} rendszerben")
            ->greeting("Tisztelt {$notifiable->name}!")
            ->line($this->isReset
                ? 'Az adminisztrátor új jelszót állított be a fiókjához.'
                : "A szervezete fiókot hozott létre Önnek a(z) {$app} rendszerben.")
            ->line("E-mail-cím: {$notifiable->email}")
            ->line("Ideiglenes jelszó: {$this->password}")
            ->action('Bejelentkezés', route('login'))
            ->line('Az első belépés után kérjük, módosítsa a jelszavát.');
    }
}
