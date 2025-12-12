<?php

declare(strict_types=1);

namespace Oomph\detection\packet;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * BadPacketF Detection
 *
 * Purpose: Detect inventory manipulation
 * Trigger: Invalid hotbar slot
 *
 * How it works:
 * 1. Validate hotbar slot is within [0, 9)
 * 2. Version 1.21.20+: Validate trigger type and client prediction for block clicks
 *    - TriggerType must be valid
 *    - ClientPredictedResult must be valid
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketF extends Detection {

    /** Minimum valid hotbar slot */
    private const MIN_HOTBAR_SLOT = 0;

    /** Maximum valid hotbar slot (exclusive) */
    private const MAX_HOTBAR_SLOT = 9;

    public function __construct() {
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "BadPacketF";
    }

    public function getType(): string {
        return self::TYPE_PACKET;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check for invalid hotbar slot
     *
     * @param OomphPlayer $player The player to check
     * @param int $hotbarSlot The hotbar slot from packet
     */
    public function process(OomphPlayer $player, int $hotbarSlot): void {
        // Check if hotbar slot is out of range [0, 9)
        if ($hotbarSlot < self::MIN_HOTBAR_SLOT || $hotbarSlot >= self::MAX_HOTBAR_SLOT) {
            $this->fail($player, 1.0);
        }
    }

    /**
     * Check for invalid trigger type (1.21.20+)
     *
     * @param OomphPlayer $player The player to check
     * @param int $triggerType The trigger type from packet
     */
    public function processTriggerType(OomphPlayer $player, int $triggerType): void {
        // TODO: Implement trigger type validation when 1.21.20+ support is added
        // For now, this is a placeholder for future implementation
    }
}
