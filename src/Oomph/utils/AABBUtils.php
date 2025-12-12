<?php

declare(strict_types=1);

namespace Oomph\utils;

use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

/**
 * AABB utility functions extending PocketMine's AxisAlignedBB
 */
class AABBUtils {
    /**
     * Find the closest point on an AABB to a given point
     * @param AxisAlignedBB $box The bounding box
     * @param Vector3 $point The reference point
     * @return Vector3 The closest point on the AABB
     */
    public static function closestPointOnAABB(AxisAlignedBB $box, Vector3 $point): Vector3 {
        $x = Math::clamp($point->x, $box->minX, $box->maxX);
        $y = Math::clamp($point->y, $box->minY, $box->maxY);
        $z = Math::clamp($point->z, $box->minZ, $box->maxZ);

        return new Vector3($x, $y, $z);
    }

    /**
     * Calculate the minimum distance from a point to an AABB
     * @param AxisAlignedBB $box The bounding box
     * @param Vector3 $point The reference point
     * @return float The distance
     */
    public static function distanceToAABB(AxisAlignedBB $box, Vector3 $point): float {
        $closestPoint = self::closestPointOnAABB($box, $point);
        return $closestPoint->distance($point);
    }

    /**
     * Check if a ray intersects with an AABB and return the distance to intersection
     * @param Vector3 $origin Ray origin
     * @param Vector3 $direction Ray direction (should be normalized)
     * @param AxisAlignedBB $box The bounding box
     * @return float|null Distance to intersection, or null if no intersection
     */
    public static function rayIntersectsAABB(Vector3 $origin, Vector3 $direction, AxisAlignedBB $box): ?float {
        $dirFracX = $direction->x == 0.0 ? 1e10 : 1.0 / $direction->x;
        $dirFracY = $direction->y == 0.0 ? 1e10 : 1.0 / $direction->y;
        $dirFracZ = $direction->z == 0.0 ? 1e10 : 1.0 / $direction->z;

        $t1 = ($box->minX - $origin->x) * $dirFracX;
        $t2 = ($box->maxX - $origin->x) * $dirFracX;
        $t3 = ($box->minY - $origin->y) * $dirFracY;
        $t4 = ($box->maxY - $origin->y) * $dirFracY;
        $t5 = ($box->minZ - $origin->z) * $dirFracZ;
        $t6 = ($box->maxZ - $origin->z) * $dirFracZ;

        $tMin = max(max(min($t1, $t2), min($t3, $t4)), min($t5, $t6));
        $tMax = min(min(max($t1, $t2), max($t3, $t4)), max($t5, $t6));

        // Ray is intersecting AABB, but whole AABB is behind us
        if ($tMax < 0.0) {
            return null;
        }

        // Ray doesn't intersect AABB
        if ($tMin > $tMax) {
            return null;
        }

        // Return distance to intersection
        return $tMin < 0.0 ? $tMax : $tMin;
    }

    /**
     * Expand an AABB by a given amount in all directions
     * @param AxisAlignedBB $box The bounding box
     * @param float $amount Amount to expand
     * @return AxisAlignedBB Expanded AABB
     */
    public static function expand(AxisAlignedBB $box, float $amount): AxisAlignedBB {
        return new AxisAlignedBB(
            $box->minX - $amount,
            $box->minY - $amount,
            $box->minZ - $amount,
            $box->maxX + $amount,
            $box->maxY + $amount,
            $box->maxZ + $amount
        );
    }

    /**
     * Contract an AABB by a given amount in all directions
     * @param AxisAlignedBB $box The bounding box
     * @param float $amount Amount to contract
     * @return AxisAlignedBB Contracted AABB
     */
    public static function contract(AxisAlignedBB $box, float $amount): AxisAlignedBB {
        return self::expand($box, -$amount);
    }

    /**
     * Check if two AABBs intersect
     * @param AxisAlignedBB $a First bounding box
     * @param AxisAlignedBB $b Second bounding box
     * @return bool True if they intersect
     */
    public static function intersects(AxisAlignedBB $a, AxisAlignedBB $b): bool {
        return $a->minX < $b->maxX && $a->maxX > $b->minX &&
               $a->minY < $b->maxY && $a->maxY > $b->minY &&
               $a->minZ < $b->maxZ && $a->maxZ > $b->minZ;
    }

    /**
     * Get the center point of an AABB
     * @param AxisAlignedBB $box The bounding box
     * @return Vector3 Center point
     */
    public static function center(AxisAlignedBB $box): Vector3 {
        return new Vector3(
            ($box->minX + $box->maxX) / 2.0,
            ($box->minY + $box->maxY) / 2.0,
            ($box->minZ + $box->maxZ) / 2.0
        );
    }

    /**
     * Get the volume of an AABB
     * @param AxisAlignedBB $box The bounding box
     * @return float Volume
     */
    public static function volume(AxisAlignedBB $box): float {
        return ($box->maxX - $box->minX) *
               ($box->maxY - $box->minY) *
               ($box->maxZ - $box->minZ);
    }
}
