<?php

declare(strict_types=1);

namespace Oomph\detection\packet;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\player\GameMode;

/**
 * BadPacketC Detection
 *
 * Purpose: Detect instant-break hacks in survival
 * Trigger: Invalid block breaking while NOT in creative mode
 *
 * How it works:
 * - Flag if UseItemActionBreakBlock sent while NOT in creative mode
 * - Flag if PlayerAction break packet sent while NOT in creative mode
 * - Flag if ItemStackRequest break action sent
 * - Flag if PlayerAuthInput break action sent
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketC extends Detection {

    public function __construct() {
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "BadPacketC";
    }

    public function getType(): string {
        return self::TYPE_PACKET;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check for invalid block breaking
     *
     * @param OomphPlayer $player The player to check
     * @param bool $isBreakAction Whether a break action was detected
     */
    public function process(OomphPlayer $player, bool $isBreakAction): void {
        // Only flag if not in creative mode
        if ($player->getPlayer()->getGamemode() === GameMode::CREATIVE()) {
            return;
        }

        // Flag if break action detected in non-creative mode
        if ($isBreakAction) {
            $this->fail($player, 1.0, [
                'gamemode' => $player->getPlayer()->getGamemode()->name()
            ]);
        }
    }
}
