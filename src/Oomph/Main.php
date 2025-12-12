<?php

declare(strict_types=1);

namespace Oomph;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use Oomph\player\PlayerManager;
use Oomph\listener\PacketListener;
use Oomph\listener\PlayerListener;
use Oomph\listener\EntityListener;

class Main extends PluginBase implements Listener {

    private static Main $instance;
    private PlayerManager $playerManager;

    public function onEnable(): void {
        self::$instance = $this;

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
}
