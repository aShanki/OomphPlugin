<?php

declare(strict_types=1);

namespace Oomph\detection\packet;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * BadPacketA Detection
 *
 * Purpose: Detect manipulated tick/frame values
 * Trigger: Invalid simulation frame (frame=0 after non-zero)
 *
 * How it works:
 * - Flag if player sends SimulationFrame=0 after previously sending non-zero frame
 * - This indicates packet manipulation or timer cheats
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketA extends Detection {

    public function __construct() {
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "BadPacketA";
    }

    public function getType(): string {
        return self::TYPE_PACKET;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check for invalid simulation frame
     *
     * @param OomphPlayer $player The player to check
     * @param int $currentFrame Current simulation frame from packet
     * @param int $lastFrame Last simulation frame received
     */
    public function process(OomphPlayer $player, int $currentFrame, int $lastFrame): void {
        // Flag if current frame is 0 after non-zero frame
        if ($currentFrame === 0 && $lastFrame > 0) {
            $this->fail($player, 1.0, [
                'current_frame' => $currentFrame,
                'last_frame' => $lastFrame
            ]);
        }
    }
}
