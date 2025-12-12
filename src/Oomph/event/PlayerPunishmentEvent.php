<?php

declare(strict_types=1);

namespace Oomph\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\player\Player;

class PlayerPunishmentEvent extends Event implements Cancellable {
    use CancellableTrait;

    private ?string $disconnectMessage = null;

    public function __construct(
        private Player $player,
        private string $detectionName,
        private string $reason
    ) {}

    public function getPlayer(): Player {
        return $this->player;
    }

    public function getDetectionName(): string {
        return $this->detectionName;
    }

    public function getReason(): string {
        return $this->reason;
    }

    public function getDisconnectMessage(): ?string {
        return $this->disconnectMessage;
    }

    public function setDisconnectMessage(string $message): void {
        $this->disconnectMessage = $message;
    }
}
