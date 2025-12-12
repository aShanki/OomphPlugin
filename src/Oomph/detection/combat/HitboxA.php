<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;

/**
 * HitboxA Detection
 *
 * Client hitbox validation using Interact packet. Compares the client-reported
 * interaction position against the expected entity hitbox position. Detects
 * clients with expanded hitboxes.
 *
 * Requires: EnableClientEntityTracking=true
 */
class HitboxA extends Detection {

    // Distance threshold - flag if reported position is this far from expected hitbox
    private const DISTANCE_THRESHOLD = 0.004;

    public function __construct() {
        // MaxViolations: 10
        // FailBuffer: 6, MaxBuffer: 6
        parent::__construct(
            maxBuffer: 6.0,
            failBuffer: 6.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "HitboxA";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 10.0;
    }

    /**
     * Check if client-reported interaction position matches expected entity hitbox
     *
     * @param OomphPlayer $player The player who sent the Interact packet
     * @param Entity $target The target entity
     * @param Vector3 $clientReportedPosition Position reported by client in Interact packet
     */
    public function check(OomphPlayer $player, Entity $target, Vector3 $clientReportedPosition): void {
        // Get expected entity bounding box from server-side tracking
        $expectedBB = $target->getBoundingBox();

        // Calculate closest point on expected AABB to client-reported position
        $closestPoint = $this->getClosestPointOnAABB($clientReportedPosition, $expectedBB);

        // Calculate distance from reported position to expected hitbox
        $distance = $clientReportedPosition->distance($closestPoint);

        // Check if distance exceeds threshold
        if ($distance > self::DISTANCE_THRESHOLD) {
            // Client reporting hits outside actual hitbox
            $this->fail($player);
        } else {
            // Valid hitbox interaction
            $this->pass();
        }
    }

    /**
     * Get closest point on AABB to a given point
     */
    private function getClosestPointOnAABB(Vector3 $point, AxisAlignedBB $aabb): Vector3 {
        return new Vector3(
            max($aabb->getMinX(), min($point->x, $aabb->getMaxX())),
            max($aabb->getMinY(), min($point->y, $aabb->getMaxY())),
            max($aabb->getMinZ(), min($point->z, $aabb->getMaxZ()))
        );
    }

    /**
     * Check if point is inside AABB
     */
    private function isPointInAABB(Vector3 $point, AxisAlignedBB $aabb): bool {
        return $point->x >= $aabb->getMinX() && $point->x <= $aabb->getMaxX() &&
               $point->y >= $aabb->getMinY() && $point->y <= $aabb->getMaxY() &&
               $point->z >= $aabb->getMinZ() && $point->z <= $aabb->getMaxZ();
    }
}
