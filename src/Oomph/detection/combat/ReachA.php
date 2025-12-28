<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * ReachA Detection
 *
 * Uses the CombatComponent's 10-step lerp raycast results to detect reach hacks.
 * Based on anticheat-reference/player/detection/reach_a.go
 *
 * Checks if player's combat reach exceeds the vanilla value.
 * Only applicable for non-touch clients.
 */
class ReachA extends Detection {

    // Reach thresholds from Go implementation
    private const MIN_REACH_THRESHOLD = 2.9;
    private const MAX_REACH_THRESHOLD = 3.0;

    // Skip checks for this many ticks after teleport
    private const TICKS_AFTER_TELEPORT = 20;

    // Input mode constants
    private const INPUT_MODE_TOUCH = 0;

    private int $ticksSinceTeleport = 999;
    private int $correctionCooldown = 0;

    // Ticks per second (standard Minecraft/Bedrock rate)
    private const TICKS_PER_SECOND = 20;

    public function __construct() {
        // From Go: FailBuffer: 1.01, MaxBuffer: 1.5, MaxViolations: 7
        // TrustDuration: 60 * TicksPerSecond (60 seconds = 1200 ticks)
        parent::__construct(
            maxBuffer: 1.5,
            failBuffer: 1.01,
            trustDuration: 60 * self::TICKS_PER_SECOND  // 1200 ticks = 60 seconds
        );
    }

    public function getName(): string {
        return "ReachA";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 7.0;
    }

    /**
     * This detection can cancel attacks
     */
    public function isCancellable(): bool {
        return true;
    }

    /**
     * Reset teleport counter
     */
    public function onTeleport(): void {
        $this->ticksSinceTeleport = 0;
    }

    /**
     * Notify of a correction being sent (movement correction)
     */
    public function onCorrection(): void {
        $this->correctionCooldown = 20; // Match Go: InCorrectionCooldown checks <= 20
    }

    /**
     * Increment ticks since teleport and decrement correction cooldown
     */
    public function tick(): void {
        if ($this->ticksSinceTeleport < self::TICKS_AFTER_TELEPORT) {
            $this->ticksSinceTeleport++;
        }
        if ($this->correctionCooldown > 0) {
            $this->correctionCooldown--;
        }
    }

    /**
     * Check combat reach using CombatComponent's raycast results
     * This matches the Go implementation's hook function exactly
     *
     * @param OomphPlayer $player The attacking player
     */
    public function check(OomphPlayer $player): void {
        // Skip touch clients (line 52 in Go)
        if ($player->getInputMode() === self::INPUT_MODE_TOUCH) {
            return;
        }

        // Skip first 20 ticks after teleport (line 52 in Go)
        if ($this->ticksSinceTeleport <= self::TICKS_AFTER_TELEPORT) {
            return;
        }

        // Skip during correction cooldown (line 52 in Go)
        if ($this->correctionCooldown > 0) {
            return;
        }

        // Get raycast results from combat component
        $combatComponent = $player->getCombatComponent();
        $raycasts = $combatComponent->getRaycasts();

        // No raycasts means no validation needed (line 56-58 in Go)
        if ($raycasts === []) {
            return;
        }

        // Calculate min and max reach from raycasts (lines 60-69 in Go)
        $minReach = PHP_FLOAT_MAX;
        $maxReach = 0.0;

        foreach ($raycasts as $dist) {
            if ($dist > $maxReach) {
                $maxReach = $dist;
            }
            if ($dist < $minReach) {
                $minReach = $dist;
            }
        }

        // Flag if both thresholds exceeded (line 70 in Go)
        if ($minReach > self::MIN_REACH_THRESHOLD && $maxReach > self::MAX_REACH_THRESHOLD) {
            $this->fail($player, 1.0, [
                'min_reach' => $minReach,
                'max_reach' => $maxReach
            ]);
        } else {
            // Pass with small decay (line 74 in Go)
            $this->pass(0.0015);
        }
    }

    /**
     * Get ticks since last teleport for debugging
     */
    public function getTicksSinceTeleport(): int {
        return $this->ticksSinceTeleport;
    }

    /**
     * Get correction cooldown for debugging
     */
    public function getCorrectionCooldown(): int {
        return $this->correctionCooldown;
    }
}
