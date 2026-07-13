<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PopularRoutesCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ?string $recipientName;
    public array $routes;
    public string $routesIndexUrl;

    public function __construct(?string $recipientName, array $routes)
    {
        $this->recipientName = $recipientName;
        $this->routes = $routes;
        $this->routesIndexUrl = rtrim(config('app.frontend_url'), '/');
    }

    public function build()
    {
        return $this->subject('Popular Travel Routes from Jetze.pk')
            ->view('emails.popular_routes_campaign')
            ->with([
                'recipientName' => $this->recipientName,
                'routes' => $this->routes,
                'routesIndexUrl' => $this->routesIndexUrl,
            ]);
    }
}
