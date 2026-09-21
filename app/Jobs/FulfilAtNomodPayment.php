<?php

namespace App\Jobs;

use App\Services\AtNomodFulfilmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FulfilAtNomodPayment implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A supplier request is not safe to blindly retry after a worker timeout. */
    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public readonly int $paymentAttemptId) {}

    public function uniqueId(): string
    {
        return 'nomod-at-'.$this->paymentAttemptId;
    }

    public function handle(AtNomodFulfilmentService $fulfilmentService): void
    {
        $fulfilmentService->fulfil($this->paymentAttemptId);
    }
}
