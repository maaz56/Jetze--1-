<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DepositDataController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\TestCase;

class CustomerDepositReceiptTest extends TestCase
{
    public function test_customer_deposit_requires_an_image_receipt(): void
    {
        $this->assertReceiptRejected(null);
    }

    public function test_customer_deposit_rejects_pdf_receipts(): void
    {
        $this->assertReceiptRejected(UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'));
    }

    private function assertReceiptRejected(?UploadedFile $receipt): void
    {
        $request = Request::create('/api/deposit-data', 'POST', [
            'date' => '2026-10-19', 'amount' => '100', 'payment_type' => 'cash', 'currency' => 'AED',
        ], [], $receipt ? ['receipt_image' => $receipt] : []);
        $request->setUserResolver(fn () => new User(['role' => 'customer']));

        try {
            (new DepositDataController())->store($request);
            $this->fail('The receipt should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('receipt_image', $exception->errors());
        }
    }
}
