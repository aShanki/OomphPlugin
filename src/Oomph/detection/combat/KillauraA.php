<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\entity\effect\VanillaEffects;

/**
 * KillauraA Detection
 *
 * Detects attacks without arm swing animation. Legitimate players must swing
 * their arm before attacking. Killaura cheats often skip this animation.
 */
class KillauraA extends Detection {

    // Base tick threshold before considering attack without swing
    private const BASE_MAX_TICK_DIFF = 10;

    public function __construct() {
        // MaxViolations: 1
        // FailBuffer: 1, MaxBuffer: 1
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
     * Check if player attacked without recent arm swing
     *
     * @param OomphPlayer $player The player being checked
     */
    public function check(OomphPlayer $player): void {
        $combatComponent = $player->getCombatComponent();
        $serverTick = $player->getServerTick();
        $lastSwingTick = $combatComponent->getLastSwingTick();

        // Calculate ticks since last swing
        $tickDiff = $serverTick - $lastSwingTick;

        // Calculate max allowed tick difference
        // Mining Fatigue increases the threshold
        $maxTickDiff = self::BASE_MAX_TICK_DIFF;

        $effectManager = $player->getPlayer()->getEffects();
        $effect = $effectManager->get(VanillaEffects::MINING_FATIGUE());
        if ($effect !== null) {
            $amplifier = $effect->getAmplifier();
            $maxTickDiff += $amplifier;
        }

        // Check if attack occurred too long after last swing
        if ($tickDiff > $maxTickDiff) {
            // Attack without recent arm swing animation
            $this->fail($player);
        } else {
            // Valid swing timing
            $this->pass();
        }
    }
}
