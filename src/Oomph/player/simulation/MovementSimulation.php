<?php

declare(strict_types=1);

namespace Oomph\player\simulation;

use Oomph\player\OomphPlayer;
use Oomph\player\component\MovementComponent;
use Oomph\utils\Math;
use Oomph\utils\PhysicsConstants;
use Oomph\utils\AABBUtils;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\World;
use pocketmine\block\Block;
use pocketmine\block\Air;
use pocketmine\block\Liquid;

/**
 * Server-side movement simulation for anticheat
 * Ported from anticheat-reference/player/simulation/movement.go
 */
class MovementSimulation {

    /**
     * Main simulation entry point
     * Simulates one tick of player movement based on input
     */
    public static function simulate(OomphPlayer $player): void {
        $movement = $player->getMovementComponent();

        // Always simulate teleport first
        if (self::attemptTeleport($player)) {
            return;
        }

        // Check if simulation is reliable
        if (!self::simulationIsReliable($player)) {
            $movement->reset();
            return;
        }

        // Check if in unloaded chunk
        $world = $player->getPlayer()->getWorld();
        $pos = $movement->getAuthPosition();
        $chunkX = $pos->getFloorX() >> 4;
        $chunkZ = $pos->getFloorZ() >> 4;

        if (!$world->isChunkLoaded($chunkX, $chunkZ)) {
            $movement->setAuthVelocity(new Vector3(0, 0, 0));
            return;
        }

        // Check if immobile
        if ($movement->isImmobile() || !$player->isReady()) {
            $movement->setAuthVelocity(new Vector3(0, 0, 0));
            return;
        }

        // Reset velocity if insignificant
        $vel = $movement->getAuthVelocity();
        if ($vel->lengthSquared() < PhysicsConstants::VELOCITY_EPSILON) {
            $movement->setAuthVelocity(new Vector3(0, 0, 0));
        }

        // Get block under player
        $blockUnderPos = $pos->subtract(0, 0.5, 0);
        $blockUnder = $world->getBlock($blockUnderPos);

        // Calculate friction and movement speed
        $blockFriction = PhysicsConstants::DEFAULT_AIR_FRICTION;
        $moveRelativeSpeed = $movement->getAirSpeed();

        if ($movement->isOnGround()) {
            $mSpeed = $movement->getMovementSpeed();
            // Soul sand slows movement
            if ($blockUnder->getName() === "Soul Sand") {
                $mSpeed *= 0.543;
            }
            $blockFriction *= self::getBlockFriction($blockUnder);
            $moveRelativeSpeed = $mSpeed * (0.16277136 / ($blockFriction * $blockFriction * $blockFriction));
        }

        // Handle gliding
        if ($movement->isGliding()) {
            // TODO: Check for elytra in inventory
            if (!$movement->isOnGround()) {
                self::simulateGlide($player, $movement);
                $movement->setAuthMov($movement->getAuthVelocity());
                return;
            } else {
                $movement->setGliding(false);
            }
        }

        // Apply knockback if applicable
        self::attemptKnockback($movement);

        // Apply horizontal movement based on input
        self::moveRelative($movement, $moveRelativeSpeed);

        // Attempt jump
        $clientJumpPrevented = false;
        self::attemptJump($player, $clientJumpPrevented);

        // Handle climbing (ladders, vines, etc.)
        $blockAt = $world->getBlock($pos);
        if (self::isClimbable($blockAt)) {
            $newVel = clone $movement->getAuthVelocity();
            $negClimbSpeed = -PhysicsConstants::CLIMB_SPEED;

            if ($newVel->y < $negClimbSpeed) {
                $newVel->y = $negClimbSpeed;
            }

            if ($movement->isPressingJump() || $movement->hasCollisionX() || $movement->hasCollisionZ()) {
                $newVel->y = PhysicsConstants::CLIMB_SPEED;
            }

            if ($movement->isSneaking() && $newVel->y < 0) {
                $newVel->y = 0;
            }

            $movement->setAuthVelocity($newVel);
        }

        // Handle cobweb
        if ($blockAt->getName() === "Cobweb") {
            $newVel = clone $movement->getAuthVelocity();
            $newVel->x *= PhysicsConstants::COBWEB_X_MULTIPLIER;
            $newVel->y *= PhysicsConstants::COBWEB_Y_MULTIPLIER;
            $newVel->z *= PhysicsConstants::COBWEB_Z_MULTIPLIER;
            $movement->setAuthVelocity($newVel);
        }

        // Avoid edge if sneaking
        self::avoidEdge($movement, $world);

        // Store old values for post-collision logic
        $oldVel = clone $movement->getAuthVelocity();
        $oldOnGround = $movement->isOnGround();
        $oldY = $movement->getAuthPosition()->y;

        // Perform collision detection
        self::tryCollisions($player, $world, $clientJumpPrevented);

        // Update block under after collision
        if ($movement->getSupportingBlockPos() !== null) {
            $blockUnder = $world->getBlock($movement->getSupportingBlockPos());
        } else {
            $blockUnder = $world->getBlock($pos->subtract(0, 0.2, 0));
        }

        // Apply post-collision motion (landing, bouncing)
        self::setPostCollisionMotion($player, $oldVel, $oldOnGround, $blockUnder);

        // Store mov (velocity before friction/gravity)
        $movement->setAuthMov(clone $movement->getAuthVelocity());

        // Apply gravity and friction
        $newVel = clone $movement->getAuthVelocity();

        // Apply gravity
        $newVel->y -= $movement->getGravity();
        $newVel->y *= PhysicsConstants::NORMAL_GRAVITY_MULTIPLIER;

        // Apply friction
        $newVel->x *= $blockFriction;
        $newVel->z *= $blockFriction;

        $movement->setAuthVelocity($newVel);
    }

    /**
     * Apply input-based movement (strafe and forward)
     */
    private static function moveRelative(MovementComponent $movement, float $speed): void {
        $impulseForward = $movement->getImpulseForward();
        $impulseStrafe = $movement->getImpulseStrafe();

        $force = $impulseForward * $impulseForward + $impulseStrafe * $impulseStrafe;

        if ($force >= 1e-4) {
            $force = $speed / max(sqrt($force), 1.0);
            $mf = $impulseForward * $force;
            $ms = $impulseStrafe * $force;

            $yaw = $movement->getYaw() * M_PI / 180.0;
            $v2 = Math::mcSin($yaw);
            $v3 = Math::mcCos($yaw);

            $vel = clone $movement->getAuthVelocity();
            $vel->x += $ms * $v3 - $mf * $v2;
            $vel->z += $mf * $v3 + $ms * $v2;
            $movement->setAuthVelocity($vel);
        }
    }

    /**
     * Attempt to apply jump force
     */
    private static function attemptJump(OomphPlayer $player, bool &$clientJumpPrevented): void {
        $movement = $player->getMovementComponent();

        if (!$movement->isJumping() || !$movement->isOnGround() || $movement->getJumpDelay() > 0) {
            return;
        }

        $vel = clone $movement->getAuthVelocity();
        $vel->y = max($movement->getJumpHeight(), $vel->y);
        $movement->setJumpDelay(PhysicsConstants::JUMP_DELAY_TICKS);

        // Add sprint jump boost
        if ($movement->isSprinting()) {
            $force = $movement->getYaw() * 0.017453292;
            $vel->x -= Math::mcSin($force) * 0.2;
            $vel->z += Math::mcCos($force) * 0.2;
        }

        // TODO: Check if jump is blocked by blocks above

        $movement->setAuthVelocity($vel);
    }

    /**
     * Apply knockback if pending
     */
    private static function attemptKnockback(MovementComponent $movement): bool {
        if ($movement->hasKnockback()) {
            $kb = $movement->getKnockback();
            if ($kb !== null) {
                $movement->setAuthVelocity($kb);
                $movement->consumeKnockback();
                return true;
            }
        }
        return false;
    }

    /**
     * Handle teleport (instant or smoothed)
     */
    private static function attemptTeleport(OomphPlayer $player): bool {
        $movement = $player->getMovementComponent();

        if (!$movement->hasTeleport()) {
            return false;
        }

        $teleportPos = $movement->getTeleportPos();
        if ($teleportPos === null) {
            return false;
        }

        if (!$movement->isTeleportSmoothed()) {
            // Instant teleport
            $movement->setAuthPosition($teleportPos);
            $movement->setAuthVelocity(new Vector3(0, 0, 0));
            $movement->setJumpDelay(0);

            // Allow jump immediately after teleport
            $clientJumpPrevented = false;
            self::attemptJump($player, $clientJumpPrevented);

            $movement->consumeTeleport();
            return true;
        }

        // Smoothed teleport
        $remaining = $movement->getRemainingTeleportTicks();
        if ($remaining > 0) {
            $currentPos = $movement->getAuthPosition();
            $posDelta = $teleportPos->subtract($currentPos->x, $currentPos->y, $currentPos->z);
            $newPos = $currentPos->add(
                $posDelta->x / $remaining,
                $posDelta->y / $remaining,
                $posDelta->z / $remaining
            );
            $movement->setAuthPosition($newPos);
            $movement->setJumpDelay(0);
            $movement->incrementTicksSinceTeleport();
            return $remaining > 1;
        }

        return false;
    }

    /**
     * Collision detection with step-up support
     */
    private static function tryCollisions(OomphPlayer $player, World $world, bool $clientJumpPrevented): void {
        $movement = $player->getMovementComponent();
        $collisionBB = $movement->getBoundingBox();
        $vel = clone $movement->getAuthVelocity();

        // Get nearby block bounding boxes
        $expandedBB = self::expandBBByVelocity($collisionBB, $vel);
        $bbList = self::getNearbyBoundingBoxes($world, $expandedBB);

        // One-way collisions (for stuck entities)
        $useOneWayCollisions = $movement->isStuckInCollider();
        $penetration = new Vector3(0, 0, 0);

        // Y-axis collision
        $yVel = $clientJumpPrevented ? 0 : $vel->y;
        foreach ($bbList as $blockBB) {
            $yVel = self::clipCollide($blockBB, $collisionBB, 0, $yVel, 0, $useOneWayCollisions, $penetration)->y;
        }
        $collisionBB = self::offsetBB($collisionBB, 0, $yVel, 0);

        // X-axis collision
        $xVel = $vel->x;
        foreach ($bbList as $blockBB) {
            $xVel = self::clipCollide($blockBB, $collisionBB, $xVel, 0, 0, $useOneWayCollisions, $penetration)->x;
        }
        $collisionBB = self::offsetBB($collisionBB, $xVel, 0, 0);

        // Z-axis collision
        $zVel = $vel->z;
        foreach ($bbList as $blockBB) {
            $zVel = self::clipCollide($blockBB, $collisionBB, 0, 0, $zVel, $useOneWayCollisions, $penetration)->z;
        }
        $collisionBB = self::offsetBB($collisionBB, 0, 0, $zVel);

        $collisionVel = new Vector3($xVel, $yVel, $zVel);

        // Track penetration
        $hasPenetration = $penetration->lengthSquared() >= PhysicsConstants::PENETRATION_EPSILON;
        $movement->setStuckInCollider($movement->isPenetratedLastFrame() && $hasPenetration);
        $movement->setPenetratedLastFrame($hasPenetration);

        // Determine collisions
        $xCollision = abs($vel->x - $collisionVel->x) >= PhysicsConstants::COLLISION_EPSILON;
        $yCollision = abs($vel->y - $collisionVel->y) >= PhysicsConstants::COLLISION_EPSILON || $clientJumpPrevented;
        $zCollision = abs($vel->z - $collisionVel->z) >= PhysicsConstants::COLLISION_EPSILON;
        $onGround = $movement->isOnGround() || ($yCollision && $vel->y < 0.0);

        // Try step-up if colliding horizontally while on ground
        if ($onGround && ($xCollision || $zCollision)) {
            $stepResult = self::tryStepUp($movement, $bbList, $vel, $useOneWayCollisions);
            if ($stepResult !== null) {
                $collisionBB = $stepResult['bb'];
                $collisionVel = $stepResult['vel'];
            }
        }

        // Update position from bounding box
        $newPos = new Vector3(
            ($collisionBB->minX + $collisionBB->maxX) / 2.0,
            $collisionBB->minY,
            ($collisionBB->minZ + $collisionBB->maxZ) / 2.0
        );
        $movement->setAuthPosition($newPos);

        // Update collision flags
        $movement->setCollisions(
            abs($vel->x - $collisionVel->x) >= PhysicsConstants::COLLISION_EPSILON,
            abs($vel->y - $collisionVel->y) >= PhysicsConstants::COLLISION_EPSILON,
            abs($vel->z - $collisionVel->z) >= PhysicsConstants::COLLISION_EPSILON
        );

        // Update on-ground state
        $movement->setOnGround(
            ($yCollision && $vel->y < 0) ||
            ($movement->isOnGround() && !$yCollision && abs($vel->y) <= PhysicsConstants::COLLISION_EPSILON)
        );

        $movement->setAuthVelocity($collisionVel);
    }

    /**
     * Try to step up onto a block
     *
     * @param MovementComponent $movement
     * @param array<AxisAlignedBB> $bbList
     * @param Vector3 $vel
     * @param bool $useOneWayCollisions
     * @return array{bb: AxisAlignedBB, vel: Vector3}|null
     */
    private static function tryStepUp(
        MovementComponent $movement,
        array $bbList,
        Vector3 $vel,
        bool $useOneWayCollisions
    ): ?array {
        $stepBB = $movement->getBoundingBox();
        $penetration = new Vector3(0, 0, 0);

        // Step up
        $stepYVel = PhysicsConstants::STEP_HEIGHT;
        foreach ($bbList as $blockBB) {
            $stepYVel = self::clipCollide($blockBB, $stepBB, 0, $stepYVel, 0, $useOneWayCollisions, $penetration)->y;
        }
        $stepBB = self::offsetBB($stepBB, 0, $stepYVel, 0);

        // Move horizontally
        $stepXVel = $vel->x;
        foreach ($bbList as $blockBB) {
            $stepXVel = self::clipCollide($blockBB, $stepBB, $stepXVel, 0, 0, $useOneWayCollisions, $penetration)->x;
        }
        $stepBB = self::offsetBB($stepBB, $stepXVel, 0, 0);

        $stepZVel = $vel->z;
        foreach ($bbList as $blockBB) {
            $stepZVel = self::clipCollide($blockBB, $stepBB, 0, 0, $stepZVel, $useOneWayCollisions, $penetration)->z;
        }
        $stepBB = self::offsetBB($stepBB, 0, 0, $stepZVel);

        // Step down
        $inverseYStepVel = -$stepYVel;
        foreach ($bbList as $blockBB) {
            $inverseYStepVel = self::clipCollide($blockBB, $stepBB, 0, $inverseYStepVel, 0, $useOneWayCollisions, $penetration)->y;
        }
        $stepBB = self::offsetBB($stepBB, 0, $inverseYStepVel, 0);

        $stepVel = new Vector3($stepXVel, $stepYVel + $inverseYStepVel, $stepZVel);

        // Check if step is better than regular collision
        $regularHzDist = $vel->x * $vel->x + $vel->z * $vel->z;
        $stepHzDist = $stepVel->x * $stepVel->x + $stepVel->z * $stepVel->z;

        if ($stepHzDist > $regularHzDist) {
            return ['bb' => $stepBB, 'vel' => $stepVel];
        }

        return null;
    }

    /**
     * Prevent falling off edges while sneaking
     */
    private static function avoidEdge(MovementComponent $movement, World $world): void {
        if (!$movement->isSneaking() || !$movement->isOnGround()) {
            return;
        }

        $vel = clone $movement->getAuthVelocity();
        if ($vel->y > 0) {
            return;
        }

        $bb = $movement->getBoundingBox();
        $shrunkBB = new AxisAlignedBB(
            $bb->minX + PhysicsConstants::EDGE_BOUNDARY,
            $bb->minY,
            $bb->minZ + PhysicsConstants::EDGE_BOUNDARY,
            $bb->maxX - PhysicsConstants::EDGE_BOUNDARY,
            $bb->maxY,
            $bb->maxZ - PhysicsConstants::EDGE_BOUNDARY
        );

        $offset = PhysicsConstants::EDGE_OFFSET;
        $xMov = $vel->x;
        $zMov = $vel->z;

        // Adjust X movement
        while ($xMov !== 0.0) {
            $testBB = self::offsetBB($shrunkBB, $xMov, -PhysicsConstants::STEP_HEIGHT * 1.01, 0);
            if (count(self::getNearbyBoundingBoxes($world, $testBB)) > 0) {
                break;
            }

            if (abs($xMov) < $offset) {
                $xMov = 0.0;
            } elseif ($xMov > 0) {
                $xMov -= $offset;
            } else {
                $xMov += $offset;
            }
        }

        // Adjust Z movement
        while ($zMov !== 0.0) {
            $testBB = self::offsetBB($shrunkBB, 0, -PhysicsConstants::STEP_HEIGHT * 1.01, $zMov);
            if (count(self::getNearbyBoundingBoxes($world, $testBB)) > 0) {
                break;
            }

            if (abs($zMov) < $offset) {
                $zMov = 0.0;
            } elseif ($zMov > 0) {
                $zMov -= $offset;
            } else {
                $zMov += $offset;
            }
        }

        // Adjust both together
        while ($xMov !== 0.0 && $zMov !== 0.0) {
            $testBB = self::offsetBB($shrunkBB, $xMov, -PhysicsConstants::STEP_HEIGHT * 1.01, $zMov);
            if (count(self::getNearbyBoundingBoxes($world, $testBB)) > 0) {
                break;
            }

            if (abs($xMov) < $offset) {
                $xMov = 0;
            } elseif ($xMov > 0) {
                $xMov -= $offset;
            } else {
                $xMov += $offset;
            }

            if (abs($zMov) < $offset) {
                $zMov = 0;
            } elseif ($zMov > 0) {
                $zMov -= $offset;
            } else {
                $zMov += $offset;
            }
        }

        $vel->x = $xMov;
        $vel->z = $zMov;
        $movement->setAuthVelocity($vel);
    }

    /**
     * Check if simulation is reliable (not in water, flying, etc.)
     */
    private static function simulationIsReliable(OomphPlayer $player): bool {
        $movement = $player->getMovementComponent();

        if ($movement->getRemainingTeleportTicks() > 0) {
            return true;
        }

        // Check for liquids nearby
        $world = $player->getPlayer()->getWorld();
        $bb = $movement->getBoundingBox();
        $expandedBB = AABBUtils::expand($bb, 1.0);

        $minX = (int)floor($expandedBB->minX);
        $minY = (int)floor($expandedBB->minY);
        $minZ = (int)floor($expandedBB->minZ);
        $maxX = (int)floor($expandedBB->maxX);
        $maxY = (int)floor($expandedBB->maxY);
        $maxZ = (int)floor($expandedBB->maxZ);

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                for ($z = $minZ; $z <= $maxZ; $z++) {
                    $block = $world->getBlockAt($x, $y, $z);
                    if ($block instanceof Liquid) {
                        return false;
                    }
                }
            }
        }

        // Must be in survival/adventure and not flying
        $gameMode = $player->getGameMode();
        return !($movement->isFlying() || $movement->isNoClip());
    }

    /**
     * Apply post-collision effects (bouncing, landing, etc.)
     */
    private static function setPostCollisionMotion(
        OomphPlayer $player,
        Vector3 $oldVel,
        bool $oldOnGround,
        Block $blockUnder
    ): void {
        $movement = $player->getMovementComponent();

        // Landing on block
        if (!$oldOnGround && $movement->hasCollisionY()) {
            self::landOnBlock($movement, $oldVel, $blockUnder);
        } elseif ($movement->hasCollisionY()) {
            $vel = clone $movement->getAuthVelocity();
            $vel->y = 0;
            $movement->setAuthVelocity($vel);
        }

        // Cancel horizontal velocity on collision
        $vel = clone $movement->getAuthVelocity();
        if ($movement->hasCollisionX()) {
            $vel->x = 0;
        }
        if ($movement->hasCollisionZ()) {
            $vel->z = 0;
        }
        $movement->setAuthVelocity($vel);
    }

    /**
     * Handle landing on a block (bouncing, stopping, etc.)
     */
    private static function landOnBlock(MovementComponent $movement, Vector3 $oldVel, Block $blockUnder): void {
        $vel = clone $movement->getAuthVelocity();

        if ($oldVel->y >= 0 || $movement->isPressingSneak()) {
            $vel->y = 0;
            $movement->setAuthVelocity($vel);
            return;
        }

        $blockName = $blockUnder->getName();

        if ($blockName === "Slime Block") {
            $vel->y = PhysicsConstants::SLIME_BOUNCE_MULTIPLIER * $oldVel->y;
            if (abs($vel->y) < 1e-4) {
                $vel->y = 0.0;
            }
        } elseif ($blockName === "Bed") {
            $vel->y = min(1.0, PhysicsConstants::BED_BOUNCE_MULTIPLIER * $oldVel->y);
        } else {
            $vel->y = 0;
        }

        $movement->setAuthVelocity($vel);
    }

    /**
     * Simulate elytra gliding
     */
    private static function simulateGlide(OomphPlayer $player, MovementComponent $movement): void {
        $radians = M_PI / 180.0;
        $yaw = $movement->getYaw() * $radians;
        $pitch = $movement->getPitch() * $radians;

        $yawCos = Math::mcCos(-$yaw - M_PI);
        $yawSin = Math::mcSin(-$yaw - M_PI);
        $pitchCos = Math::mcCos($pitch);
        $pitchSin = Math::mcSin($pitch);

        $lookX = $yawSin * -$pitchCos;
        $lookY = -$pitchSin;
        $lookZ = $yawCos * -$pitchCos;

        $vel = clone $movement->getAuthVelocity();
        $velHz = sqrt($vel->x * $vel->x + $vel->z * $vel->z);
        $lookHz = $pitchCos;
        $sqrPitchCos = $pitchCos * $pitchCos;

        $vel->y += -0.08 + $sqrPitchCos * 0.06;

        if ($vel->y < 0 && $lookHz > 0) {
            $yAccel = $vel->y * -0.1 * $sqrPitchCos;
            $vel->y += $yAccel;
            $vel->x += $lookX * $yAccel / $lookHz;
            $vel->z += $lookZ * $yAccel / $lookHz;
        }

        if ($pitch < 0) {
            $yAccel = $velHz * -$pitchSin * 0.04;
            $vel->y += $yAccel * 3.2;
            $vel->x -= $lookX * $yAccel / $lookHz;
            $vel->z -= $lookZ * $yAccel / $lookHz;
        }

        if ($lookHz > 0) {
            $vel->x += ($lookX / $lookHz * $velHz - $vel->x) * 0.1;
            $vel->z += ($lookZ / $lookHz * $velHz - $vel->z) * 0.1;
        }

        // Apply glide boost (fireworks)
        if ($movement->getGlideBoost() > 0) {
            $vel->x += ($lookX * 0.1) + ((($lookX * 1.5) - $vel->x) * 0.5);
            $vel->y += ($lookY * 0.1) + ((($lookY * 1.5) - $vel->y) * 0.5);
            $vel->z += ($lookZ * 0.1) + ((($lookZ * 1.5) - $vel->z) * 0.5);
        }

        $vel->x *= 0.99;
        $vel->y *= 0.98;
        $vel->z *= 0.99;

        $movement->setAuthVelocity($vel);
    }

    // ========== HELPER METHODS ==========

    /**
     * Get block friction value
     */
    private static function getBlockFriction(Block $block): float {
        $name = $block->getName();

        return match ($name) {
            "Ice", "Packed Ice", "Blue Ice" => 0.98,
            "Slime Block" => 0.8,
            default => PhysicsConstants::DEFAULT_BLOCK_FRICTION
        };
    }

    /**
     * Check if block is climbable
     */
    private static function isClimbable(Block $block): bool {
        $name = $block->getName();
        return in_array($name, ["Ladder", "Vine", "Scaffolding", "Weeping Vines", "Twisting Vines"], true);
    }

    /**
     * Expand bounding box by velocity
     */
    private static function expandBBByVelocity(AxisAlignedBB $bb, Vector3 $vel): AxisAlignedBB {
        return new AxisAlignedBB(
            $bb->minX + min(0, $vel->x),
            $bb->minY + min(0, $vel->y),
            $bb->minZ + min(0, $vel->z),
            $bb->maxX + max(0, $vel->x),
            $bb->maxY + max(0, $vel->y),
            $bb->maxZ + max(0, $vel->z)
        );
    }

    /**
     * Offset a bounding box by x, y, z
     */
    private static function offsetBB(AxisAlignedBB $bb, float $x, float $y, float $z): AxisAlignedBB {
        return new AxisAlignedBB(
            $bb->minX + $x,
            $bb->minY + $y,
            $bb->minZ + $z,
            $bb->maxX + $x,
            $bb->maxY + $y,
            $bb->maxZ + $z
        );
    }

    /**
     * Get all block bounding boxes in the area
     *
     * @return array<AxisAlignedBB>
     */
    private static function getNearbyBoundingBoxes(World $world, AxisAlignedBB $bb): array {
        $boxes = [];

        $minX = (int)floor($bb->minX);
        $minY = (int)floor($bb->minY);
        $minZ = (int)floor($bb->minZ);
        $maxX = (int)floor($bb->maxX);
        $maxY = (int)floor($bb->maxY);
        $maxZ = (int)floor($bb->maxZ);

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                for ($z = $minZ; $z <= $maxZ; $z++) {
                    $block = $world->getBlockAt($x, $y, $z);

                    if (!($block instanceof Air) && $block->isSolid()) {
                        $boxes[] = new AxisAlignedBB(
                            $x,
                            $y,
                            $z,
                            $x + 1,
                            $y + 1,
                            $z + 1
                        );
                    }
                }
            }
        }

        return $boxes;
    }

    /**
     * Clip collision - calculate how much movement is allowed before hitting a box
     */
    private static function clipCollide(
        AxisAlignedBB $blockBB,
        AxisAlignedBB $entityBB,
        float $x,
        float $y,
        float $z,
        bool $useOneWay,
        Vector3 &$penetration
    ): Vector3 {
        // This is a simplified version - full implementation would match vanilla exactly
        if ($y !== 0.0) {
            if ($entityBB->minX < $blockBB->maxX && $entityBB->maxX > $blockBB->minX &&
                $entityBB->minZ < $blockBB->maxZ && $entityBB->maxZ > $blockBB->minZ) {

                if ($y > 0 && $entityBB->maxY <= $blockBB->minY) {
                    $diff = $blockBB->minY - $entityBB->maxY;
                    if ($diff < $y) {
                        $y = $diff;
                    }
                } elseif ($y < 0 && $entityBB->minY >= $blockBB->maxY) {
                    $diff = $blockBB->maxY - $entityBB->minY;
                    if ($diff > $y) {
                        $y = $diff;
                    }
                }
            }
        }

        if ($x !== 0.0) {
            if ($entityBB->minY < $blockBB->maxY && $entityBB->maxY > $blockBB->minY &&
                $entityBB->minZ < $blockBB->maxZ && $entityBB->maxZ > $blockBB->minZ) {

                if ($x > 0 && $entityBB->maxX <= $blockBB->minX) {
                    $diff = $blockBB->minX - $entityBB->maxX;
                    if ($diff < $x) {
                        $x = $diff;
                    }
                } elseif ($x < 0 && $entityBB->minX >= $blockBB->maxX) {
                    $diff = $blockBB->maxX - $entityBB->minX;
                    if ($diff > $x) {
                        $x = $diff;
                    }
                }
            }
        }

        if ($z !== 0.0) {
            if ($entityBB->minX < $blockBB->maxX && $entityBB->maxX > $blockBB->minX &&
                $entityBB->minY < $blockBB->maxY && $entityBB->maxY > $blockBB->minY) {

                if ($z > 0 && $entityBB->maxZ <= $blockBB->minZ) {
                    $diff = $blockBB->minZ - $entityBB->maxZ;
                    if ($diff < $z) {
                        $z = $diff;
                    }
                } elseif ($z < 0 && $entityBB->minZ >= $blockBB->maxZ) {
                    $diff = $blockBB->maxZ - $entityBB->minZ;
                    if ($diff > $z) {
                        $z = $diff;
                    }
                }
            }
        }

        return new Vector3($x, $y, $z);
    }
}
