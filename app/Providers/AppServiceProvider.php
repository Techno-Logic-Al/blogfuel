<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
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

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return (new MailMessage)
                ->subject('Reset your BlogFuel password')
                ->greeting('Password reset requested')
                ->line('Use the button below to choose a new password for your BlogFuel account.')
                ->action('Reset password', $url)
                ->line('If you did not request a password reset, you can ignore this email.');
        });
    }
}
