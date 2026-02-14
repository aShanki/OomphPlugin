<?php

declare(strict_types=1);

namespace Oomph;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use Oomph\player\PlayerManager;
use Oomph\listener\PacketListener;
use Oomph\listener\PlayerListener;
use Oomph\listener\EntityListener;
use Oomph\utils\WebhookNotifier;
use Oomph\task\WebhookQueueTask;

class Main extends PluginBase implements Listener {

    private static Main $instance;
    private PlayerManager $playerManager;

    public function onEnable(): void {
        self::$instance = $this;

        // Save default config if it doesn't exist
        $this->saveDefaultConfig();

        // Initialize webhook notifier from config
        $this->initWebhook();

        // Schedule webhook queue processor (every 2 seconds to avoid rate limits)
        $this->getScheduler()->scheduleRepeatingTask(
            new WebhookQueueTask(),
            WebhookQueueTask::PROCESS_INTERVAL
        );

        $this->playerManager = new PlayerManager();

        // Register packet listener
        $this->getServer()->getPluginManager()->registerEvents(
            new PacketListener($this, $this->playerManager),
            $this
        );

        // Register player listener
        $this->getServer()->getPluginManager()->registerEvents(
            new PlayerListener($this, $this->playerManager),
            $this
        );

        // Register entity listener
        $this->getServer()->getPluginManager()->registerEvents(
            new EntityListener($this, $this->playerManager),
            $this
        );

        $this->getLogger()->info("Oomph Anticheat enabled!");
    }

    public function onDisable(): void {
        $this->getLogger()->info("Oomph Anticheat disabled!");
    }

    public static function getInstance(): Main {
        return self::$instance;
    }

    public function getPlayerManager(): PlayerManager {
        return $this->playerManager;
    }

    /**
     * Initialize webhook notifier from environment variable
     */
    private function initWebhook(): void {
        $config = $this->getConfig();

        // Read webhook URL from environment variable (keeps it out of git)
        $webhookUrlEnv = getenv("OOMPH_WEBHOOK_URL");
        $webhookUrl = is_string($webhookUrlEnv) && $webhookUrlEnv !== "" ? $webhookUrlEnv : "";

        $notifyAllFlagsRaw = $config->getNested("webhook.notify_all_flags", false);
        $notifyAllFlags = is_bool($notifyAllFlagsRaw) ? $notifyAllFlagsRaw : false;

        $minViolationLevelRaw = $config->getNested("webhook.min_violation_level", 0);
        $minViolationLevel = is_int($minViolationLevelRaw) ? $minViolationLevelRaw : 0;

        if ($webhookUrl !== "") {
            WebhookNotifier::init($webhookUrl, $notifyAllFlags, $minViolationLevel);
            $this->getLogger()->info("Webhook notifications enabled");
        }
    }
}
