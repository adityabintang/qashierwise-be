<?php

namespace Tests\Unit\Services;

use App\Models\CredentialAccessLog;
use App\Models\PaymentProviderCredential;
use App\Models\User;
use App\Models\UserEncryptionKey;
use App\Services\CredentialAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CredentialAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CredentialAuditService $auditService;
    protected User $user;
    protected PaymentProviderCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->auditService = new CredentialAuditService();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create user encryption key
        UserEncryptionKey::create([
            'user_id' => $this->user->id,
            'encryption_key_encrypted' => encrypt('test-key-' . $this->user->id),
            'key_version' => 1,
        ]);
        
        // Create test credential
        $this->credential = PaymentProviderCredential::create([
            'user_id' => $this->user->id,
            'provider' => PaymentProviderCredential::PROVIDER_DOKU,
            'credentials_encrypted' => encrypt(json_encode([
                'client_id' => 'test-client-id',
                'secret_key' => 'test-secret-key',
            ])),
            'is_active' => true,
            'connection_status' => 'valid',
        ]);
    }

    public function test_logs_credential_access_create(): void
    {
        $log = $this->auditService->logAccess(
            $this->user,
            $this->credential,
            CredentialAccessLog::ACTION_CREATE
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals($this->credential->id, $log->credential_id);
        $this->assertEquals(CredentialAccessLog::ACTION_CREATE, $log->action);
        $this->assertTrue($log->success);
        $this->assertNull($log->error_message);
    }

    public function test_logs_credential_access_read(): void
    {
        $log = $this->auditService->logAccess(
            $this->user,
            $this->credential,
            CredentialAccessLog::ACTION_READ
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertEquals(CredentialAccessLog::ACTION_READ, $log->action);
        $this->assertTrue($log->success);
    }

    public function test_logs_credential_access_update(): void
    {
        $log = $this->auditService->logAccess(
            $this->user,
            $this->credential,
            CredentialAccessLog::ACTION_UPDATE
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertEquals(CredentialAccessLog::ACTION_UPDATE, $log->action);
        $this->assertTrue($log->success);
    }

    public function test_logs_credential_access_delete(): void
    {
        $log = $this->auditService->logAccess(
            $this->user,
            $this->credential,
            CredentialAccessLog::ACTION_DELETE
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertEquals(CredentialAccessLog::ACTION_DELETE, $log->action);
        $this->assertTrue($log->success);
    }

    public function test_logs_credential_access_with_failure(): void
    {
        $log = $this->auditService->logAccess(
            $this->user,
            $this->credential,
            CredentialAccessLog::ACTION_UPDATE,
            false,
            'Invalid credentials format'
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertFalse($log->success);
        $this->assertEquals('Invalid credentials format', $log->error_message);
    }

    public function test_throws_exception_for_invalid_action(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action: invalid_action');

        $this->auditService->logAccess(
            $this->user,
            $this->credential,
            'invalid_action'
        );
    }

    public function test_logs_decryption_success(): void
    {
        $log = $this->auditService->logDecryption(
            $this->user,
            $this->credential
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals($this->credential->id, $log->credential_id);
        $this->assertEquals(CredentialAccessLog::ACTION_DECRYPT, $log->action);
        $this->assertTrue($log->success);
        $this->assertNull($log->error_message);
    }

    public function test_logs_decryption_failure(): void
    {
        $log = $this->auditService->logDecryption(
            $this->user,
            $this->credential,
            false,
            'Decryption key mismatch'
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertFalse($log->success);
        $this->assertEquals('Decryption key mismatch', $log->error_message);
    }

    public function test_logs_validation_success(): void
    {
        $log = $this->auditService->logValidation(
            $this->user,
            $this->credential,
            true
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertEquals(CredentialAccessLog::ACTION_READ, $log->action);
        $this->assertTrue($log->success);
        $this->assertEquals('Validation successful', $log->error_message);
    }

    public function test_logs_validation_failure(): void
    {
        $log = $this->auditService->logValidation(
            $this->user,
            $this->credential,
            false,
            'Invalid API credentials'
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertFalse($log->success);
        $this->assertEquals('Validation: Invalid API credentials', $log->error_message);
    }

    public function test_logs_rls_violation(): void
    {
        $log = $this->auditService->logRLSViolation(
            $this->user,
            null,
            'Attempted to access credential belonging to another user'
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertNull($log->credential_id);
        $this->assertEquals(CredentialAccessLog::ACTION_READ, $log->action);
        $this->assertFalse($log->success);
        $this->assertStringContainsString('RLS Violation:', $log->error_message);
        $this->assertStringContainsString('Attempted to access credential belonging to another user', $log->error_message);
    }

    public function test_logs_rls_violation_without_credential_id(): void
    {
        $log = $this->auditService->logRLSViolation(
            $this->user,
            null,
            'Query filtered by RLS policy'
        );

        $this->assertInstanceOf(CredentialAccessLog::class, $log);
        $this->assertNull($log->credential_id);
        $this->assertStringContainsString('RLS Violation:', $log->error_message);
    }

    public function test_captures_request_context(): void
    {
        $request = Request::create('/api/credentials', 'POST', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
        ]);

        $this->auditService->setRequest($request);

        $log = $this->auditService->logAccess(
            $this->user,
            $this->credential,
            CredentialAccessLog::ACTION_CREATE
        );

        $this->assertEquals('192.168.1.100', $log->ip_address);
        $this->assertEquals('Mozilla/5.0 Test Browser', $log->user_agent);
    }

    public function test_gets_logs_for_user(): void
    {
        // Create multiple logs
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_CREATE);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_READ);
        $this->auditService->logDecryption($this->user, $this->credential);

        $logs = $this->auditService->getLogsForUser($this->user);

        $this->assertCount(3, $logs);
        $this->assertEquals($this->user->id, $logs->first()->user_id);
    }

    public function test_gets_logs_for_user_filtered_by_action(): void
    {
        // Create multiple logs with different actions
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_CREATE);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_READ);
        $this->auditService->logDecryption($this->user, $this->credential);
        $this->auditService->logDecryption($this->user, $this->credential);

        $decryptLogs = $this->auditService->getLogsForUser($this->user, CredentialAccessLog::ACTION_DECRYPT);

        $this->assertCount(2, $decryptLogs);
        $this->assertTrue($decryptLogs->every(fn($log) => $log->action === CredentialAccessLog::ACTION_DECRYPT));
    }

    public function test_gets_logs_for_user_with_limit(): void
    {
        // Create 5 logs
        for ($i = 0; $i < 5; $i++) {
            $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_READ);
        }

        $logs = $this->auditService->getLogsForUser($this->user, null, 3);

        $this->assertCount(3, $logs);
    }

    public function test_gets_logs_for_credential(): void
    {
        // Create logs for this credential
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_CREATE);
        $this->auditService->logDecryption($this->user, $this->credential);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE);

        $logs = $this->auditService->getLogsForCredential($this->credential);

        $this->assertCount(3, $logs);
        $this->assertEquals($this->credential->id, $logs->first()->credential_id);
    }

    public function test_gets_failed_attempts(): void
    {
        // Create successful and failed logs
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_READ, true);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE, false, 'Error 1');
        $this->auditService->logDecryption($this->user, $this->credential, false, 'Error 2');

        $failedLogs = $this->auditService->getFailedAttempts();

        $this->assertCount(2, $failedLogs);
        $this->assertTrue($failedLogs->every(fn($log) => $log->success === false));
    }

    public function test_gets_failed_attempts_for_specific_user(): void
    {
        $otherUser = User::factory()->create();
        $otherCredential = PaymentProviderCredential::create([
            'user_id' => $otherUser->id,
            'provider' => PaymentProviderCredential::PROVIDER_XENDIT,
            'credentials_encrypted' => encrypt(json_encode(['api_key' => 'test'])),
            'is_active' => false,
        ]);

        // Create failed logs for both users
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE, false, 'Error 1');
        $this->auditService->logAccess($otherUser, $otherCredential, CredentialAccessLog::ACTION_UPDATE, false, 'Error 2');

        $failedLogs = $this->auditService->getFailedAttempts($this->user);

        $this->assertCount(1, $failedLogs);
        $this->assertEquals($this->user->id, $failedLogs->first()->user_id);
    }

    public function test_gets_rls_violations(): void
    {
        // Create various logs including RLS violations
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_READ, true);
        $this->auditService->logRLSViolation($this->user, null, 'Attempted unauthorized access');
        $this->auditService->logRLSViolation($this->user, null, 'Another violation');
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE, false, 'Regular error');

        $violations = $this->auditService->getRLSViolations();

        $this->assertCount(2, $violations);
        $this->assertTrue($violations->every(fn($log) => str_contains($log->error_message, 'RLS Violation:')));
    }

    public function test_gets_decryption_logs(): void
    {
        // Create various logs
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_CREATE);
        $this->auditService->logDecryption($this->user, $this->credential);
        $this->auditService->logDecryption($this->user, $this->credential);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE);

        $decryptLogs = $this->auditService->getDecryptionLogs($this->user);

        $this->assertCount(2, $decryptLogs);
        $this->assertTrue($decryptLogs->every(fn($log) => $log->action === CredentialAccessLog::ACTION_DECRYPT));
    }

    public function test_gets_user_audit_summary(): void
    {
        // Create various logs
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_CREATE);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_READ);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_DELETE);
        $this->auditService->logDecryption($this->user, $this->credential);
        $this->auditService->logDecryption($this->user, $this->credential);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE, false, 'Error');
        $this->auditService->logRLSViolation($this->user, null, 'Violation');

        $summary = $this->auditService->getUserAuditSummary($this->user);

        $this->assertEquals(8, $summary['total_logs']);
        $this->assertEquals(1, $summary['create_actions']);
        $this->assertEquals(2, $summary['read_actions']); // 1 regular read + 1 RLS violation (uses ACTION_READ)
        $this->assertEquals(2, $summary['update_actions']);
        $this->assertEquals(1, $summary['delete_actions']);
        $this->assertEquals(2, $summary['decrypt_actions']);
        $this->assertEquals(6, $summary['successful_actions']);
        $this->assertEquals(2, $summary['failed_actions']);
        $this->assertEquals(1, $summary['rls_violations']);
    }

    public function test_gets_credential_audit_summary(): void
    {
        // Create various logs for the credential
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_CREATE);
        $this->auditService->logDecryption($this->user, $this->credential);
        $this->auditService->logDecryption($this->user, $this->credential);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_UPDATE);
        $this->auditService->logAccess($this->user, $this->credential, CredentialAccessLog::ACTION_READ, false, 'Error');

        $summary = $this->auditService->getCredentialAuditSummary($this->credential);

        $this->assertEquals(6, $summary['total_accesses']);
        $this->assertEquals(2, $summary['decryptions']);
        $this->assertEquals(2, $summary['updates']);
        $this->assertEquals(1, $summary['failed_accesses']);
        $this->assertNotNull($summary['last_accessed_at']);
        $this->assertNotNull($summary['last_decrypted_at']);
    }
}
