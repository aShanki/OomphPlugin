<?php

declare(strict_types=1);

namespace Oomph\player\component;

use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;

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

    // Pending actions (legacy - kept for compatibility)
    private ?Vector3 $knockbackVelocity = null;
    private ?Vector3 $pendingTeleport = null;

    // Metrics
    private float $fallDistance = 0.0;
    private float $movementSpeed = 0.0;

    // Server-side authoritative state
    private Vector3 $authPosition;
    private Vector3 $authVelocity;
    private Vector3 $authMov;  // velocity before friction/gravity

    // Physics state
    private float $gravity = 0.08;
    private float $jumpHeight = 0.42;
    private float $defaultMovementSpeed = 0.1;
    private float $airSpeed = 0.02;

    // Jump state
    private int $jumpDelay = 0;
    private bool $pressingJump = false;
    private bool $pressingSneak = false;

    // Knockback state
    private ?Vector3 $knockback = null;
    private int $ticksSinceKnockback = 999;

    // Teleport state
    private ?Vector3 $teleportPos = null;
    private int $ticksSinceTeleport = 999;
    private int $remainingTeleportTicks = 0;
    private bool $teleportSmoothed = false;

    // Collision state (enhanced)
    private bool $stuckInCollider = false;
    private bool $penetratedLastFrame = false;

    // Correction state
    private int $pendingCorrections = 0;
    private int $ticksSinceCorrection = 999;

    // Supporting block
    private ?Vector3 $supportingBlockPos = null;

    // Immobility
    private bool $immobile = false;
    private bool $noClip = false;
    private bool $gliding = false;
    private int $glideBoost = 0;

    // Player size for bounding box
    private float $width = 0.6;
    private float $height = 1.8;

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

        // Initialize authoritative state
        $this->authPosition = clone $initialPosition;
        $this->authVelocity = new Vector3(0, 0, 0);
        $this->authMov = new Vector3(0, 0, 0);
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
     * @return array{yaw: float, pitch: float}
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

    // ========== NEW AUTHORITATIVE MOVEMENT METHODS ==========

    // Authoritative position
    public function getAuthPosition(): Vector3 {
        return $this->authPosition;
    }

    public function setAuthPosition(Vector3 $position): void {
        $this->authPosition = $position;
    }

    // Authoritative velocity
    public function getAuthVelocity(): Vector3 {
        return $this->authVelocity;
    }

    public function setAuthVelocity(Vector3 $velocity): void {
        $this->authVelocity = $velocity;
    }

    // Authoritative mov (velocity before friction/gravity)
    public function getAuthMov(): Vector3 {
        return $this->authMov;
    }

    public function setAuthMov(Vector3 $mov): void {
        $this->authMov = $mov;
    }

    // Physics parameters
    public function getGravity(): float {
        return $this->gravity;
    }

    public function setGravity(float $gravity): void {
        $this->gravity = $gravity;
    }

    public function getJumpHeight(): float {
        return $this->jumpHeight;
    }

    public function setJumpHeight(float $jumpHeight): void {
        $this->jumpHeight = $jumpHeight;
    }

    public function getDefaultMovementSpeed(): float {
        return $this->defaultMovementSpeed;
    }

    public function setDefaultMovementSpeed(float $speed): void {
        $this->defaultMovementSpeed = $speed;
    }

    public function getAirSpeed(): float {
        return $this->airSpeed;
    }

    public function setAirSpeed(float $speed): void {
        $this->airSpeed = $speed;
    }

    // Jump delay
    public function getJumpDelay(): int {
        return $this->jumpDelay;
    }

    public function setJumpDelay(int $delay): void {
        $this->jumpDelay = $delay;
    }

    public function decrementJumpDelay(): void {
        if ($this->jumpDelay > 0) {
            $this->jumpDelay--;
        }
    }

    // Pressing states
    public function isPressingJump(): bool {
        return $this->pressingJump;
    }

    public function setPressingJump(bool $pressing): void {
        $this->pressingJump = $pressing;
    }

    public function isPressingSneak(): bool {
        return $this->pressingSneak;
    }

    public function setPressingSneak(bool $pressing): void {
        $this->pressingSneak = $pressing;
    }

    // Knockback
    public function hasKnockback(): bool {
        return $this->knockback !== null && $this->ticksSinceKnockback === 0;
    }

    public function getKnockback(): ?Vector3 {
        return $this->knockback;
    }

    public function setKnockback(Vector3 $knockback): void {
        $this->knockback = $knockback;
        $this->ticksSinceKnockback = 0;
    }

    public function consumeKnockback(): void {
        $this->knockback = null;
        $this->ticksSinceKnockback++;
    }

    public function getTicksSinceKnockback(): int {
        return $this->ticksSinceKnockback;
    }

    public function incrementTicksSinceKnockback(): void {
        $this->ticksSinceKnockback++;
    }

    // Teleport
    public function hasTeleport(): bool {
        return $this->teleportPos !== null && $this->ticksSinceTeleport <= $this->remainingTeleportTicks;
    }

    public function getTeleportPos(): ?Vector3 {
        return $this->teleportPos;
    }

    public function setTeleport(Vector3 $pos, bool $smoothed = false, int $ticks = 0): void {
        $this->teleportPos = $pos;
        $this->teleportSmoothed = $smoothed;
        $this->remainingTeleportTicks = $ticks;
        $this->ticksSinceTeleport = 0;
    }

    public function consumeTeleport(): void {
        $this->teleportPos = null;
        $this->ticksSinceTeleport++;
    }

    public function isTeleportSmoothed(): bool {
        return $this->teleportSmoothed;
    }

    public function getRemainingTeleportTicks(): int {
        return max(0, $this->remainingTeleportTicks - $this->ticksSinceTeleport);
    }

    public function getTicksSinceTeleport(): int {
        return $this->ticksSinceTeleport;
    }

    public function incrementTicksSinceTeleport(): void {
        $this->ticksSinceTeleport++;
    }

    // Collision state
    public function isStuckInCollider(): bool {
        return $this->stuckInCollider;
    }

    public function setStuckInCollider(bool $stuck): void {
        $this->stuckInCollider = $stuck;
    }

    public function isPenetratedLastFrame(): bool {
        return $this->penetratedLastFrame;
    }

    public function setPenetratedLastFrame(bool $penetrated): void {
        $this->penetratedLastFrame = $penetrated;
    }

    // Corrections
    public function getPendingCorrections(): int {
        return $this->pendingCorrections;
    }

    public function incrementPendingCorrections(): void {
        $this->pendingCorrections++;
    }

    public function decrementPendingCorrections(): void {
        if ($this->pendingCorrections > 0) {
            $this->pendingCorrections--;
        }
    }

    public function getTicksSinceCorrection(): int {
        return $this->ticksSinceCorrection;
    }

    public function resetTicksSinceCorrection(): void {
        $this->ticksSinceCorrection = 0;
    }

    public function incrementTicksSinceCorrection(): void {
        $this->ticksSinceCorrection++;
    }

    // Supporting block
    public function getSupportingBlockPos(): ?Vector3 {
        return $this->supportingBlockPos;
    }

    public function setSupportingBlockPos(?Vector3 $pos): void {
        $this->supportingBlockPos = $pos;
    }

    // Immobility
    public function isImmobile(): bool {
        return $this->immobile;
    }

    public function setImmobile(bool $immobile): void {
        $this->immobile = $immobile;
    }

    public function isNoClip(): bool {
        return $this->noClip;
    }

    public function setNoClip(bool $noClip): void {
        $this->noClip = $noClip;
    }

    public function isGliding(): bool {
        return $this->gliding;
    }

    public function setGliding(bool $gliding): void {
        $this->gliding = $gliding;
    }

    public function getGlideBoost(): int {
        return $this->glideBoost;
    }

    public function setGlideBoost(int $boost): void {
        $this->glideBoost = $boost;
    }

    public function decrementGlideBoost(): void {
        if ($this->glideBoost > 0) {
            $this->glideBoost--;
        }
    }

    // Bounding box
    public function getBoundingBox(): AxisAlignedBB {
        $halfWidth = $this->width / 2.0;
        return new AxisAlignedBB(
            $this->position->x - $halfWidth,
            $this->position->y,
            $this->position->z - $halfWidth,
            $this->position->x + $halfWidth,
            $this->position->y + $this->height,
            $this->position->z + $halfWidth
        );
    }

    public function getWidth(): float {
        return $this->width;
    }

    public function setWidth(float $width): void {
        $this->width = $width;
    }

    public function getHeight(): float {
        return $this->height;
    }

    public function setHeight(float $height): void {
        $this->height = $height;
    }

    // Set all collision flags at once
    public function setCollisions(bool $x, bool $y, bool $z): void {
        $this->collisionX = $x;
        $this->collisionY = $y;
        $this->collisionZ = $z;
    }

    /**
     * Reset simulation state (copy client state to server state)
     */
    public function reset(): void {
        $this->authPosition = clone $this->position;
        $this->authVelocity = clone $this->velocity;
        $this->authMov = clone $this->velocity;
    }

    /**
     * Handle teleport event - resets teleport tracking state
     */
    public function onTeleport(): void {
        $this->teleportPos = $this->position;
        $this->ticksSinceTeleport = 0;
        $this->remainingTeleportTicks = 20; // Default teleport grace period

        // Sync auth position with client position on teleport
        $this->authPosition = clone $this->position;
        $this->authVelocity = Vector3::zero();
    }

    /**
     * Apply knockback velocity from an external source
     *
     * @param Vector3 $velocity The knockback velocity to apply
     */
    public function applyKnockback(Vector3 $velocity): void {
        $this->knockback = $velocity;
        $this->ticksSinceKnockback = 0;

        // Update auth velocity to include knockback
        $this->authVelocity = $velocity;
    }
}
