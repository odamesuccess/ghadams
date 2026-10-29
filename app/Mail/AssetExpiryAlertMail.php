<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;

class AssetExpiryAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $assets,
        public string $alertType,
        public string $remainingPeriod,
        public string $statusLabel,
    ) {
    }

    public function envelope(): Envelope
    {
        $count = $this->assets->count();

        $period = match ($this->alertType) {
            'three_months' => '3-Month',
            'one_month' => '1-Month',
            'expiry_day' => 'Expiry-Day',
            default => 'Asset Expiry',
        };

        return new Envelope(
            subject: sprintf(
                'GhanaDAMS %s Asset Expiry Alert (%d %s)',
                $period,
                $count,
                $count === 1 ? 'Asset' : 'Assets'
            )
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.asset-expiry-alert'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
