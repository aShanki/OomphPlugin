<?php

declare(strict_types=1);

namespace Oomph\player\correction;

use Oomph\player\OomphPlayer;
use Oomph\utils\PhysicsConstants;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;

/**
 * Handles movement corrections sent to the client
 * When server simulation differs from client, this sends a correction packet
 */
class CorrectionHandler {

    private OomphPlayer $player;

    /** Threshold for position difference to trigger correction (in blocks) */
    private float $correctionThreshold;

    /** Ticks to wait after a correction before checking reach, etc. */
    private int $cooldownTicks;

    /** Ticks since last correction was sent */
    private int $ticksSinceCorrection = 999;

    public function __construct(OomphPlayer $player, float $threshold = PhysicsConstants::DEFAULT_CORRECTION_THRESHOLD) {
        $this->player = $player;
        $this->correctionThreshold = $threshold;
        $this->cooldownTicks = PhysicsConstants::TICKS_AFTER_TELEPORT;
    }

    /**
     * Check if correction is needed based on position difference
     *
     * @param Vector3 $serverPos Server-predicted position
     * @param Vector3 $clientPos Client-reported position
     * @param float|null $threshold Custom threshold (uses default if null)
     * @return bool True if correction should be sent
     */
    public function shouldCorrect(Vector3 $serverPos, Vector3 $clientPos, ?float $threshold = null): bool {
        $threshold = $threshold ?? $this->correctionThreshold;

        $movement = $this->player->getMovementComponent();

        // Don't correct during teleport
        if ($movement->getTicksSinceTeleport() < $this->cooldownTicks) {
            return false;
        }

        // Don't correct if already pending corrections
        if ($movement->getPendingCorrections() > 0) {
            return false;
        }

        // Calculate position difference
        $diff = $serverPos->subtract($clientPos->x, $clientPos->y, $clientPos->z);
        $distance = sqrt($diff->x * $diff->x + $diff->y * $diff->y + $diff->z * $diff->z);

        return $distance > $threshold;
    }

    /**
     * Send movement correction to client
     * Synchronizes client position with server prediction
     */
    public function sendCorrection(): void {
        $movement = $this->player->getMovementComponent();
        $pmPlayer = $this->player->getPlayer();

        // Increment pending corrections
        $movement->incrementPendingCorrections();
        $movement->resetTicksSinceCorrection();
        $this->ticksSinceCorrection = 0;

        // Get server-predicted position and velocity
        $serverPos = $movement->getAuthPosition();
        $serverVel = $movement->getAuthVelocity();
        $onGround = $movement->isOnGround();

        // Add player eye height for packet (client expects eye position)
        $packetPos = new Vector3(
            $serverPos->x,
            $serverPos->y + PhysicsConstants::DEFAULT_PLAYER_HEIGHT_OFFSET,
            $serverPos->z
        );

        // Create and send correction packet using static factory method
        $pk = CorrectPlayerMovePredictionPacket::create(
            position: $packetPos,
            delta: $serverVel,
            onGround: $onGround,
            tick: $this->player->getSimulationFrame(),
            predictionType: CorrectPlayerMovePredictionPacket::PREDICTION_TYPE_PLAYER,
            vehicleRotation: new Vector2(0, 0),
            vehicleAngularVelocity: null
        );

        $pmPlayer->getNetworkSession()->sendDataPacket($pk);

        // Mark that we're waiting for correction ACK
        $this->player->setPendingCorrectionACK(true);
    }

    /**
     * Handle correction acknowledgment from client
     * Called when client confirms they've applied the correction
     */
    public function onCorrectionAck(): void {
        $movement = $this->player->getMovementComponent();

        // Decrement pending corrections
        $movement->decrementPendingCorrections();

        // Clear pending ACK flag
        $this->player->setPendingCorrectionACK(false);
    }

    /**
     * Check if in correction cooldown
     * During cooldown, we should be more lenient with checks
     *
     * @return bool True if in cooldown
     */
    public function isInCooldown(): bool {
        return $this->ticksSinceCorrection < $this->cooldownTicks;
    }

    /**
     * Get ticks since last correction was sent
     *
     * @return int Ticks
     */
    public function getTicksSinceCorrection(): int {
        return $this->ticksSinceCorrection;
    }

    /**
     * Update cooldown timer (call each tick)
     */
    public function tick(): void {
        if ($this->ticksSinceCorrection < 999) {
            $this->ticksSinceCorrection++;
        }

        $movement = $this->player->getMovementComponent();
        $movement->incrementTicksSinceCorrection();
    }

    /**
     * Set custom correction threshold
     *
     * @param float $threshold Threshold in blocks
     */
    public function setCorrectionThreshold(float $threshold): void {
        $this->correctionThreshold = $threshold;
    }

    /**
     * Get current correction threshold
     *
     * @return float Threshold in blocks
     */
    public function getCorrectionThreshold(): float {
        return $this->correctionThreshold;
    }

    /**
     * Set cooldown ticks
     *
     * @param int $ticks Ticks to wait after correction
     */
    public function setCooldownTicks(int $ticks): void {
        $this->cooldownTicks = $ticks;
    }

    /**
     * Get cooldown ticks
     *
     * @return int Ticks
     */
    public function getCooldownTicks(): int {
        return $this->cooldownTicks;
    }

    /**
     * Force reset cooldown (e.g., after teleport)
     */
    public function resetCooldown(): void {
        $this->ticksSinceCorrection = 999;
        $this->player->getMovementComponent()->resetTicksSinceCorrection();
    }
}
