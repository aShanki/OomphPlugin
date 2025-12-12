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

    /** Precision to round to */
    private const ROUNDING_PRECISION = 3;

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

        // Calculate yaw delta
        $currentYaw = $movement->getRotation()->getY();
        $lastYaw = $movement->getLastRotation()->getY();
        $yawDelta = $currentYaw - $lastYaw;

        // Normalize to [-180, 180]
        $yawDelta = $this->wrapDegrees($yawDelta);

        // Skip if no rotation change
        if (abs($yawDelta) < 0.001) {
            return;
        }

        // Round the yaw delta
        $rounded = $this->roundTo($yawDelta, self::ROUNDING_PRECISION);

        // Calculate difference between rounded and actual
        $diff = abs($rounded - $yawDelta);

        // Flag if difference is suspiciously small
        if ($diff <= self::ROUNDED_THRESHOLD) {
            $this->fail($player, 1.0);
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
