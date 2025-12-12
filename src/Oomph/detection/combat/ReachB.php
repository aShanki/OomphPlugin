<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;

/**
 * ReachB Detection
 *
 * Closest point distance validation. Calculates the distance from the attacker's
 * eye position to the closest point on the target's bounding box. Simpler and
 * faster than raycast method but slightly less accurate.
 */
class ReachB extends Detection {

    // Reach threshold
    private const MIN_REACH_THRESHOLD = 2.9;

    // Interpolation steps for lag compensation
    private const LERP_STEPS = 20;

    private float $minReach = 999.0;

    public function __construct() {
        // MaxViolations: 15
        // FailBuffer: 1.01, MaxBuffer: 3
        // TrustDuration: 20 ticks
        parent::__construct(
            maxBuffer: 3.0,
            failBuffer: 1.01,
            trustDuration: 20
        );
    }

    public function getName(): string {
        return "ReachB";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 15.0;
    }

    /**
     * Check if attack reach is legitimate using closest point method
     *
     * @param OomphPlayer $player The attacking player
     * @param Entity $target The target entity
     */
    public function check(OomphPlayer $player, Entity $target): void {
        // Get attacker's eye position
        $attackerPos = $player->getPlayer()->getPosition();
        $eyeHeight = $player->getPlayer()->getEyeHeight();
        $eyePos = $attackerPos->add(0, $eyeHeight, 0);

        // Get target's current position
        $currentPos = $target->getPosition();

        // Try to get previous position from entity tracker for lag compensation
        $prevPos = $currentPos;
        $entityTracker = $player->getCombatComponent()->getEntityTracker();
        $trackedEntity = $entityTracker->getEntity($target->getId());
        if ($trackedEntity !== null) {
            $history = $trackedEntity->getPositionHistory();
            if (count($history) >= 2) {
                // Get second-to-last position for interpolation
                $prevPos = $history[count($history) - 2]->position;
            }
        }

        // Get target's bounding box dimensions
        $targetBB = $target->getBoundingBox();
        $width = $targetBB->maxX - $targetBB->minX;
        $height = $targetBB->maxY - $targetBB->minY;
        $depth = $targetBB->maxZ - $targetBB->minZ;

        $halfWidth = $width / 2;
        $halfDepth = $depth / 2;

        // Calculate minimum distance across all interpolation steps
        $this->minReach = 999.0;

        for ($i = 0; $i <= self::LERP_STEPS; $i++) {
            $t = $i / self::LERP_STEPS;

            // Interpolate position
            $lerpedPos = $this->lerp($prevPos, $currentPos, $t);

            // Create bounding box at interpolated position
            $interpolatedBB = new AxisAlignedBB(
                $lerpedPos->x - $halfWidth,
                $lerpedPos->y,
                $lerpedPos->z - $halfDepth,
                $lerpedPos->x + $halfWidth,
                $lerpedPos->y + $height,
                $lerpedPos->z + $halfDepth
            );

            // Calculate closest point on AABB to eye position
            $closestPoint = $this->getClosestPointOnAABB($eyePos, $interpolatedBB);

            // Calculate distance to closest point
            $distance = $eyePos->distance($closestPoint);

            // Track minimum reach
            $this->minReach = min($this->minReach, $distance);
        }

        // Check if reach exceeds threshold
        if ($this->minReach > self::MIN_REACH_THRESHOLD) {
            // Attack exceeds legitimate reach
            $this->fail($player);
        } else {
            // Legitimate reach
            $this->pass();
        }
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
     * Get closest point on AABB to a given point
     */
    private function getClosestPointOnAABB(Vector3 $point, AxisAlignedBB $aabb): Vector3 {
        return new Vector3(
            max($aabb->minX, min($point->x, $aabb->maxX)),
            max($aabb->minY, min($point->y, $aabb->maxY)),
            max($aabb->minZ, min($point->z, $aabb->maxZ))
        );
    }

    /**
     * Get last calculated min reach for debugging
     */
    public function getLastMinReach(): float {
        return $this->minReach;
    }
}
