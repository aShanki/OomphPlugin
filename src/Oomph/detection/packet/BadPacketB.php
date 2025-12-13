<?php

declare(strict_types=1);

namespace Oomph\detection\packet;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * BadPacketB Detection
 *
 * Purpose: Detect impossible self-attacks
 * Trigger: Self-hitting (attacking own runtime ID)
 *
 * How it works:
 * - Flag if EntityRuntimeID in attack equals player's own RuntimeID
 * - Legitimate clients cannot attack themselves
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketB extends Detection {

    public function __construct() {
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "BadPacketB";
    }

    public function getType(): string {
        return self::TYPE_PACKET;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check for self-hitting
     *
     * @param OomphPlayer $player The player to check
     * @param int $targetEntityId The entity runtime ID being attacked
     */
    public function process(OomphPlayer $player, int $targetEntityId): void {
        // Get player's own runtime ID
        $playerEntityId = $player->getPlayer()->getId();

        // Flag if attacking self
        if ($targetEntityId === $playerEntityId) {
            $this->fail($player, 1.0, [
                'target_id' => $targetEntityId,
                'self_id' => $playerEntityId
            ]);
        }
    }
}
