<?php

declare(strict_types=1);

namespace Oomph\detection;

use Oomph\player\OomphPlayer;

/**
 * Abstract base class for all anticheat detections
 *
 * Implements a buffer-based violation system where:
 * - Buffer accumulates on suspicious behavior
 * - Buffer must exceed FailBuffer threshold to count as violation
 * - Violations accumulate until MaxViolations reached (punishment)
 * - Buffer decays over time when player behaves legitimately
 */
abstract class Detection {

    // Detection type constants
    public const TYPE_COMBAT = "combat";
    public const TYPE_MOVEMENT = "movement";
    public const TYPE_PACKET = "packet";
    public const TYPE_AUTH = "auth";
    public const TYPE_WORLD = "world";

    /** Current violation count */
    protected float $violations = 0.0;

    /** Current buffer level */
    protected float $buffer = 0.0;

    /** Maximum buffer value */
    protected float $maxBuffer;

    /** Buffer threshold to trigger violation */
    protected float $failBuffer;

    /** Trust duration in ticks (-1 for immediate violation) */
    protected int $trustDuration;

    /** Server tick when last flagged */
    protected int $lastFlagged = 0;

    /**
     * @param float $maxBuffer Maximum buffer value
     * @param float $failBuffer Buffer threshold to trigger violation
     * @param int $trustDuration Trust duration in ticks (-1 for immediate)
     */
    public function __construct(float $maxBuffer, float $failBuffer, int $trustDuration = -1) {
        $this->maxBuffer = $maxBuffer;
        $this->failBuffer = $failBuffer;
        $this->trustDuration = $trustDuration;
    }

    /**
     * Get the detection name (e.g., "AutoclickerA", "BadPacketB")
     */
    abstract public function getName(): string;

    /**
     * Get the detection type
     */
    abstract public function getType(): string;

    /**
     * Get maximum violations before punishment
     */
    abstract public function getMaxViolations(): float;

    /**
     * Increment buffer and check for violation
     *
     * @param OomphPlayer $player The player being checked
     * @param float $extra Additional buffer to add (default: 1.0)
     */
    public function fail(OomphPlayer $player, float $extra = 1.0): void {
        // Increment buffer (capped at maxBuffer)
        $this->buffer = min($this->buffer + $extra, $this->maxBuffer);

        // Check if buffer threshold reached
        if ($this->buffer < $this->failBuffer) {
            return;
        }

        // Calculate violation increment based on trust duration
        $increment = 1.0;
        if ($this->trustDuration > 0) {
            $serverTick = $player->getServerTick();
            $ticksSinceFlag = $serverTick - $this->lastFlagged;
            $increment = min(1.0, $ticksSinceFlag / $this->trustDuration);
        }

        // Add violations
        $this->violations += $increment;
        $this->lastFlagged = $player->getServerTick();

        // Fire flagged event (can be cancelled by other plugins)
        // TODO: Implement event system
        // $event = new PlayerFlaggedEvent($player, $this);
        // $event->call();
        // if ($event->isCancelled()) return;

        // Check if max violations reached
        if ($this->violations >= $this->getMaxViolations()) {
            // Fire punishment event (can be cancelled by other plugins)
            // TODO: Implement event system
            // $punishEvent = new PlayerPunishmentEvent($player, $this);
            // $punishEvent->call();
            // if (!$punishEvent->isCancelled()) {
                $player->getPlayer()->kick("Unfair Advantage: " . $this->getName());
            // }
        }
    }

    /**
     * Decay buffer (called when player behaves legitimately)
     *
     * @param float $amount Amount to decrease buffer by (default: 0.1)
     */
    public function pass(float $amount = 0.1): void {
        $this->buffer = max(0.0, $this->buffer - $amount);
    }

    /**
     * Reset violations and buffer to zero
     */
    public function reset(): void {
        $this->violations = 0.0;
        $this->buffer = 0.0;
    }

    /**
     * Get current violation count
     */
    public function getViolations(): float {
        return $this->violations;
    }

    /**
     * Get current buffer value
     */
    public function getBuffer(): float {
        return $this->buffer;
    }

    /**
     * Get fail buffer threshold
     */
    public function getFailBuffer(): float {
        return $this->failBuffer;
    }

    /**
     * Get max buffer value
     */
    public function getMaxBuffer(): float {
        return $this->maxBuffer;
    }

    /**
     * Get trust duration
     */
    public function getTrustDuration(): int {
        return $this->trustDuration;
    }

    /**
     * Get last flagged tick
     */
    public function getLastFlagged(): int {
        return $this->lastFlagged;
    }
}
