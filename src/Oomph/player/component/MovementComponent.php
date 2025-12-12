<?php

declare(strict_types=1);

namespace Oomph\player\component;

use pocketmine\math\Vector3;

/**
 * Tracks player movement state, position, velocity, rotation, and collision flags
 */
class MovementComponent {

    // Position tracking
    private Vector3 $position;
    private Vector3 $prevPosition;

    // Velocity tracking
    private Vector3 $velocity;
    private Vector3 $lastVelocity;

    // Rotation tracking
    private float $yaw;
    private float $pitch;
    private float $headYaw;
    private float $lastYaw;
    private float $lastPitch;

    // Input from MoveVec
    private float $impulseForward = 0.0;
    private float $impulseStrafe = 0.0;

    // State flags
    private bool $onGround = false;
    private bool $sprinting = false;
    private bool $sneaking = false;
    private bool $jumping = false;
    private bool $flying = false;
    private bool $swimming = false;
    private bool $climbing = false;

    // Collision flags
    private bool $collisionX = false;
    private bool $collisionY = false;
    private bool $collisionZ = false;

    // Pending actions
    private ?Vector3 $knockbackVelocity = null;
    private ?Vector3 $pendingTeleport = null;

    // Metrics
    private float $fallDistance = 0.0;
    private float $movementSpeed = 0.0;

    public function __construct(Vector3 $initialPosition, float $yaw, float $pitch, float $headYaw) {
        $this->position = clone $initialPosition;
        $this->prevPosition = clone $initialPosition;
        $this->velocity = new Vector3(0, 0, 0);
        $this->lastVelocity = new Vector3(0, 0, 0);
        $this->yaw = $yaw;
        $this->pitch = $pitch;
        $this->headYaw = $headYaw;
        $this->lastYaw = $yaw;
        $this->lastPitch = $pitch;
    }

    /**
     * Update movement state with new data
     */
    public function update(): void {
        // Store previous position
        $this->prevPosition = clone $this->position;

        // Store last velocity
        $this->lastVelocity = clone $this->velocity;

        // Store last rotation
        $this->lastYaw = $this->yaw;
        $this->lastPitch = $this->pitch;

        // Calculate movement speed
        $this->movementSpeed = $this->position->distance($this->prevPosition);
    }

    /**
     * Get the delta between current and previous position
     */
    public function getDeltaPosition(): Vector3 {
        return $this->position->subtractVector($this->prevPosition);
    }

    /**
     * Get the rotation delta (yaw, pitch)
     */
    public function getRotationDelta(): array {
        return [
            'yaw' => $this->yaw - $this->lastYaw,
            'pitch' => $this->pitch - $this->lastPitch
        ];
    }

    // Position getters/setters
    public function getPosition(): Vector3 {
        return $this->position;
    }

    public function setPosition(Vector3 $position): void {
        $this->position = $position;
    }

    public function getPrevPosition(): Vector3 {
        return $this->prevPosition;
    }

    // Velocity getters/setters
    public function getVelocity(): Vector3 {
        return $this->velocity;
    }

    public function setVelocity(Vector3 $velocity): void {
        $this->velocity = $velocity;
    }

    public function getLastVelocity(): Vector3 {
        return $this->lastVelocity;
    }

    // Rotation getters/setters
    public function getYaw(): float {
        return $this->yaw;
    }

    public function setYaw(float $yaw): void {
        $this->yaw = $yaw;
    }

    public function getPitch(): float {
        return $this->pitch;
    }

    public function setPitch(float $pitch): void {
        $this->pitch = $pitch;
    }

    public function getHeadYaw(): float {
        return $this->headYaw;
    }

    public function setHeadYaw(float $headYaw): void {
        $this->headYaw = $headYaw;
    }

    public function getLastYaw(): float {
        return $this->lastYaw;
    }

    public function getLastPitch(): float {
        return $this->lastPitch;
    }

    /**
     * Get current rotation as a Vector3 (yaw, pitch, headYaw)
     */
    public function getRotation(): Vector3 {
        return new Vector3($this->yaw, $this->pitch, $this->headYaw);
    }

    /**
     * Get previous rotation as a Vector3 (lastYaw, lastPitch, headYaw)
     */
    public function getLastRotation(): Vector3 {
        return new Vector3($this->lastYaw, $this->lastPitch, $this->headYaw);
    }

    /**
     * Update position from packet data
     */
    public function updatePosition(Vector3 $position): void {
        $this->position = $position;
    }

    /**
     * Update rotation from packet data
     */
    public function updateRotation(float $yaw, float $pitch): void {
        $this->yaw = $yaw;
        $this->pitch = $pitch;
    }

    // Input getters/setters
    public function getImpulseForward(): float {
        return $this->impulseForward;
    }

    public function setImpulseForward(float $impulse): void {
        $this->impulseForward = $impulse;
    }

    public function getImpulseStrafe(): float {
        return $this->impulseStrafe;
    }

    public function setImpulseStrafe(float $impulse): void {
        $this->impulseStrafe = $impulse;
    }

    // State flags getters/setters
    public function isOnGround(): bool {
        return $this->onGround;
    }

    public function setOnGround(bool $onGround): void {
        $this->onGround = $onGround;
    }

    public function isSprinting(): bool {
        return $this->sprinting;
    }

    public function setSprinting(bool $sprinting): void {
        $this->sprinting = $sprinting;
    }

    public function isSneaking(): bool {
        return $this->sneaking;
    }

    public function setSneaking(bool $sneaking): void {
        $this->sneaking = $sneaking;
    }

    public function isJumping(): bool {
        return $this->jumping;
    }

    public function setJumping(bool $jumping): void {
        $this->jumping = $jumping;
    }

    public function isFlying(): bool {
        return $this->flying;
    }

    public function setFlying(bool $flying): void {
        $this->flying = $flying;
    }

    public function isSwimming(): bool {
        return $this->swimming;
    }

    public function setSwimming(bool $swimming): void {
        $this->swimming = $swimming;
    }

    public function isClimbing(): bool {
        return $this->climbing;
    }

    public function setClimbing(bool $climbing): void {
        $this->climbing = $climbing;
    }

    // Collision flags getters/setters
    public function hasCollisionX(): bool {
        return $this->collisionX;
    }

    public function setCollisionX(bool $collision): void {
        $this->collisionX = $collision;
    }

    public function hasCollisionY(): bool {
        return $this->collisionY;
    }

    public function setCollisionY(bool $collision): void {
        $this->collisionY = $collision;
    }

    public function hasCollisionZ(): bool {
        return $this->collisionZ;
    }

    public function setCollisionZ(bool $collision): void {
        $this->collisionZ = $collision;
    }

    // Pending actions getters/setters
    public function getKnockbackVelocity(): ?Vector3 {
        return $this->knockbackVelocity;
    }

    public function setKnockbackVelocity(?Vector3 $velocity): void {
        $this->knockbackVelocity = $velocity;
    }

    public function clearKnockback(): void {
        $this->knockbackVelocity = null;
    }

    public function getPendingTeleport(): ?Vector3 {
        return $this->pendingTeleport;
    }

    public function setPendingTeleport(?Vector3 $position): void {
        $this->pendingTeleport = $position;
    }

    public function clearPendingTeleport(): void {
        $this->pendingTeleport = null;
    }

    // Metrics getters/setters
    public function getFallDistance(): float {
        return $this->fallDistance;
    }

    public function setFallDistance(float $distance): void {
        $this->fallDistance = $distance;
    }

    public function getMovementSpeed(): float {
        return $this->movementSpeed;
    }
}
