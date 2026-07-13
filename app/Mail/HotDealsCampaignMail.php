<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HotDealsCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ?string $recipientName;
    public array $deals;
    public string $homeUrl;

    public function __construct(?string $recipientName, array $deals)
    {
        $this->recipientName = $recipientName;
        $this->deals = $deals;
        $this->homeUrl = rtrim(config('app.frontend_url'), '/');
    }

    public function build()
    {
        return $this->subject('Hot Travel Deals from Jetze.pk')
            ->view('emails.hot_deals_campaign')
            ->with([
                'recipientName' => $this->recipientName,
                'deals' => $this->deals,
                'homeUrl' => $this->homeUrl,
            ]);
    }
}
