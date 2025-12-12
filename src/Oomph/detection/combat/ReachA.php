<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;

/**
 * ReachA Detection
 *
 * Raycast-based reach validation. Performs raycasting from player's eye position
 * toward their look direction, testing against entity's historical positions
 * (lag compensation). Flags if attack distance exceeds legitimate reach.
 */
class ReachA extends Detection {

    // Reach thresholds
    private const MIN_REACH_THRESHOLD = 2.9;
    private const MAX_REACH_THRESHOLD = 3.0;

    // Interpolation steps for lag compensation
    private const LERP_STEPS = 20;

    // Skip checks for this many ticks after teleport
    private const TICKS_AFTER_TELEPORT = 20;

    // Input mode constants
    private const INPUT_MODE_TOUCH = 0;

    private int $ticksSinceTeleport = 999;
    private float $minReach = 999.0;
    private float $maxReach = 0.0;

    public function __construct() {
        // MaxViolations: 7
        // FailBuffer: 1.01, MaxBuffer: 1.5
        // TrustDuration: 60 ticks
        parent::__construct(
            maxBuffer: 1.5,
            failBuffer: 1.01,
            trustDuration: 60
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
     * Reset teleport counter
     */
    public function onTeleport(): void {
        $this->ticksSinceTeleport = 0;
    }

    /**
     * Increment ticks since teleport
     */
    public function tick(): void {
        if ($this->ticksSinceTeleport < self::TICKS_AFTER_TELEPORT) {
            $this->ticksSinceTeleport++;
        }
    }

    /**
     * Check if attack reach is legitimate using raycast method
     *
     * @param OomphPlayer $player The attacking player
     * @param Entity $target The target entity
     * @param int $inputMode Player's input mode
     */
    public function check(OomphPlayer $player, Entity $target, int $inputMode): void {
        // Skip touch clients (too many false positives due to mobile quirks)
        if ($inputMode === self::INPUT_MODE_TOUCH) {
            return;
        }

        // Skip first 20 ticks after teleport
        if ($this->ticksSinceTeleport < self::TICKS_AFTER_TELEPORT) {
            return;
        }

        // Get attacker's eye position
        $attackerPos = $player->getPlayer()->getPosition();
        $eyeHeight = $player->getPlayer()->getEyeHeight();
        $eyePos = $attackerPos->add(0, $eyeHeight, 0);

        // Get attacker's look direction
        $yaw = $player->getMovementComponent()->getYaw();
        $pitch = $player->getMovementComponent()->getPitch();
        $direction = $this->getDirectionVector($yaw, $pitch);

        // Get target's current and previous position for interpolation
        $currentPos = $target->getPosition();
        $prevPos = $target->getLastPosition() ?? $currentPos;

        // Get target's bounding box dimensions
        $targetBB = $target->getBoundingBox();

        // Perform raycast at multiple interpolation steps (lag compensation)
        $this->minReach = 999.0;
        $this->maxReach = 0.0;

        for ($i = 0; $i <= self::LERP_STEPS; $i++) {
            $t = $i / self::LERP_STEPS;

            // Interpolate position
            $lerpedPos = $this->lerp($prevPos, $currentPos, $t);

            // Create bounding box at interpolated position
            $width = $targetBB->getMaxX() - $targetBB->getMinX();
            $height = $targetBB->getMaxY() - $targetBB->getMinY();
            $depth = $targetBB->getMaxZ() - $targetBB->getMinZ();

            $halfWidth = $width / 2;
            $halfDepth = $depth / 2;

            $interpolatedBB = new AxisAlignedBB(
                $lerpedPos->x - $halfWidth,
                $lerpedPos->y,
                $lerpedPos->z - $halfDepth,
                $lerpedPos->x + $halfWidth,
                $lerpedPos->y + $height,
                $lerpedPos->z + $halfDepth
            );

            // Perform raycast
            $distance = $this->raycastToAABB($eyePos, $direction, $interpolatedBB);

            if ($distance !== null) {
                $this->minReach = min($this->minReach, $distance);
                $this->maxReach = max($this->maxReach, $distance);
            }
        }

        // Check if reach exceeds thresholds
        if ($this->minReach > self::MIN_REACH_THRESHOLD && $this->maxReach > self::MAX_REACH_THRESHOLD) {
            // Attack exceeds legitimate reach
            $this->fail($player);
        } else {
            // Legitimate reach
            $this->pass();
        }
    }

    /**
     * Calculate direction vector from yaw and pitch
     */
    private function getDirectionVector(float $yaw, float $pitch): Vector3 {
        $yawRad = deg2rad($yaw);
        $pitchRad = deg2rad($pitch);

        return new Vector3(
            -sin($yawRad) * cos($pitchRad),
            -sin($pitchRad),
            cos($yawRad) * cos($pitchRad)
        );
    }

    /**
     * Linear interpolation between two positions
     */
    private function lerp(Vector3 $from, Vector3 $to, float $t): Vector3 {
        return new Vector3(
            $from->x + ($to->x - $from->x) * $t,
            $from->y + ($to->y - $from->y) * $t,
            $from->z + ($to->z - $from->z) * $t
        );
    }

    /**
     * Raycast from origin in direction to check intersection with AABB
     * Returns distance to intersection or null if no intersection
     */
    private function raycastToAABB(Vector3 $origin, Vector3 $direction, AxisAlignedBB $aabb): ?float {
        // Ray-AABB intersection algorithm
        $invDirX = $direction->x != 0 ? 1.0 / $direction->x : PHP_FLOAT_MAX;
        $invDirY = $direction->y != 0 ? 1.0 / $direction->y : PHP_FLOAT_MAX;
        $invDirZ = $direction->z != 0 ? 1.0 / $direction->z : PHP_FLOAT_MAX;

        $tx1 = ($aabb->getMinX() - $origin->x) * $invDirX;
        $tx2 = ($aabb->getMaxX() - $origin->x) * $invDirX;
        $ty1 = ($aabb->getMinY() - $origin->y) * $invDirY;
        $ty2 = ($aabb->getMaxY() - $origin->y) * $invDirY;
        $tz1 = ($aabb->getMinZ() - $origin->z) * $invDirZ;
        $tz2 = ($aabb->getMaxZ() - $origin->z) * $invDirZ;

        $tmin = max(max(min($tx1, $tx2), min($ty1, $ty2)), min($tz1, $tz2));
        $tmax = min(min(max($tx1, $tx2), max($ty1, $ty2)), max($tz1, $tz2));

        // No intersection if tmax < 0 or tmin > tmax
        if ($tmax < 0 || $tmin > $tmax) {
            return null;
        }

        // Calculate distance
        $t = $tmin >= 0 ? $tmin : $tmax;
        return $t * $direction->length();
    }

    /**
     * Get last calculated min reach for debugging
     */
    public function getLastMinReach(): float {
        return $this->minReach;
    }

    /**
     * Get last calculated max reach for debugging
     */
    public function getLastMaxReach(): float {
        return $this->maxReach;
    }
}
