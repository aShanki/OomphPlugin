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
 *
 * Based on anticheat-reference/player/detection/inv_move_a.go
 * Uses a two-phase check:
 * 1. On ItemStackRequest: record if player had movement input (preFlag)
 * 2. On next PlayerAuthInput: if preFlag && still has movement, flag
 */
class InvMoveA extends Detection {

    /** Whether impulse was detected during ItemStackRequest */
    private bool $preFlag = false;

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
     * Called when ItemStackRequest packet is received
     * Records if player had movement input at this time
     *
     * @param OomphPlayer $player The player being checked
     * @param Vector2 $impulse Movement input (X=forward, Y=strafe)
     */
    public function onItemStackRequest(OomphPlayer $player, Vector2 $impulse): void {
        // Calculate total impulse magnitude squared
        $impulseLenSqr = $impulse->x * $impulse->x + $impulse->y * $impulse->y;

        // Record if player had movement input during inventory action
        $this->preFlag = $impulseLenSqr > 0.0;
    }

    /**
     * Called when PlayerAuthInput packet is received after ItemStackRequest
     * Checks if player is still moving and flags if preFlag was set
     *
     * @param OomphPlayer $player The player being checked
     * @param Vector2 $impulse Movement input (X=forward, Y=strafe)
     */
    public function onPlayerAuthInput(OomphPlayer $player, Vector2 $impulse): void {
        // Calculate total impulse magnitude squared
        $impulseLenSqr = $impulse->x * $impulse->x + $impulse->y * $impulse->y;

        // If preFlag was set and still has movement, flag
        if ($this->preFlag && $impulseLenSqr > 0.0) {
            $this->fail($player, 1.0, [
                'impulse_x' => $impulse->x,
                'impulse_y' => $impulse->y
            ]);
        }

        // Reset preFlag after checking
        $this->preFlag = false;
    }

    /**
     * Legacy check method for backwards compatibility
     * @deprecated Use onItemStackRequest + onPlayerAuthInput instead
     */
    public function check(OomphPlayer $player, Vector2 $impulse): void {
        $this->onItemStackRequest($player, $impulse);
    }
}
