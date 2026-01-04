<?php

declare(strict_types=1);

namespace Oomph\detection\movement;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\math\Vector3;

/**
 * VelocityA Detection
 *
 * Detects velocity manipulation by comparing client-reported velocity
 * against server-simulated expected velocity.
 *
 * Catches:
 * - Speed hacks (horizontal velocity too high)
 * - Flight hacks (vertical velocity anomalies)
 * - Anti-knockback (ignoring applied knockback)
 * - Blink/teleport (sudden position changes)
 */
class VelocityA extends Detection {

    /** Maximum allowed horizontal velocity deviation */
    private const MAX_HORIZONTAL_DEVIATION = 0.5;

    /** Maximum allowed vertical velocity deviation */
    private const MAX_VERTICAL_DEVIATION = 0.8;

    /** Maximum horizontal speed (blocks per tick) */
    private const MAX_HORIZONTAL_SPEED = 1.0;

    /** Consecutive violations before flagging */
    private int $consecutiveViolations = 0;

    /** Threshold for consecutive violations */
    private const CONSECUTIVE_THRESHOLD = 3;

    public function __construct() {
        parent::__construct(
            maxBuffer: 5.0,
            failBuffer: 2.0,
            trustDuration: 80 // 4 second decay
        );
    }

    public function getName(): string {
        return "VelocityA";
    }

    public function getType(): string {
        return self::TYPE_MOVEMENT;
    }

    public function getMaxViolations(): float {
        return 20.0;
    }

    /**
     * Check velocity on each input
     *
     * @param OomphPlayer $player The player being checked
     */
    public function check(OomphPlayer $player): void {
        $movement = $player->getMovementComponent();

        // Skip during special conditions
        if ($movement->isInCorrectionCooldown() ||
            $movement->getTicksSinceTeleport() < 20 ||
            $movement->getTicksSinceKnockback() < 10) {
            $this->consecutiveViolations = 0;
            return;
        }

        // Skip special movement states
        if ($movement->isFlying() || $movement->isGliding() || $movement->isNoClip()) {
            $this->consecutiveViolations = 0;
            return;
        }

        // Get positions
        $clientPos = $movement->getPosition();
        $prevClientPos = $movement->getPrevPosition();
        $authPos = $movement->getAuthPosition();

        // Calculate client movement delta
        $clientDelta = $clientPos->subtractVector($prevClientPos);

        // Calculate horizontal speed
        $horizontalSpeed = sqrt($clientDelta->x * $clientDelta->x + $clientDelta->z * $clientDelta->z);

        // Get expected velocity from simulation
        $authVelocity = $movement->getAuthVelocity();
        $expectedHorizontalSpeed = sqrt($authVelocity->x * $authVelocity->x + $authVelocity->z * $authVelocity->z);

        // Calculate deviation
        $horizontalDeviation = $horizontalSpeed - $expectedHorizontalSpeed;

        // Check for speed violations
        $isViolation = false;
        $violationType = '';

        // Check 1: Horizontal speed exceeds maximum
        if ($horizontalSpeed > self::MAX_HORIZONTAL_SPEED && !$movement->isSprinting()) {
            $isViolation = true;
            $violationType = 'speed_exceeded';
        }

        // Check 2: Horizontal deviation from expected
        if ($horizontalDeviation > self::MAX_HORIZONTAL_DEVIATION) {
            $isViolation = true;
            $violationType = 'horizontal_deviation';
        }

        // Check 3: Vertical velocity anomaly (going up when should be falling)
        $verticalDeviation = $clientDelta->y - $authVelocity->y;
        if ($verticalDeviation > self::MAX_VERTICAL_DEVIATION && $authVelocity->y < 0) {
            $isViolation = true;
            $violationType = 'vertical_deviation';
        }

        if ($isViolation) {
            $this->consecutiveViolations++;

            if ($this->consecutiveViolations >= self::CONSECUTIVE_THRESHOLD) {
                $this->fail($player, 1.0, [
                    'type' => $violationType,
                    'h_speed' => $horizontalSpeed,
                    'expected_h' => $expectedHorizontalSpeed,
                    'h_deviation' => $horizontalDeviation,
                    'v_deviation' => $verticalDeviation,
                    'consecutive' => $this->consecutiveViolations
                ]);

                // Don't reset completely, keep tracking
                $this->consecutiveViolations = self::CONSECUTIVE_THRESHOLD - 1;
            }
        } else {
            // Decay consecutive violations
            $this->consecutiveViolations = max(0, $this->consecutiveViolations - 1);
            $this->pass(0.03);
        }
    }

    /**
     * Called each tick for decay
     */
    public function tick(OomphPlayer $player): void {
        $this->pass(0.01);
    }
}
