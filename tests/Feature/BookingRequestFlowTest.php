<?php

namespace Tests\Feature;

use App\Models\AgentCharge;
use App\Models\BookingRequest;
use App\Models\FlightBookings;
use App\Models\FlightPassenger;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\TestCase;

class BookingRequestFlowTest extends TestCase
{
    private User $customer;

    private User $otherCustomer;

    private User $admin;

    private FlightBookings $booking;

    protected function setUp(): void
    {
        parent::setUp();
        // Never touch a real database.
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', config('database.default'));
        Mail::fake();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('booking_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->decimal('selling_amount', 20, 8);
            $table->char('selling_currency', 3);
            $table->timestamps();
        });
        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('price_snapshot_id')->nullable();
            $table->string('pnr')->nullable();
            $table->string('itinerary_ref')->nullable();
            $table->string('flight_provider')->nullable();
            $table->string('booking_source')->nullable();
            $table->string('booking_mode')->nullable();
            $table->string('status')->default('booked');
            $table->decimal('amount', 10, 2)->default(0);
            $table->char('selling_currency', 3)->nullable();
            $table->decimal('selling_amount', 20, 8)->nullable();
            $table->decimal('aed_amount', 20, 8)->nullable();
            $table->string('tid')->nullable();
            $table->string('t_status')->nullable();
            $table->timestamps();
        });
        Schema::create('agent_charges', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('charged_by')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('payment_type');
            $table->text('additional_details')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });
        Schema::create('flight_passengers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('type')->default('ADT');
            $table->string('title')->default('Mr');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('cnic')->nullable();
            $table->timestamps();
        });

        foreach ([
            '2026_05_08_102537_create_permission_tables.php',
            '2026_10_30_000001_add_total_amount_to_flight_passengers_table.php',
            '2026_10_30_000002_add_status_to_flight_passengers_table.php',
            '2026_10_30_000003_create_booking_requests_table.php',
            '2026_10_30_000004_create_booking_request_passengers_table.php',
        ] as $migration) {
            (require database_path("migrations/{$migration}"))->up();
        }

        $this->customer = User::create(['name' => 'Cust', 'email' => 'c@example.com', 'role' => 'customer']);
        $this->otherCustomer = User::create(['name' => 'Other', 'email' => 'o@example.com', 'role' => 'customer']);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@example.com', 'role' => 'admin']);

        $snapshotId = \DB::table('booking_price_snapshots')->insertGetId([
            'selling_amount' => 300, 'selling_currency' => 'aed',
        ]);
        $this->booking = FlightBookings::create([
            'agent_id' => $this->customer->id,
            'price_snapshot_id' => $snapshotId,
            'pnr' => 'ABC123',
            'flight_provider' => 'AT',
            'booking_mode' => 'B2C',
            'status' => 'ticketed',
            'aed_amount' => 300,
        ]);
        foreach (['One', 'Two', 'Three'] as $name) {
            FlightPassenger::create(['booking_id' => $this->booking->id, 'first_name' => $name, 'last_name' => 'Pax']);
        }
    }

    private function paxIds(): array
    {
        return $this->booking->pessangers()->orderBy('id')->pluck('id')->all();
    }

    public function test_new_passengers_inherit_the_booking_status(): void
    {
        $this->assertSame(['ticketed'], $this->booking->pessangers()->distinct()->pluck('status')->all());
    }

    public function test_customer_cannot_see_or_request_another_customers_booking(): void
    {
        $this->actingAs($this->otherCustomer)
            ->getJson('/api/booking-requests/lookup?pnr=ABC123')
            ->assertNotFound();

        $this->actingAs($this->otherCustomer)
            ->postJson('/api/booking-requests', [
                'booking_id' => $this->booking->id,
                'type' => 'void',
                'passenger_ids' => $this->paxIds(),
            ])->assertNotFound();
    }

    public function test_partial_void_then_refund_of_the_rest(): void
    {
        [$first, $second, $third] = $this->paxIds();

        $lookup = $this->actingAs($this->customer)->getJson('/api/booking-requests/lookup?pnr=ABC123')->assertOk();
        $this->assertSame('AED', $lookup->json('currency'));
        $this->assertArrayNotHasKey('agent_id', $lookup->json('booking'));

        $id = $this->actingAs($this->customer)->postJson('/api/booking-requests', [
            'booking_id' => $this->booking->id,
            'type' => 'void',
            'passenger_ids' => [$first],
        ])->assertCreated()->json('data.id');

        $this->assertEquals(100, BookingRequest::find($id)->ticket_amount);

        // Only one open request per booking.
        $this->actingAs($this->customer)->postJson('/api/booking-requests', [
            'booking_id' => $this->booking->id,
            'type' => 'void',
            'passenger_ids' => [$second],
        ])->assertUnprocessable();

        // Customers cannot approve.
        $this->actingAs($this->customer)->postJson("/api/booking-requests/{$id}/approve")->assertForbidden();

        // Penalty above the ticket amount is refused.
        $this->actingAs($this->admin)->postJson("/api/booking-requests/{$id}/approve", ['penalty_amount' => 150])
            ->assertUnprocessable();

        $this->actingAs($this->admin)->postJson("/api/booking-requests/{$id}/approve", ['penalty_amount' => 10])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertEquals(90, BookingRequest::find($id)->refund_amount);
        // Wallet: ticket amount credited back, penalty charged separately.
        $this->assertEquals(100, AgentCharge::where('payment_type', 'void')->sum('amount'));
        $this->assertEquals(10, AgentCharge::where('payment_type', 'charge')->sum('amount'));
        $this->assertTrue(FlightBookings::whereKey($this->booking->id)->chargedToWallet()->exists());
        $this->assertSame('partially_voided', $this->booking->fresh()->status);
        $this->assertSame('voided', FlightPassenger::find($first)->status);
        $this->assertSame('ticketed', FlightPassenger::find($second)->status);

        // Approving twice does nothing.
        $this->actingAs($this->admin)->postJson("/api/booking-requests/{$id}/approve")->assertUnprocessable();

        // A voided passenger cannot be requested again.
        $this->actingAs($this->customer)->postJson('/api/booking-requests', [
            'booking_id' => $this->booking->id,
            'type' => 'refund',
            'passenger_ids' => [$first, $second],
        ])->assertUnprocessable();

        $refundId = $this->actingAs($this->customer)->postJson('/api/booking-requests', [
            'booking_id' => $this->booking->id,
            'type' => 'refund',
            'passenger_ids' => [$second, $third],
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/booking-requests/{$refundId}/approve")->assertOk();

        // Every passenger is now settled, mixing voided and refunded.
        $this->assertSame('refunded', $this->booking->fresh()->status);
        $this->assertEquals(200, AgentCharge::where('payment_type', 'refund')->sum('amount'));
        $this->assertSame('voided', FlightPassenger::find($first)->status);
        $this->assertSame('refunded', FlightPassenger::find($third)->status);

        $this->actingAs($this->customer)->getJson('/api/booking-requests/counts')
            ->assertJsonPath('approved', 2)
            ->assertJsonMissingPath('providers');
    }

    public function test_customer_can_cancel_only_their_own_pending_request(): void
    {
        $id = $this->actingAs($this->customer)->postJson('/api/booking-requests', [
            'pnr' => 'ABC123',
            'type' => 'refund',
            'passenger_ids' => $this->paxIds(),
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->otherCustomer)->postJson("/api/booking-requests/{$id}/cancel")->assertNotFound();
        $this->actingAs($this->otherCustomer)->getJson('/api/booking-requests')->assertJsonPath('data.total', 0);

        $this->actingAs($this->customer)->postJson("/api/booking-requests/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('ticketed', $this->booking->fresh()->status);
    }

    public function test_card_paid_booking_gets_no_wallet_entries(): void
    {
        $this->booking->update(['t_status' => 'approved']);

        $id = $this->actingAs($this->customer)->postJson('/api/booking-requests', [
            'booking_id' => $this->booking->id,
            'type' => 'void',
            'passenger_ids' => $this->paxIds(),
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/booking-requests/{$id}/approve")->assertOk();

        $this->assertSame('voided', $this->booking->fresh()->status);
        $this->assertSame(0, AgentCharge::count());
    }

    public function test_whole_booking_status_change_skips_settled_passengers(): void
    {
        [$first] = $this->paxIds();
        FlightPassenger::whereKey($first)->update(['status' => 'voided']);

        $this->booking->update(['status' => 'canceled']);

        $this->assertSame('voided', FlightPassenger::find($first)->status);
        $this->assertSame(2, $this->booking->pessangers()->where('status', 'canceled')->count());
    }
}
