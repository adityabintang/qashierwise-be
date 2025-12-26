<?php

namespace App\Services;

use App\Models\CredentialAccessLog;
use App\Models\PaymentProviderCredential;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CredentialAuditService
{
    /**
     * Current request instance for capturing context.
     */
    protected ?Request $request = null;

    /**
     * Set the current request for context capture.
     */
    public function setRequest(?Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Log a credential access action (create, read, update, delete).
     */
    public function logAccess(
        User $user,
        PaymentProviderCredential $credential,
        string $action,
        bool $success = true,
        ?string $errorMessage = null
    ): CredentialAccessLog {
        if (!CredentialAccessLog::isValidAction($action)) {
            throw new \InvalidArgumentException("Invalid action: {$action}");
        }

        return $this->createLog([
            'user_id' => $user->id,
            'credential_id' => $credential->id,
            'action' => $action,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Log a credential decryption event.
     */
    public function logDecryption(
        User $user,
        PaymentProviderCredential $credential,
        bool $success = true,
        ?string $errorMessage = null
    ): CredentialAccessLog {
        return $this->createLog([
            'user_id' => $user->id,
            'credential_id' => $credential->id,
            'action' => CredentialAccessLog::ACTION_DECRYPT,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Log a credential validation attempt.
     */
    public function logValidation(
        User $user,
        PaymentProviderCredential $credential,
        bool $success,
        ?string $errorMessage = null
    ): CredentialAccessLog {
        // Use 'read' action for validation attempts as it involves reading credentials
        return $this->createLog([
            'user_id' => $user->id,
            'credential_id' => $credential->id,
            'action' => CredentialAccessLog::ACTION_READ,
            'success' => $success,
            'error_message' => $errorMessage ? "Validation: {$errorMessage}" : 'Validation successful',
        ]);
    }

    /**
     * Log a Row Level Security violation.
     */
    public function logRLSViolation(
        User $user,
        ?int $attemptedCredentialId = null,
        ?string $details = null
    ): CredentialAccessLog {
        return $this->createLog([
            'user_id' => $user->id,
            'credential_id' => $attemptedCredentialId,
            'action' => CredentialAccessLog::ACTION_READ,
            'success' => false,
            'error_message' => "RLS Violation: {$details}",
        ]);
    }

    /**
     * Get audit logs for a specific user.
     */
    public function getLogsForUser(
        User $user,
        ?string $action = null,
        ?int $limit = 50
    ): Collection {
        $query = CredentialAccessLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($action !== null) {
            $query->where('action', $action);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get audit logs for a specific credential.
     */
    public function getLogsForCredential(
        PaymentProviderCredential $credential,
        ?int $limit = 50
    ): Collection {
        $query = CredentialAccessLog::where('credential_id', $credential->id)
            ->orderBy('created_at', 'desc');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get failed access attempts for security monitoring.
     */
    public function getFailedAttempts(
        ?User $user = null,
        ?int $limit = 100
    ): Collection {
        $query = CredentialAccessLog::where('success', false)
            ->orderBy('created_at', 'desc');

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get RLS violation attempts for security monitoring.
     */
    public function getRLSViolations(?int $limit = 100): Collection
    {
        $query = CredentialAccessLog::where('success', false)
            ->where('error_message', 'LIKE', 'RLS Violation:%')
            ->orderBy('created_at', 'desc');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get decryption events for a user.
     */
    public function getDecryptionLogs(
        User $user,
        ?int $limit = 50
    ): Collection {
        $query = CredentialAccessLog::where('user_id', $user->id)
            ->where('action', CredentialAccessLog::ACTION_DECRYPT)
            ->orderBy('created_at', 'desc');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get audit summary for a user.
     */
    public function getUserAuditSummary(User $user): array
    {
        $logs = CredentialAccessLog::where('user_id', $user->id)->get();

        return [
            'total_logs' => $logs->count(),
            'create_actions' => $logs->where('action', CredentialAccessLog::ACTION_CREATE)->count(),
            'read_actions' => $logs->where('action', CredentialAccessLog::ACTION_READ)->count(),
            'update_actions' => $logs->where('action', CredentialAccessLog::ACTION_UPDATE)->count(),
            'delete_actions' => $logs->where('action', CredentialAccessLog::ACTION_DELETE)->count(),
            'decrypt_actions' => $logs->where('action', CredentialAccessLog::ACTION_DECRYPT)->count(),
            'successful_actions' => $logs->where('success', true)->count(),
            'failed_actions' => $logs->where('success', false)->count(),
            'rls_violations' => $logs->where('success', false)
                ->filter(fn($log) => str_contains($log->error_message ?? '', 'RLS Violation'))
                ->count(),
        ];
    }

    /**
     * Get audit summary for a credential.
     */
    public function getCredentialAuditSummary(PaymentProviderCredential $credential): array
    {
        $logs = CredentialAccessLog::where('credential_id', $credential->id)->get();

        return [
            'total_accesses' => $logs->count(),
            'decryptions' => $logs->where('action', CredentialAccessLog::ACTION_DECRYPT)->count(),
            'updates' => $logs->where('action', CredentialAccessLog::ACTION_UPDATE)->count(),
            'failed_accesses' => $logs->where('success', false)->count(),
            'last_accessed_at' => $logs->first()?->created_at,
            'last_decrypted_at' => $logs->where('action', CredentialAccessLog::ACTION_DECRYPT)
                ->first()?->created_at,
        ];
    }

    /**
     * Create an audit log entry.
     */
    protected function createLog(array $data): CredentialAccessLog
    {
        // Add request context if available
        if ($this->request !== null) {
            $data['ip_address'] = $this->request->ip();
            $data['user_agent'] = $this->request->userAgent();
        }

        try {
            $log = CredentialAccessLog::create($data);

            Log::debug('Credential access log created', [
                'log_id' => $log->id,
                'action' => $log->action,
                'user_id' => $log->user_id,
                'credential_id' => $log->credential_id,
                'success' => $log->success,
            ]);

            return $log;
        } catch (\Exception $e) {
            Log::error('Failed to create credential access log', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }
}
