<?php

declare(strict_types=1);

namespace Oomph\detection\packet;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * BadPacketF Detection
 *
 * Purpose: Detect inventory manipulation
 * Trigger: Invalid hotbar slot, trigger type, or client prediction
 *
 * How it works:
 * 1. Validate hotbar slot is within [0, 9)
 * 2. Version 1.21.20+: Validate trigger type and client prediction for block clicks
 *    - TriggerType must be PlayerInput or SimulationTick
 *    - ClientPrediction must be Failure or Success
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketF extends Detection {

    /** Minimum valid hotbar slot */
    private const MIN_HOTBAR_SLOT = 0;

    /** Maximum valid hotbar slot (exclusive) */
    private const MAX_HOTBAR_SLOT = 9;

    // Trigger type constants (Go: protocol.TriggerType*)
    public const TRIGGER_TYPE_UNKNOWN = 0;
    public const TRIGGER_TYPE_PLAYER_INPUT = 1;
    public const TRIGGER_TYPE_SIMULATION_TICK = 2;

    // Client prediction constants (Go: protocol.ClientPrediction*)
    public const CLIENT_PREDICTION_FAILURE = 0;
    public const CLIENT_PREDICTION_SUCCESS = 1;

    // Protocol version for 1.21.20 (trigger type validation)
    private const PROTOCOL_VERSION_1_21_20 = 712;

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
            $this->fail($player, 1.0, [
                'hotbar_slot' => $hotbarSlot
            ]);
        }
    }

    /**
     * Check for invalid trigger type and client prediction (1.21.20+ for block clicks)
     * Called from UseItemTransactionData with ActionType=ClickBlock (Go: lines 56-64)
     *
     * @param OomphPlayer $player The player to check
     * @param int $triggerType The trigger type from packet
     * @param int $clientPrediction The client prediction result from packet
     * @param int $protocolVersion The client's protocol version
     */
    public function processBlockClick(OomphPlayer $player, int $triggerType, int $clientPrediction, int $protocolVersion): void {
        // Only check for 1.21.20+ (Go: line 56)
        if ($protocolVersion < self::PROTOCOL_VERSION_1_21_20) {
            return;
        }

        // Validate trigger type (Go: lines 59-61)
        // Must be PlayerInput or SimulationTick
        if ($triggerType !== self::TRIGGER_TYPE_PLAYER_INPUT && $triggerType !== self::TRIGGER_TYPE_SIMULATION_TICK) {
            $this->fail($player, 1.0, [
                'trigger_type' => $triggerType,
                'reason' => 'invalid_trigger_type'
            ]);
        }

        // Validate client prediction (Go: lines 62-64)
        // Must be Failure or Success
        if ($clientPrediction !== self::CLIENT_PREDICTION_FAILURE && $clientPrediction !== self::CLIENT_PREDICTION_SUCCESS) {
            $this->fail($player, 1.0, [
                'client_prediction' => $clientPrediction,
                'reason' => 'invalid_client_prediction'
            ]);
        }
    }
}
