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

    /** Tick when preFlag was set (for extended window) */
    private int $preFlagTick = 0;

    /** How many ticks to keep preFlag active */
    private const PRE_FLAG_WINDOW = 5;

    /** Minimum impulse magnitude to consider as movement */
    private const MIN_IMPULSE_THRESHOLD = 0.01;

    public function __construct() {
        // Increased thresholds for better detection
        // FailBuffer: 1 (flag immediately when detected)
        // MaxBuffer: 3 (allow accumulation)
        // TrustDuration: 100 ticks (5 seconds decay)
        parent::__construct(
            maxBuffer: 3.0,
            failBuffer: 1.0,
            trustDuration: 100
        );
    }

    public function getName(): string {
        return "InvMoveA";
    }

    public function getType(): string {
        return self::TYPE_MOVEMENT;
    }

    public function getMaxViolations(): float {
        return 10.0; // Increased from 1.0 to allow multiple flags before punishment
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

        // Record if player had significant movement input during inventory action
        if ($impulseLenSqr > self::MIN_IMPULSE_THRESHOLD) {
            $this->preFlag = true;
            $this->preFlagTick = $player->getServerTick();

            // Immediately flag if moving while doing inventory action
            // This catches the case where they're actively walking while moving items
            $this->fail($player, 1.0, [
                'phase' => 'request',
                'impulse_x' => $impulse->x,
                'impulse_y' => $impulse->y,
                'impulse_len' => sqrt($impulseLenSqr)
            ]);
        }
    }

    /**
     * Called when PlayerAuthInput packet is received after ItemStackRequest
     * Checks if player is still moving and flags if preFlag was set
     *
     * @param OomphPlayer $player The player being checked
     * @param Vector2 $impulse Movement input (X=forward, Y=strafe)
     */
    public function onPlayerAuthInput(OomphPlayer $player, Vector2 $impulse): void {
        $serverTick = $player->getServerTick();

        // Check if preFlag is still within the detection window
        if (!$this->preFlag || ($serverTick - $this->preFlagTick) > self::PRE_FLAG_WINDOW) {
            $this->preFlag = false;
            // Decay buffer when behaving normally
            $this->pass(0.05);
            return;
        }

        // Calculate total impulse magnitude squared
        $impulseLenSqr = $impulse->x * $impulse->x + $impulse->y * $impulse->y;

        // If preFlag was set and still has significant movement, flag again
        if ($impulseLenSqr > self::MIN_IMPULSE_THRESHOLD) {
            $this->fail($player, 1.0, [
                'phase' => 'input',
                'impulse_x' => $impulse->x,
                'impulse_y' => $impulse->y,
                'impulse_len' => sqrt($impulseLenSqr),
                'ticks_since_request' => $serverTick - $this->preFlagTick
            ]);
        }

        // Don't reset preFlag immediately - keep it active for the window duration
        // This catches sustained movement during inventory manipulation
    }

    /**
     * Legacy check method for backwards compatibility
     * @deprecated Use onItemStackRequest + onPlayerAuthInput instead
     */
    public function check(OomphPlayer $player, Vector2 $impulse): void {
        $this->onItemStackRequest($player, $impulse);
    }
}
