<?php

declare(strict_types=1);

namespace Oomph\detection\movement;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\math\Vector2;

/**
 * InvMoveA Detection
 *
 * Detects movement while manipulating inventory. Legitimate clients freeze
 * player movement when opening inventory or crafting interfaces.
 */
class InvMoveA extends Detection {

    public function __construct() {
        // MaxViolations: 1
        // FailBuffer: 1, MaxBuffer: 1
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "InvMoveA";
    }

    public function getType(): string {
        return self::TYPE_MOVEMENT;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check if player is moving while processing inventory request
     *
     * @param OomphPlayer $player The player being checked
     * @param Vector2 $impulse Movement input (X=forward, Y=strafe)
     */
    public function check(OomphPlayer $player, Vector2 $impulse): void {
        // Calculate total impulse magnitude
        $impulseMagnitude = sqrt($impulse->x * $impulse->x + $impulse->y * $impulse->y);

        // Check if player has movement input while in inventory
        if ($impulseMagnitude > 0) {
            // Player is moving while manipulating inventory
            $this->fail($player);
        } else {
            // Player is properly standing still in inventory
            $this->pass();
        }
    }
}
