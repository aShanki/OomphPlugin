<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * AimA Detection
 *
 * Purpose: Detect artificially smooth/snapped aim (aimbot)
 * Method: Check for suspiciously rounded yaw delta values
 *
 * Legitimate mouse movement has natural imprecision.
 * Aimbots often produce mathematically perfect rotations.
 *
 * Only checks mouse input mode (skip touch/gamepad - too many false positives)
 */
class AimA extends Detection {

    /** Threshold for detecting suspiciously rounded values */
    private const ROUNDED_THRESHOLD = 3e-5;

    /** Heavy rounding precision (1 decimal place) */
    private const ROUNDING_PRECISION_HEAVY = 1;

    /** Light rounding precision (5 decimal places) */
    private const ROUNDING_PRECISION_LIGHT = 5;

    public function __construct() {
        parent::__construct(
            maxBuffer: 5.0,
            failBuffer: 5.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "AimA";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 20.0;
    }

    /**
     * Process rotation data and check for aimbot
     *
     * @param OomphPlayer $player The player to check
     */
    public function process(OomphPlayer $player): void {
        // Only check mouse input mode
        if (!$player->isMouseInput()) {
            return;
        }

        // Get movement component
        $movement = $player->getMovementComponent();

        // Skip if player is colliding with blocks (Go: lines 58-60)
        // Collisions can cause erratic rotation values
        if ($movement->hasXCollision() || $movement->hasZCollision()) {
            return;
        }

        // Get absolute yaw delta from rotation delta (Go: line 62)
        $yawDelta = abs($movement->getRotationDelta()['yaw']);

        // Skip if no rotation change (Go: line 63-65)
        if ($yawDelta < 1e-3) {
            return;
        }

        // Round to two different precisions (Go: line 67)
        // Heavy = 1 decimal place, Light = 5 decimal places
        $roundedHeavy = $this->roundTo($yawDelta, self::ROUNDING_PRECISION_HEAVY);
        $roundedLight = $this->roundTo($yawDelta, self::ROUNDING_PRECISION_LIGHT);

        // Calculate difference between the two rounded values (Go: line 68)
        $diff = abs($roundedLight - $roundedHeavy);

        // Flag if difference is suspiciously small (Go: lines 79-84)
        if ($diff <= self::ROUNDED_THRESHOLD) {
            $this->fail($player, 1.0, [
                'yaw_delta' => $this->roundTo($yawDelta, 3),
                'rounded_heavy' => $roundedHeavy,
                'rounded_light' => $roundedLight,
                'diff' => $diff
            ]);
            return;
        }

        // Rotation appears natural
        $this->pass(0.1);
    }

    /**
     * Wrap degrees to [-180, 180] range
     */
    private function wrapDegrees(float $degrees): float {
        $degrees = fmod($degrees + 180.0, 360.0);
        if ($degrees < 0) {
            $degrees += 360.0;
        }
        return $degrees - 180.0;
    }

    /**
     * Round float to specified decimal places
     */
    private function roundTo(float $value, int $precision): float {
        $multiplier = pow(10, $precision);
        return round($value * $multiplier) / $multiplier;
    }
}
