<?php

namespace App\Jobs;

use App\Services\TboNomodFulfilmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FulfilTboNomodPayment implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A supplier booking request must never be blindly retried. */
    public int $tries = 1;

    public int $timeout = 150;

    /** Release an orphaned uniqueness lock if a worker dies before handling. */
    public int $uniqueFor = 300;

    public function __construct(public readonly int $paymentAttemptId) {}

    public function uniqueId(): string
    {
        return 'nomod-tbo-'.$this->paymentAttemptId;
    }

    public function handle(TboNomodFulfilmentService $fulfilmentService): void
    {
        $fulfilmentService->fulfil($this->paymentAttemptId);
    }
}
