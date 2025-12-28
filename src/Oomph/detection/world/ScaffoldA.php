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
 * Trigger: UseItemTransactionData with TriggerType=PlayerInput (1.21.20+)
 * Go reference: Only flags when TriggerType == protocol.TriggerTypePlayerInput
 */
class ScaffoldA extends Detection {


    // TriggerType constants from protocol
    public const TRIGGER_TYPE_UNKNOWN = 0;
    public const TRIGGER_TYPE_PLAYER_INPUT = 1;
    public const TRIGGER_TYPE_SIMULATION_TICK = 2;

    // Minimum version for TriggerType validation (1.21.20)
    public const MIN_VERSION_FOR_TRIGGER = 712;  // Protocol version for 1.21.20

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
     * Go reference (lines 54-59):
     * - Only applies to 1.21.20+ (VersionInRange check)
     * - Only flags when TriggerType == protocol.TriggerTypePlayerInput
     * - Checks ClickedPosition.LenSqr() == 0
     *
     * @param OomphPlayer $player The player placing the block
     * @param Vector3 $clickPos The click position on the block face (0-1 range for each axis)
     * @param int $triggerType The trigger type from UseItemTransactionData
     * @param int $protocolVersion The client's protocol version
     */
    public function check(OomphPlayer $player, Vector3 $clickPos, int $triggerType = self::TRIGGER_TYPE_PLAYER_INPUT, int $protocolVersion = 999): void {
        // Go: Only check for 1.21.20+ (protocol version 712+)
        if ($protocolVersion < self::MIN_VERSION_FOR_TRIGGER) {
            return;
        }

        // Go: Only flag when TriggerType == TriggerTypePlayerInput
        // On older versions or simulation ticks, don't flag
        if ($triggerType !== self::TRIGGER_TYPE_PLAYER_INPUT) {
            return;
        }

        // Check if click position length squared is zero (Go: ClickedPosition.LenSqr() == 0)
        $lenSqr = $clickPos->x * $clickPos->x + $clickPos->y * $clickPos->y + $clickPos->z * $clickPos->z;

        if ($lenSqr === 0.0) {
            // Zero click position detected - likely scaffold hack
            $this->fail($player, 1.0, [
                'click_x' => $clickPos->x,
                'click_y' => $clickPos->y,
                'click_z' => $clickPos->z,
                'trigger_type' => $triggerType
            ]);
        }
    }

    /**
     * Alternative check method if you have separate components
     *
     * @param OomphPlayer $player The player placing the block
     * @param float $clickX Click X position (0-1 range)
     * @param float $clickY Click Y position (0-1 range)
     * @param float $clickZ Click Z position (0-1 range)
     * @param int $triggerType The trigger type from UseItemTransactionData
     * @param int $protocolVersion The client's protocol version
     */
    public function checkComponents(OomphPlayer $player, float $clickX, float $clickY, float $clickZ, int $triggerType = self::TRIGGER_TYPE_PLAYER_INPUT, int $protocolVersion = 999): void {
        // Go: Only check for 1.21.20+ (protocol version 712+)
        if ($protocolVersion < self::MIN_VERSION_FOR_TRIGGER) {
            return;
        }

        // Go: Only flag when TriggerType == TriggerTypePlayerInput
        if ($triggerType !== self::TRIGGER_TYPE_PLAYER_INPUT) {
            return;
        }

        // Check if click position length squared is zero (Go: ClickedPosition.LenSqr() == 0)
        $lenSqr = $clickX * $clickX + $clickY * $clickY + $clickZ * $clickZ;

        if ($lenSqr === 0.0) {
            // Zero click position detected - likely scaffold hack
            $this->fail($player, 1.0, [
                'click_x' => $clickX,
                'click_y' => $clickY,
                'click_z' => $clickZ,
                'trigger_type' => $triggerType
            ]);
        }
    }
}
