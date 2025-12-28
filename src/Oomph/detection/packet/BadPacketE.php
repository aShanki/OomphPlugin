<?php

declare(strict_types=1);

namespace Oomph\detection\packet;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * BadPacketE Detection
 *
 * Purpose: Detect movement value manipulation
 * Trigger: Invalid MoveVector (outside valid range)
 *
 * How it works:
 * - Validate MoveVector.X, MoveVector.Y (forward/strafe) are within [-1.001, 1.001]
 * - Values outside this range indicate packet manipulation
 * - Legitimate clients cannot send movement values outside this range
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketE extends Detection {

    /** Maximum valid movement value (with small epsilon for floating point) */
    private const MAX_MOVE_VALUE = 1.001;

    public function __construct() {
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "BadPacketE";
    }

    public function getType(): string {
        return self::TYPE_PACKET;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check for invalid move vector
     *
     * Go reference iterates both components without early return:
     * for index := range 2 { if v := i.MoveVector[index]; v < -1.001 || v > 1.001 { ... } }
     *
     * @param OomphPlayer $player The player to check
     * @param float $moveVecX Movement X component (strafe)
     * @param float $moveVecZ Movement Z component (forward)
     */
    public function process(OomphPlayer $player, float $moveVecX, float $moveVecZ): void {
        // Check both components - do NOT return early (Go checks both in loop)
        $invalidX = abs($moveVecX) > self::MAX_MOVE_VALUE;
        $invalidZ = abs($moveVecZ) > self::MAX_MOVE_VALUE;

        if ($invalidX || $invalidZ) {
            $this->fail($player, 1.0, [
                'move_x' => $moveVecX,
                'move_z' => $moveVecZ,
                'invalid_x' => $invalidX,
                'invalid_z' => $invalidZ,
                'limit' => self::MAX_MOVE_VALUE
            ]);
        }
    }
}
