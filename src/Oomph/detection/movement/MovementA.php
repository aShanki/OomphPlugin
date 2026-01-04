<?php

declare(strict_types=1);

namespace Oomph\detection\movement;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\math\Vector3;

/**
 * MovementA Detection
 *
 * Detects speed and fly hacks by flagging when server-authoritative movement
 * corrections are triggered. When the client's reported position deviates
 * significantly from the server's simulated position, a correction is sent
 * and this detection flags the player.
 *
 * This catches:
 * - Speed hacks (horizontal movement speed)
 * - Flight hacks (vertical movement anomalies)
 * - Teleport/blink hacks
 * - Timer hacks (movement faster than allowed)
 */
class MovementA extends Detection {

    /** Number of corrections before flagging */
    private int $correctionCount = 0;

    /** Minimum corrections in window to flag */
    private const CORRECTION_THRESHOLD = 3;

    /** Ticks to track corrections over */
    private const CORRECTION_WINDOW = 40; // 2 seconds

    /** Server tick when counting started */
    private int $windowStartTick = 0;

    public function __construct() {
        // Higher thresholds to reduce false positives from lag
        // FailBuffer: 2 (need 2+ corrections in window)
        // MaxBuffer: 5
        // TrustDuration: 200 ticks (10 seconds) for decay
        parent::__construct(
            maxBuffer: 5.0,
            failBuffer: 2.0,
            trustDuration: 200
        );
    }

    public function getName(): string {
        return "MovementA";
    }

    public function getType(): string {
        return self::TYPE_MOVEMENT;
    }

    public function getMaxViolations(): float {
        return 15.0;
    }

    /**
     * Called when a movement correction is sent to the client
     *
     * @param OomphPlayer $player The player being corrected
     * @param Vector3 $clientPos Client-reported position
     * @param Vector3 $serverPos Server-predicted position
     * @param float $distance Distance between client and server positions
     */
    public function onCorrection(OomphPlayer $player, Vector3 $clientPos, Vector3 $serverPos, float $distance): void {
        $serverTick = $player->getServerTick();

        // Reset window if expired
        if ($serverTick - $this->windowStartTick > self::CORRECTION_WINDOW) {
            $this->correctionCount = 0;
            $this->windowStartTick = $serverTick;
        }

        $this->correctionCount++;

        // Check if correction count exceeds threshold
        if ($this->correctionCount >= self::CORRECTION_THRESHOLD) {
            // Calculate deviation for debug
            $dx = $clientPos->x - $serverPos->x;
            $dy = $clientPos->y - $serverPos->y;
            $dz = $clientPos->z - $serverPos->z;

            $this->fail($player, 1.0, [
                'distance' => $distance,
                'dx' => $dx,
                'dy' => $dy,
                'dz' => $dz,
                'corrections' => $this->correctionCount,
                'ping' => $player->getPing()
            ]);

            // Reset counter after flagging
            $this->correctionCount = 0;
            $this->windowStartTick = $serverTick;
        }
    }

    /**
     * Called each tick to decay correction count
     */
    public function tick(OomphPlayer $player): void {
        // Slowly decay buffer when not flagging
        if ($this->correctionCount === 0) {
            $this->pass(0.01);
        }
    }
}
