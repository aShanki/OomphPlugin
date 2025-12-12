<?php

declare(strict_types=1);

namespace Oomph\detection\world;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\math\Vector3;

/**
 * ScaffoldA Detection
 *
 * Detects scaffold/tower building hacks by checking for zero click vectors on
 * block placement. Legitimate players click somewhere on the block face, but
 * scaffold hacks often use zero coordinates (0, 0, 0) as the click position.
 *
 * Trigger: UseItemActionClickBlock in PlayerAuthInput (1.21.20+)
 */
class ScaffoldA extends Detection {

    // Epsilon for float comparison
    private const EPSILON = 0.0001;

    public function __construct() {
        // MaxViolations: 5
        // FailBuffer: 1, MaxBuffer: 1
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "ScaffoldA";
    }

    public function getType(): string {
        return self::TYPE_WORLD;
    }

    public function getMaxViolations(): float {
        return 5.0;
    }

    /**
     * Check if block placement has zero click position
     *
     * @param OomphPlayer $player The player placing the block
     * @param Vector3 $clickPos The click position on the block face (0-1 range for each axis)
     */
    public function check(OomphPlayer $player, Vector3 $clickPos): void {
        // Check if all components of click position are zero (or near zero)
        $isZeroX = abs($clickPos->x) < self::EPSILON;
        $isZeroY = abs($clickPos->y) < self::EPSILON;
        $isZeroZ = abs($clickPos->z) < self::EPSILON;

        if ($isZeroX && $isZeroY && $isZeroZ) {
            // Zero click position detected - likely scaffold hack
            $this->fail($player);
        } else {
            // Valid click position
            $this->pass();
        }
    }

    /**
     * Alternative check method if you have separate components
     *
     * @param OomphPlayer $player The player placing the block
     * @param float $clickX Click X position (0-1 range)
     * @param float $clickY Click Y position (0-1 range)
     * @param float $clickZ Click Z position (0-1 range)
     */
    public function checkComponents(OomphPlayer $player, float $clickX, float $clickY, float $clickZ): void {
        $isZeroX = abs($clickX) < self::EPSILON;
        $isZeroY = abs($clickY) < self::EPSILON;
        $isZeroZ = abs($clickZ) < self::EPSILON;

        if ($isZeroX && $isZeroY && $isZeroZ) {
            // Zero click position detected - likely scaffold hack
            $this->fail($player);
        } else {
            // Valid click position
            $this->pass();
        }
    }
}
