<?php

namespace App\Console\Commands;

use App\Services\SubscriptionMonitoringService;
use Illuminate\Console\Command;

/**
 * Command to check subscription system health.
 *
 * Can be run manually or scheduled to monitor system health.
 */
class SubscriptionHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:health-check
                          {--json : Output in JSON format}
                          {--alert : Send alerts if issues detected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check subscription system health and report issues';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionMonitoringService $monitoring): int
    {
        $this->info('Checking subscription system health...');

        $health = $monitoring->checkHealth();

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));

            return $health['status'] === 'healthy' ? 0 : 1;
        }

        // Display status
        $statusColor = $health['status'] === 'healthy' ? 'green' : 'yellow';
        $this->line('');
        $this->line('<fg='.$statusColor.'>Status: '.strtoupper($health['status']).'</>');
        $this->line('Timestamp: '.$health['timestamp']);
        $this->line('');

        // Display metrics
        $this->info('Metrics:');
        $this->displayMetrics($health['metrics']);

        // Display issues
        if (! empty($health['issues'])) {
            $this->line('');
            $this->error('Issues Detected:');
            $this->displayIssues($health['issues']);

            if ($this->option('alert')) {
                $this->warn('Alerts would be sent for these issues.');
            }

            return 1;
        }

        $this->line('');
        $this->info('✓ No issues detected. System is healthy.');

        return 0;
    }

    /**
     * Display metrics in a table.
     */
    private function displayMetrics(array $metrics): void
    {
        // Subscription creation metrics
        $this->line('  Subscription Creation:');
        $this->line('    Errors: '.$metrics['subscription_creation']['errors']);
        $this->line('    Successes: '.$metrics['subscription_creation']['successes']);
        $this->line('    Error Rate: '.round($metrics['subscription_creation']['error_rate'] * 100, 2).'%');

        // Webhook processing metrics
        $this->line('');
        $this->line('  Webhook Processing:');
        $this->line('    Payment Errors: '.$metrics['webhook_processing']['payment_errors']);
        $this->line('    Recurring Errors: '.$metrics['webhook_processing']['recurring_errors']);
        $this->line('    Pay Account Errors: '.$metrics['webhook_processing']['pay_account_errors']);

        // Payment failures
        $this->line('');
        $this->line('  Payment Failures:');
        $this->line('    Count: '.$metrics['payment_failures']['count']);
        $this->line('    Error Rate: '.round($metrics['payment_failures']['error_rate'] * 100, 2).'%');

        // API timeouts
        $this->line('');
        $this->line('  API Timeouts:');
        $this->line('    Count: '.$metrics['api_timeouts']['count']);

        // Security
        $this->line('');
        $this->line('  Security:');
        $this->line('    Invalid Signatures: '.$metrics['security']['invalid_signatures']);
    }

    /**
     * Display issues in a table.
     */
    private function displayIssues(array $issues): void
    {
        $headers = ['Type', 'Component', 'Severity', 'Details'];
        $rows = [];

        foreach ($issues as $issue) {
            $details = [];
            foreach ($issue as $key => $value) {
                if (! in_array($key, ['type', 'component', 'severity'])) {
                    $details[] = "$key: $value";
                }
            }

            $rows[] = [
                $issue['type'],
                $issue['component'],
                $issue['severity'],
                implode(', ', $details),
            ];
        }

        $this->table($headers, $rows);
    }
}
