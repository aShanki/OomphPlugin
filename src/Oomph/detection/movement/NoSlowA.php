<?php

declare(strict_types=1);

namespace Oomph\detection\movement;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * NoSlowA Detection
 *
 * Detects NoSlow hacks where the player moves at full speed while
 * performing actions that should slow them down:
 * - Using items (eating, drinking, blocking with shield)
 * - Sneaking
 * - Using a bow
 *
 * Based on anticheat-reference MAX_CONSUMING_IMPULSE (0.1225) and
 * MAX_SNEAK_IMPULSE (0.3)
 */
class NoSlowA extends Detection {

    /** Maximum impulse while consuming an item (eating/drinking) */
    private const MAX_CONSUMING_IMPULSE = 0.35; // 0.1225 in reference, but we add tolerance

    /** Maximum impulse while sneaking */
    private const MAX_SNEAK_IMPULSE = 0.35; // 0.3 in reference + tolerance

    /** Maximum impulse while using bow */
    private const MAX_BOW_IMPULSE = 0.35;

    /** Consecutive violations before flagging */
    private int $consecutiveViolations = 0;

    /** Threshold for flagging */
    private const VIOLATION_THRESHOLD = 3;

    public function __construct() {
        parent::__construct(
            maxBuffer: 4.0,
            failBuffer: 2.0,
            trustDuration: 60
        );
    }

    public function getName(): string {
        return "NoSlowA";
    }

    public function getType(): string {
        return self::TYPE_MOVEMENT;
    }

    public function getMaxViolations(): float {
        return 15.0;
    }

    /**
     * Check for NoSlow on each input
     *
     * @param OomphPlayer $player The player being checked
     * @param bool $isUsingItem Whether player is using an item
     * @param bool $isBlocking Whether player is blocking with shield
     * @param bool $isDrawingBow Whether player is drawing a bow
     */
    public function check(
        OomphPlayer $player,
        bool $isUsingItem = false,
        bool $isBlocking = false,
        bool $isDrawingBow = false
    ): void {
        $movement = $player->getMovementComponent();

        // Skip during special conditions
        if ($movement->isInCorrectionCooldown() || $movement->getTicksSinceTeleport() < 20) {
            $this->consecutiveViolations = 0;
            return;
        }

        // Skip if flying or other exempt states
        if ($movement->isFlying() || $movement->isGliding()) {
            $this->consecutiveViolations = 0;
            return;
        }

        // Calculate impulse magnitude
        $impulseX = $movement->getImpulseForward();
        $impulseZ = $movement->getImpulseStrafe();
        $impulseMagnitude = sqrt($impulseX * $impulseX + $impulseZ * $impulseZ);

        $isViolation = false;
        $violationType = '';
        $maxAllowed = 1.0;

        // Check 1: Using item (eating/drinking)
        if ($isUsingItem) {
            $maxAllowed = self::MAX_CONSUMING_IMPULSE;
            if ($impulseMagnitude > $maxAllowed) {
                $isViolation = true;
                $violationType = 'consuming';
            }
        }

        // Check 2: Blocking with shield
        if ($isBlocking) {
            $maxAllowed = self::MAX_CONSUMING_IMPULSE;
            if ($impulseMagnitude > $maxAllowed) {
                $isViolation = true;
                $violationType = 'blocking';
            }
        }

        // Check 3: Drawing bow
        if ($isDrawingBow) {
            $maxAllowed = self::MAX_BOW_IMPULSE;
            if ($impulseMagnitude > $maxAllowed) {
                $isViolation = true;
                $violationType = 'bow';
            }
        }

        // Check 4: Sneaking (only if not already flagged for something else)
        if (!$isViolation && $movement->isSneaking()) {
            $maxAllowed = self::MAX_SNEAK_IMPULSE;
            if ($impulseMagnitude > $maxAllowed) {
                $isViolation = true;
                $violationType = 'sneaking';
            }
        }

        if ($isViolation) {
            $this->consecutiveViolations++;

            if ($this->consecutiveViolations >= self::VIOLATION_THRESHOLD) {
                $this->fail($player, 1.0, [
                    'type' => $violationType,
                    'impulse' => $impulseMagnitude,
                    'max_allowed' => $maxAllowed,
                    'consecutive' => $this->consecutiveViolations
                ]);

                $this->consecutiveViolations = self::VIOLATION_THRESHOLD - 1;
            }
        } else {
            $this->consecutiveViolations = max(0, $this->consecutiveViolations - 1);
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
