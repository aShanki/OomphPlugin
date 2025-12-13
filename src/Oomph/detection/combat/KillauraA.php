<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\entity\effect\VanillaEffects;

/**
 * KillauraA Detection
 *
 * Detects if a player is attacking without swinging their arm.
 * Based on anticheat-reference/player/detection/killaura_a.go
 *
 * Legitimate players must swing their arm before attacking.
 * Killaura cheats often skip this animation to attack faster.
 */
class KillauraA extends Detection {

    // Base tick threshold from Go implementation
    private const BASE_MAX_TICK_DIFF = 10;

    public function __construct() {
        // From Go: FailBuffer: 1, MaxBuffer: 1, MaxViolations: 1, no TrustDuration
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "KillauraA";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * This detection can cancel attacks
     */
    public function isCancellable(): bool {
        return true;
    }

    /**
     * Check if player attacked without recent arm swing
     * This matches the Go implementation's Detect function (lines 46-69)
     *
     * @param OomphPlayer $player The player being checked
     * @param int $currentTick Current simulation frame/tick
     */
    public function check(OomphPlayer $player, int $currentTick): void {
        $combatComponent = $player->getCombatComponent();
        $lastSwingTick = $combatComponent->getLastSwingTick();

        // Calculate ticks since last swing (line 55 in Go)
        $tickDiff = $currentTick - $lastSwingTick;

        // Calculate max allowed tick difference (lines 56-59 in Go)
        $maxTickDiff = self::BASE_MAX_TICK_DIFF;

        // Mining Fatigue increases the threshold (lines 57-59 in Go)
        $effectManager = $player->getPlayer()->getEffects();
        $miningFatigue = $effectManager->get(VanillaEffects::MINING_FATIGUE());
        if ($miningFatigue !== null) {
            $amplifier = $miningFatigue->getAmplifier();
            $maxTickDiff += $amplifier;
        }

        // Flag if attack occurred too long after last swing (lines 61-68 in Go)
        if ($tickDiff > $maxTickDiff) {
            $this->fail($player);
            // Debug log (lines 62-67 in Go)
            // Log: tick_diff=$tickDiff current_tick=$currentTick last_tick=$lastSwingTick
        }
    }

    /**
     * Get base max tick diff for debugging
     */
    public function getBaseMaxTickDiff(): int {
        return self::BASE_MAX_TICK_DIFF;
    }
}
