<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SettlementConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Transaction $transaction) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $succeeded = $this->transaction->status === 'completed';
        $amount = $this->transaction->amount->whole();
        $currency = $this->transaction->getRawOriginal('currency');
        $reference = $this->transaction->provider_reference;

        if ($succeeded) {
            return (new MailMessage)
                ->subject('RMB Settlement Confirmed – Tupay')
                ->greeting("Hello {$notifiable->name},")
                ->line("Your RMB payout of **{$amount} {$currency}** has been successfully settled by our payment partner.")
                ->line("Provider Reference: `{$reference}`")
                ->line('The transaction has been recorded in your ledger.')
                ->action('View Ledger', url('/'))
                ->line('Thank you for using Tupay.');
        }

        return (new MailMessage)
            ->subject('RMB Settlement Failed – Tupay')
            ->greeting("Hello {$notifiable->name},")
            ->error()
            ->line("Your RMB payout of **{$amount} {$currency}** could not be completed by our payment partner.")
            ->line("Provider Reference: `{$reference}`")
            ->line('Please contact support if you believe this is an error.')
            ->action('Contact Support', url('/'))
            ->line('Thank you for using Tupay.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $succeeded = $this->transaction->status === 'completed';

        return [
            'type' => 'settlement_confirmed',
            'transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->amount->whole(),
            'currency' => $this->transaction->getRawOriginal('currency'),
            'status' => $this->transaction->status,
            'provider_reference' => $this->transaction->provider_reference,
            'message' => $succeeded
                ? "Your RMB settlement of {$this->transaction->amount->whole()} {$this->transaction->getRawOriginal('currency')} was confirmed."
                : "Your RMB settlement failed. Provider ref: {$this->transaction->provider_reference}.",
        ];
    }
}
