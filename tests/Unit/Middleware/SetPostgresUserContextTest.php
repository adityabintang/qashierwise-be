<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SetPostgresUserContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test suite for SetPostgresUserContext middleware.
 * 
 * Validates Requirements 3.1, 3.2, 3.3:
 * - Setting PostgreSQL session variable for authenticated users
 * - Proper cleanup after request completion
 * - No context set for unauthenticated requests
 */
class SetPostgresUserContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip tests if not using PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test requires PostgreSQL database');
        }
    }

    /**
     * Test that middleware sets PostgreSQL session variable for authenticated user.
     * 
     * @return void
     */
    public function test_sets_postgres_session_variable_for_authenticated_user(): void
    {
        // Create a test user
        $user = User::factory()->create();

        // Create a request with authenticated user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Create middleware instance
        $middleware = new SetPostgresUserContext();

        // Execute middleware
        $response = $middleware->handle($request, function ($req) use ($user) {
            // Inside the request, verify the session variable is set
            $result = DB::selectOne("SELECT current_setting('app.current_user_id', true) as user_id");
            
            // Assert the session variable is set to the user's ID
            $this->assertEquals((string)$user->id, $result->user_id);
            
            return response('OK');
        });

        // Assert response is successful
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Test that middleware does not set session variable for unauthenticated request.
     * 
     * @return void
     */
    public function test_does_not_set_session_variable_for_unauthenticated_request(): void
    {
        // Create a request without authenticated user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        // Create middleware instance
        $middleware = new SetPostgresUserContext();

        // Execute middleware
        $response = $middleware->handle($request, function ($req) {
            // Inside the request, verify the session variable is not set
            $result = DB::selectOne("SELECT current_setting('app.current_user_id', true) as user_id");
            
            // Assert the session variable is empty or null
            $this->assertTrue(empty($result->user_id) || $result->user_id === '');
            
            return response('OK');
        });

        // Assert response is successful
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Test that terminate method resets the session variable.
     * 
     * @return void
     */
    public function test_terminate_resets_session_variable(): void
    {
        // Create a test user
        $user = User::factory()->create();

        // Create a request with authenticated user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Create middleware instance
        $middleware = new SetPostgresUserContext();

        // Execute middleware
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        // Call terminate method
        $middleware->terminate($request, $response);

        // Verify the session variable is reset
        $result = DB::selectOne("SELECT current_setting('app.current_user_id', true) as user_id");
        $this->assertTrue(empty($result->user_id) || $result->user_id === '');
    }

    /**
     * Test that middleware works correctly with multiple sequential requests.
     * 
     * @return void
     */
    public function test_handles_multiple_sequential_requests(): void
    {
        // Create two test users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $middleware = new SetPostgresUserContext();

        // First request with user1
        $request1 = Request::create('/test', 'GET');
        $request1->setUserResolver(function () use ($user1) {
            return $user1;
        });

        $response1 = $middleware->handle($request1, function ($req) use ($user1) {
            $result = DB::selectOne("SELECT current_setting('app.current_user_id', true) as user_id");
            $this->assertEquals((string)$user1->id, $result->user_id);
            return response('OK');
        });

        $middleware->terminate($request1, $response1);

        // Second request with user2
        $request2 = Request::create('/test', 'GET');
        $request2->setUserResolver(function () use ($user2) {
            return $user2;
        });

        $response2 = $middleware->handle($request2, function ($req) use ($user2) {
            $result = DB::selectOne("SELECT current_setting('app.current_user_id', true) as user_id");
            $this->assertEquals((string)$user2->id, $result->user_id);
            return response('OK');
        });

        $middleware->terminate($request2, $response2);

        // Verify context is cleared after both requests
        $result = DB::selectOne("SELECT current_setting('app.current_user_id', true) as user_id");
        $this->assertTrue(empty($result->user_id) || $result->user_id === '');
    }
}
