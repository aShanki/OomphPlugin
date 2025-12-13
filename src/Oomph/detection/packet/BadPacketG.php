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
 *   - UseItemTransactionData
 *   - PlayerAuthInput block interactions
 * - Block faces: 0=Down, 1=Up, 2=North, 3=South, 4=West, 5=East
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketG extends Detection {

    /** Minimum valid block face */
    private const MIN_BLOCK_FACE = 0;

    /** Maximum valid block face */
    private const MAX_BLOCK_FACE = 5;

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
     * Check for invalid block face
     *
     * @param OomphPlayer $player The player to check
     * @param int $blockFace The block face from packet
     */
    public function process(OomphPlayer $player, int $blockFace): void {
        // Check if block face is out of range [0, 5]
        if ($blockFace < self::MIN_BLOCK_FACE || $blockFace > self::MAX_BLOCK_FACE) {
            $this->fail($player, 1.0, [
                'block_face' => $blockFace
            ]);
        }
    }
}
