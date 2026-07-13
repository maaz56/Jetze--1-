<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BlogCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ?string $recipientName;
    public array $blogs;
    public string $blogIndexUrl;

    public function __construct(?string $recipientName, array $blogs)
    {
        $this->recipientName = $recipientName;
        $this->blogs = $blogs;
        $this->blogIndexUrl = rtrim(config('app.frontend_url'), '/') . '/blog';
    }

    public function build()
    {
        return $this->subject('Latest Travel Blogs from Jetze.pk')
            ->view('emails.blog_campaign')
            ->with([
                'recipientName' => $this->recipientName,
                'blogs' => $this->blogs,
                'blogIndexUrl' => $this->blogIndexUrl,
            ]);
    }
}
