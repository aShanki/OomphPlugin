<?php

declare(strict_types=1);

namespace Oomph\detection\movement;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * GroundSpoofA Detection
 *
 * Detects ground state spoofing where the client claims to be on ground
 * when the server simulation says they shouldn't be.
 *
 * Ground spoofing is used by:
 * - Flight hacks (claim on ground to reset fall damage)
 * - Speed hacks (ground movement is faster than air)
 * - NoFall hacks (claim on ground to avoid fall damage)
 */
class GroundSpoofA extends Detection {

    /** Ticks of consistent ground mismatch before flagging */
    private int $mismatchTicks = 0;

    /** Threshold of consecutive mismatches to flag */
    private const MISMATCH_THRESHOLD = 5;

    /** Minimum Y velocity to consider player falling */
    private const FALLING_VELOCITY_THRESHOLD = -0.1;

    /** Minimum height off ground to consider airborne */
    private const AIRBORNE_HEIGHT_THRESHOLD = 0.5;

    public function __construct() {
        parent::__construct(
            maxBuffer: 4.0,
            failBuffer: 2.0,
            trustDuration: 60 // 3 second decay
        );
    }

    public function getName(): string {
        return "GroundSpoofA";
    }

    public function getType(): string {
        return self::TYPE_MOVEMENT;
    }

    public function getMaxViolations(): float {
        return 15.0;
    }

    /**
     * Check ground state on each input
     *
     * @param OomphPlayer $player The player being checked
     * @param bool $clientOnGround Client's claimed ground state
     */
    public function check(OomphPlayer $player, bool $clientOnGround): void {
        $movement = $player->getMovementComponent();

        // Skip during correction cooldown or teleport
        if ($movement->isInCorrectionCooldown() || $movement->getTicksSinceTeleport() < 20) {
            $this->mismatchTicks = 0;
            return;
        }

        // Skip if player has special movement states
        if ($movement->isFlying() || $movement->isGliding() || $movement->isSwimming() || $movement->isClimbing()) {
            $this->mismatchTicks = 0;
            return;
        }

        // Get server-simulated velocity
        $authVelocity = $movement->getAuthVelocity();

        // Check for ground spoof: client claims on ground but server says falling
        $serverSaysFalling = $authVelocity->y < self::FALLING_VELOCITY_THRESHOLD;

        // Also check position - if significantly above ground and claiming on ground
        $clientPos = $movement->getPosition();
        $authPos = $movement->getAuthPosition();
        $yDiff = $clientPos->y - $authPos->y;

        // Suspicious if claiming ground while falling OR significantly above auth position
        $suspicious = $clientOnGround && ($serverSaysFalling || $yDiff > self::AIRBORNE_HEIGHT_THRESHOLD);

        if ($suspicious) {
            $this->mismatchTicks++;

            if ($this->mismatchTicks >= self::MISMATCH_THRESHOLD) {
                $this->fail($player, 1.0, [
                    'client_on_ground' => $clientOnGround ? 'true' : 'false',
                    'auth_vel_y' => $authVelocity->y,
                    'y_diff' => $yDiff,
                    'mismatch_ticks' => $this->mismatchTicks
                ]);

                // Reset after flagging
                $this->mismatchTicks = 0;
            }
        } else {
            // Reset mismatch counter when behaving normally
            $this->mismatchTicks = max(0, $this->mismatchTicks - 1);
            $this->pass(0.05);
        }
    }

    /**
     * Called each tick for decay
     */
    public function tick(OomphPlayer $player): void {
        $this->pass(0.01);
    }
}
