<?php

declare(strict_types=1);

namespace Oomph\detection\packet;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * BadPacketG Detection
 *
 * Purpose: Detect impossible block interactions
 * Trigger: Invalid block face
 *
 * How it works:
 * - Validate block face value is valid (0-5) for:
 *   - UseItemTransactionData (except ClickAir action)
 *   - PlayerAuthInput block interactions (except AbortBreak)
 * - Block faces: 0=Down, 1=Up, 2=North, 3=South, 4=West, 5=East
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketG extends Detection {

    /** Minimum valid block face */
    private const MIN_BLOCK_FACE = 0;

    /** Maximum valid block face */
    private const MAX_BLOCK_FACE = 5;

    // Action type constants (Go: protocol.UseItemAction*)
    public const USE_ITEM_ACTION_CLICK_BLOCK = 0;
    public const USE_ITEM_ACTION_CLICK_AIR = 1;
    public const USE_ITEM_ACTION_BREAK_BLOCK = 2;

    // Player action constants (Go: protocol.PlayerAction*)
    public const PLAYER_ACTION_ABORT_BREAK = 18;

    public function __construct() {
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "BadPacketG";
    }

    public function getType(): string {
        return self::TYPE_PACKET;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check for invalid block face from UseItemTransactionData (Go: lines 48-55)
     *
     * @param OomphPlayer $player The player to check
     * @param int $blockFace The block face from packet
     * @param int $actionType The action type (ClickBlock, ClickAir, BreakBlock)
     */
    public function processUseItem(OomphPlayer $player, int $blockFace, int $actionType): void {
        // Skip if clicking air - block face doesn't matter (Go: line 53)
        if ($actionType === self::USE_ITEM_ACTION_CLICK_AIR) {
            return;
        }

        // Check if block face is out of range [0, 5]
        if (!$this->isBlockFaceValid($blockFace)) {
            $this->fail($player, 1.0, [
                'block_face' => $blockFace,
                'action_type' => $actionType
            ]);
        }
    }

    /**
     * Check for invalid block face from PlayerAuthInput ItemInteractionData (Go: lines 57-61)
     *
     * @param OomphPlayer $player The player to check
     * @param int $blockFace The block face from ItemInteractionData
     */
    public function processItemInteraction(OomphPlayer $player, int $blockFace): void {
        if (!$this->isBlockFaceValid($blockFace)) {
            $this->fail($player, 1.0, [
                'block_face' => $blockFace,
                'source' => 'item_interaction'
            ]);
        }
    }

    /**
     * Check for invalid block face from PlayerAuthInput BlockActions (Go: lines 62-68)
     *
     * @param OomphPlayer $player The player to check
     * @param int $blockFace The face from BlockAction
     * @param int $actionType The action type
     */
    public function processBlockAction(OomphPlayer $player, int $blockFace, int $actionType): void {
        // Skip if aborting break - block face may be invalid (Go: line 64)
        if ($actionType === self::PLAYER_ACTION_ABORT_BREAK) {
            return;
        }

        if (!$this->isBlockFaceValid($blockFace)) {
            $this->fail($player, 1.0, [
                'block_face' => $blockFace,
                'action_type' => $actionType,
                'source' => 'block_action'
            ]);
        }
    }

    /**
     * Simple block face validation (matches Go: utils.IsBlockFaceValid)
     *
     * @param int $blockFace The block face to validate
     * @return bool True if valid (0-5)
     */
    private function isBlockFaceValid(int $blockFace): bool {
        return $blockFace >= self::MIN_BLOCK_FACE && $blockFace <= self::MAX_BLOCK_FACE;
    }

    /**
     * Legacy method - Check for invalid block face (backwards compatibility)
     *
     * @param OomphPlayer $player The player to check
     * @param int $blockFace The block face from packet
     * @deprecated Use processUseItem, processItemInteraction, or processBlockAction instead
     */
    public function process(OomphPlayer $player, int $blockFace): void {
        if (!$this->isBlockFaceValid($blockFace)) {
            $this->fail($player, 1.0, [
                'block_face' => $blockFace
            ]);
        }
    }
}
