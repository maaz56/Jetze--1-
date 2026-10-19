<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;

class AgentRegistrationRemovalTest extends TestCase
{
    public function test_agent_registration_is_rejected(): void
    {
        $response = (new RegisteredUserController())->store(Request::create('/register', 'POST', ['role' => 'agent']));

        $this->assertSame(422, $response->getStatusCode());
    }
}
