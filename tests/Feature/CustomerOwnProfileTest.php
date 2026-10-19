<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CustomerController;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CustomerOwnProfileTest extends TestCase
{
    public function test_profile_update_only_changes_the_authenticated_customers_record(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('company_name')->nullable();
            $table->string('preferred_currency')->nullable();
            $table->timestamps();
        });
        $own = Customer::create(['user_id' => 1, 'name' => 'Original']);
        $other = Customer::create(['user_id' => 2, 'name' => 'Other']);
        $request = Request::create('/api/customer-profile', 'PUT', ['name' => 'Updated', 'user_id' => 2]);
        $request->setUserResolver(fn () => new User(['id' => 1]));

        (new CustomerController())->updateOwnProfile($request);

        $this->assertSame('Updated', $own->fresh()->name);
        $this->assertSame('Other', $other->fresh()->name);
    }
}
