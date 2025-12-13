<?php

declare(strict_types=1);

namespace Oomph\detection\packet;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\player\GameMode;

/**
 * BadPacketD Detection
 *
 * Purpose: Detect item spawning hacks
 * Trigger: Creative transaction in survival
 *
 * How it works:
 * - Flag if CraftCreativeStackRequestAction received while GameMode != Creative
 * - Detects players trying to spawn items in survival mode
 *
 * Max Violations: 1 (instant kick)
 */
class BadPacketD extends Detection {

    public function __construct() {
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "BadPacketD";
    }

    public function getType(): string {
        return self::TYPE_PACKET;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check for creative transaction in survival
     *
     * @param OomphPlayer $player The player to check
     * @param bool $isCreativeAction Whether a creative action was detected
     */
    public function process(OomphPlayer $player, bool $isCreativeAction): void {
        // Only flag if not in creative mode
        if ($player->getPlayer()->getGamemode() === GameMode::CREATIVE()) {
            return;
        }

        // Flag if creative action detected in non-creative mode
        if ($isCreativeAction) {
            $this->fail($player, 1.0, [
                'gamemode' => $player->getPlayer()->getGamemode()->name()
            ]);
        }
    }
}
