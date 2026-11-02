<?php

namespace App\Console\Commands;

use App\Services\TabbyService;
use Illuminate\Console\Command;

class RegisterTabbyWebhook extends Command
{
    protected $signature = 'payments:tabby:register-webhook
        {url? : HTTPS webhook URL (defaults to TABBY_WEBHOOK_URL)}
        {--list : Only list the webhooks registered for the current key}';

    protected $description = 'Register (or update) the Tabby webhook for the configured key and merchant code.';

    public function handle(TabbyService $tabbyService): int
    {
        $webhooks = $tabbyService->listWebhooks();
        $mode = $tabbyService->isTestMode() ? 'test' : 'live';

        if ($this->option('list')) {
            $this->table(['ID', 'URL', 'Test'], array_map(static fn (array $webhook): array => [
                $webhook['id'] ?? '',
                $webhook['url'] ?? '',
                ($webhook['is_test'] ?? false) ? 'yes' : 'no',
            ], $webhooks));

            return self::SUCCESS;
        }

        $url = (string) ($this->argument('url') ?: config('services.tabby.webhook_url'));
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($url), 'https://')) {
            $this->error('Provide an HTTPS webhook URL or set TABBY_WEBHOOK_URL.');

            return self::FAILURE;
        }

        // Re-registering updates the header secret, so reuse the existing
        // entry for this URL instead of creating a duplicate (max 4 per key).
        $existing = collect($webhooks)->firstWhere('url', $url);
        $result = $existing
            ? $tabbyService->updateWebhook((string) $existing['id'], $url)
            : $tabbyService->registerWebhook($url);

        $this->info(sprintf(
            '%s %s Tabby webhook %s: %s',
            $existing ? 'Updated' : 'Registered',
            $mode,
            $result['id'] ?? '',
            $url,
        ));

        return self::SUCCESS;
    }
}
