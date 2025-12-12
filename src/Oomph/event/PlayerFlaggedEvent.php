<?php

declare(strict_types=1);

namespace Oomph\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\player\Player;

class PlayerFlaggedEvent extends Event implements Cancellable {
    use CancellableTrait;

    public function __construct(
        private Player $player,
        private string $detectionName,
        private string $detectionType,
        private float $violations,
        private array $extraData = []
    ) {}

    public function getPlayer(): Player {
        return $this->player;
    }

    public function getDetectionName(): string {
        return $this->detectionName;
    }

    public function getDetectionType(): string {
        return $this->detectionType;
    }

    public function getViolations(): float {
        return $this->violations;
    }

    public function getExtraData(): array {
        return $this->extraData;
    }
}
