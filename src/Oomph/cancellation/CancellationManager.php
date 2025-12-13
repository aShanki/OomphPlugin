<?php

declare(strict_types=1);

namespace Oomph\cancellation;

use Oomph\player\OomphPlayer;

/**
 * Manages event cancellation state for a player
 * Used by detections to cancel combat events when cheating is detected
 */
class CancellationManager {

    private OomphPlayer $player;

    // Attack cancellation
    private bool $cancelAttack = false;
    private ?string $cancelReason = null;

    // Future: Add more event cancellation types
    // private bool $cancelMovement = false;
    // private bool $cancelBlockPlace = false;
    // etc.

    public function __construct(OomphPlayer $player) {
        $this->player = $player;
    }

    /**
     * Check if the current attack should be cancelled
     */
    public function shouldCancelAttack(): bool {
        return $this->cancelAttack;
    }

    /**
     * Set whether to cancel the current attack
     *
     * @param bool $cancel Whether to cancel
     * @param string|null $reason Optional reason for cancellation (for logging/debugging)
     */
    public function setCancelAttack(bool $cancel, ?string $reason = null): void {
        $this->cancelAttack = $cancel;
        $this->cancelReason = $reason;
    }

    /**
     * Get the reason for attack cancellation
     */
    public function getCancelReason(): ?string {
        return $this->cancelReason;
    }

    /**
     * Reset all cancellation flags (should be called each tick)
     */
    public function reset(): void {
        $this->cancelAttack = false;
        $this->cancelReason = null;
    }

    /**
     * Get the player this manager belongs to
     */
    public function getPlayer(): OomphPlayer {
        return $this->player;
    }
}
