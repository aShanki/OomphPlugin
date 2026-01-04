<?php

declare(strict_types=1);

namespace Oomph\task;

use pocketmine\scheduler\Task;
use Oomph\utils\WebhookNotifier;

/**
 * Periodically processes the webhook queue to send batched notifications
 * This prevents Discord rate limiting by spacing out requests
 */
class WebhookQueueTask extends Task {

    /**
     * Process interval in ticks (40 ticks = 2 seconds)
     * Discord allows ~30 requests/minute, so 2 seconds between batches is safe
     */
    public const PROCESS_INTERVAL = 40;

    public function onRun(): void {
        WebhookNotifier::processQueue();
    }
}
