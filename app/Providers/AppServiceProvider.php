<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Verify your BlogFuel email address')
                ->greeting('Finish setting up your BlogFuel account')
                ->line('Confirm your email address to unlock article generation, publishing, and sharing in BlogFuel.')
                ->action('Verify email address', $url)
                ->line('If you did not create a BlogFuel account, you can ignore this email.');
        });
    }
}
