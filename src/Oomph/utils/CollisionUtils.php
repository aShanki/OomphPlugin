<?php

declare(strict_types=1);

namespace Oomph\utils;

use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\Transparent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * Collision utilities for AABB clipping and collision detection
 * Ported from game/aabb.go
 */
final class CollisionUtils {
    /**
     * Get a component of a Vector3 by axis index (0=x, 1=y, 2=z)
     */
    private static function getComponent(Vector3 $vec, int $axis): float {
        return match($axis) {
            0 => $vec->x,
            1 => $vec->y,
            2 => $vec->z,
            default => 0.0
        };
    }

    /**
     * Create a new Vector3 with one component changed
     */
    private static function withComponent(Vector3 $vec, int $axis, float $value): Vector3 {
        return match($axis) {
            0 => new Vector3($value, $vec->y, $vec->z),
            1 => new Vector3($vec->x, $value, $vec->z),
            2 => new Vector3($vec->x, $vec->y, $value),
            default => $vec
        };
    }

    /**
     * Clip velocity against a stationary bounding box
     * Port of game.BBClipCollide from Go
     *
     * @param AxisAlignedBB $stationary The stationary collision box
     * @param AxisAlignedBB $moving The moving entity's box
     * @param Vector3 $velocity The velocity to clip
     * @param bool $oneWay Whether to use one-way collision
     * @param Vector3|null $penetration Output penetration depth (by reference)
     * @return Vector3 The clipped velocity
     */
    public static function clipCollide(
        AxisAlignedBB $stationary,
        AxisAlignedBB $moving,
        Vector3 $velocity,
        bool $oneWay = false,
        ?Vector3 &$penetration = null
    ): Vector3 {
        $result = self::doBBClipCollide($stationary, $moving, $velocity);

        if ($penetration !== null && self::getComponent($penetration, $result['depenetratingAxis']) < $result['penetration']) {
            $penetration = self::withComponent($penetration, $result['depenetratingAxis'], $result['penetration']);
        }

        if ($oneWay) {
            return $result['clippedVelocity'];
        }
        return $result['depenetratingVelocity'];
    }

    /**
     * Internal implementation of BBClipCollide
     * @param AxisAlignedBB $stationary
     * @param AxisAlignedBB $moving
     * @param Vector3 $velocity
     * @return array{depenetratingAxis: int, penetration: float, clippedVelocity: Vector3, depenetratingVelocity: Vector3}
     */
    private static function doBBClipCollide(
        AxisAlignedBB $stationary,
        AxisAlignedBB $moving,
        Vector3 $velocity
    ): array {
        $clippedVelocity = clone $velocity;
        $depenetratingVelocity = clone $velocity;
        $depenetratingAxis = 0;
        $penetration = 0.0;

        // Check if stationary box has zero volume
        if (self::bbHasZeroVolume($stationary)) {
            return [
                'depenetratingAxis' => $depenetratingAxis,
                'penetration' => $penetration,
                'clippedVelocity' => $clippedVelocity,
                'depenetratingVelocity' => $depenetratingVelocity
            ];
        }

        $axisPenetrations = [0.0, 0.0, 0.0];
        $axisPenetrationsSigned = [0.0, 0.0, 0.0];
        $normalDirs = [0.0, 0.0, 0.0];
        $separatingAxes = 0;
        $separatingAxis = 0;
        $resultPenetration = PHP_FLOAT_MAX - 1.0;

        // Get min/max for both boxes
        $movingMin = [$moving->minX, $moving->minY, $moving->minZ];
        $movingMax = [$moving->maxX, $moving->maxY, $moving->maxZ];
        $stationaryMin = [$stationary->minX, $stationary->minY, $stationary->minZ];
        $stationaryMax = [$stationary->maxX, $stationary->maxY, $stationary->maxZ];

        for ($i = 0; $i < 3; $i++) {
            $minPenetration = $movingMax[$i] - $stationaryMin[$i];
            $maxPenetration = $stationaryMax[$i] - $movingMin[$i];

            if (abs($minPenetration) <= 1e-7) {
                $minPenetration = 0.0;
            }
            if (abs($maxPenetration) <= 1e-7) {
                $maxPenetration = 0.0;
            }

            $minPositive = max(0.0, $minPenetration);
            $maxPositive = max(0.0, $maxPenetration);

            if ($minPositive === 0.0) {
                $axisPenetrations[$i] = 0.0;
                $axisPenetrationsSigned[$i] = $minPenetration;
                $normalDirs[$i] = -1.0;
                $separatingAxes++;
                $separatingAxis = $i;
            } elseif ($maxPositive === 0.0) {
                $axisPenetrations[$i] = 0.0;
                $axisPenetrationsSigned[$i] = $maxPenetration;
                $normalDirs[$i] = 1.0;
                $separatingAxes++;
                $separatingAxis = $i;
            } elseif ($minPositive < $maxPositive) {
                $axisPenetrations[$i] = $minPositive;
                $axisPenetrationsSigned[$i] = $minPositive;
                $normalDirs[$i] = -1.0;
            } else {
                $axisPenetrations[$i] = $maxPositive;
                $axisPenetrationsSigned[$i] = $maxPositive;
                $normalDirs[$i] = 1.0;
            }

            if ($separatingAxes > 1) {
                return [
                    'depenetratingAxis' => $depenetratingAxis,
                    'penetration' => $penetration,
                    'clippedVelocity' => $clippedVelocity,
                    'depenetratingVelocity' => $depenetratingVelocity
                ];
            }

            $resultPenetration = min($resultPenetration, $axisPenetrations[$i]);
        }

        // No separating axes means a collision
        if ($separatingAxes === 0) {
            $penetration = $resultPenetration;
            $bestAxis = 0;
            for ($i = 1; $i < 3; $i++) {
                if ($axisPenetrations[$i] < $axisPenetrations[$bestAxis]) {
                    $bestAxis = $i;
                }
            }

            $desiredVelocity = $axisPenetrations[$bestAxis] * $normalDirs[$bestAxis];
            $velComponent = self::getComponent($velocity, $bestAxis);

            if ($desiredVelocity > 0.0) {
                $depenetratingVelocity = self::withComponent(
                    $depenetratingVelocity,
                    $bestAxis,
                    max($desiredVelocity, $velComponent)
                );
            } else {
                $depenetratingVelocity = self::withComponent(
                    $depenetratingVelocity,
                    $bestAxis,
                    min($desiredVelocity, $velComponent)
                );
            }

            $depenetratingAxis = $bestAxis;
            return [
                'depenetratingAxis' => $depenetratingAxis,
                'penetration' => $penetration,
                'clippedVelocity' => $clippedVelocity,
                'depenetratingVelocity' => $depenetratingVelocity
            ];
        }

        $velComponent = self::getComponent($velocity, $separatingAxis);
        $sweptPenetration = $axisPenetrationsSigned[$separatingAxis] - ($normalDirs[$separatingAxis] * $velComponent);
        if ($sweptPenetration <= 0.0) {
            return [
                'depenetratingAxis' => $depenetratingAxis,
                'penetration' => $penetration,
                'clippedVelocity' => $clippedVelocity,
                'depenetratingVelocity' => $depenetratingVelocity
            ];
        }

        $resolvedVelocity = $axisPenetrationsSigned[$separatingAxis] * $normalDirs[$separatingAxis];
        $clippedVelocity = self::withComponent($clippedVelocity, $separatingAxis, $resolvedVelocity);
        $depenetratingVelocity = self::withComponent($depenetratingVelocity, $separatingAxis, $resolvedVelocity);

        return [
            'depenetratingAxis' => $depenetratingAxis,
            'penetration' => $penetration,
            'clippedVelocity' => $clippedVelocity,
            'depenetratingVelocity' => $depenetratingVelocity
        ];
    }

    /**
     * Check if a bounding box has zero volume
     * @param AxisAlignedBB $bb Bounding box
     * @return bool True if volume is zero
     */
    private static function bbHasZeroVolume(AxisAlignedBB $bb): bool {
        return $bb->minX === $bb->maxX && $bb->minY === $bb->maxY && $bb->minZ === $bb->maxZ;
    }

    /**
     * Get all block bounding boxes near a given AABB
     * @param AxisAlignedBB $bb The bounding box
     * @param World $world The world
     * @return AxisAlignedBB[] Array of block bounding boxes
     */
    public static function getNearbyBBoxes(AxisAlignedBB $bb, World $world): array {
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
                    if ($block instanceof Air) {
                        continue;
                    }

                    $blockBB = $block->getCollisionBoxes();
                    foreach ($blockBB as $blockBox) {
                        // Offset by block position
                        $offsetBox = new AxisAlignedBB(
                            $blockBox->minX + $x,
                            $blockBox->minY + $y,
                            $blockBox->minZ + $z,
                            $blockBox->maxX + $x,
                            $blockBox->maxY + $y,
                            $blockBox->maxZ + $z
                        );
                        $boxes[] = $offsetBox;
                    }
                }
            }
        }

        return $boxes;
    }

    /**
     * Get the closest point on an AABB to a given point
     * Ported from game.ClosestPointToBBox
     * @param AxisAlignedBB $bb Bounding box
     * @param Vector3 $point Point
     * @return Vector3 Closest point on the AABB
     */
    public static function closestPointOnAABB(AxisAlignedBB $bb, Vector3 $point): Vector3 {
        $x = $point->x;
        $y = $point->y;
        $z = $point->z;

        if ($x < $bb->minX) {
            $x = $bb->minX;
        } elseif ($x > $bb->maxX) {
            $x = $bb->maxX;
        }

        if ($y < $bb->minY) {
            $y = $bb->minY;
        } elseif ($y > $bb->maxY) {
            $y = $bb->maxY;
        }

        if ($z < $bb->minZ) {
            $z = $bb->minZ;
        } elseif ($z > $bb->maxZ) {
            $z = $bb->maxZ;
        }

        return new Vector3($x, $y, $z);
    }

    /**
     * Check if a block is passable for interaction (not solid for raycasting)
     * @param Block $block Block to check
     * @return bool True if passable
     */
    public static function isBlockPassInteraction(Block $block): bool {
        return $block instanceof Air ||
               $block instanceof Liquid ||
               $block instanceof Transparent;
    }

    /**
     * Expand an AABB by a given amount on all axes
     * @param AxisAlignedBB $bb Bounding box
     * @param float $amount Amount to expand
     * @return AxisAlignedBB Expanded AABB
     */
    public static function expandAABB(AxisAlignedBB $bb, float $amount): AxisAlignedBB {
        return new AxisAlignedBB(
            $bb->minX - $amount,
            $bb->minY - $amount,
            $bb->minZ - $amount,
            $bb->maxX + $amount,
            $bb->maxY + $amount,
            $bb->maxZ + $amount
        );
    }

    /**
     * Check if a point is inside an AABB
     * @param AxisAlignedBB $bb Bounding box
     * @param Vector3 $point Point
     * @return bool True if inside
     */
    public static function pointInAABB(AxisAlignedBB $bb, Vector3 $point): bool {
        return $point->x >= $bb->minX && $point->x <= $bb->maxX &&
               $point->y >= $bb->minY && $point->y <= $bb->maxY &&
               $point->z >= $bb->minZ && $point->z <= $bb->maxZ;
    }

    /**
     * Calculate the distance from a point to an AABB
     * Ported from game.AABBVectorDistance
     * @param AxisAlignedBB $bb Bounding box
     * @param Vector3 $point Point
     * @return float Distance
     */
    public static function distanceToAABB(AxisAlignedBB $bb, Vector3 $point): float {
        $x = max($bb->minX - $point->x, max(0.0, $point->x - $bb->maxX));
        $y = max($bb->minY - $point->y, max(0.0, $point->y - $bb->maxY));
        $z = max($bb->minZ - $point->z, max(0.0, $point->z - $bb->maxZ));

        $dist = sqrt($x * $x + $y * $y + $z * $z);
        if (is_nan($dist)) {
            $dist = 0.0;
        }

        return $dist;
    }

    /**
     * Get the center of a bounding box
     * Ported from game.BBoxCenter
     * @param AxisAlignedBB $bb Bounding box
     * @return Vector3 Center point
     */
    public static function getCenter(AxisAlignedBB $bb): Vector3 {
        return new Vector3(
            ($bb->minX + $bb->maxX) * 0.5,
            ($bb->minY + $bb->maxY) * 0.5,
            ($bb->minZ + $bb->maxZ) * 0.5
        );
    }

    /**
     * Get all corner and center points of a bounding box
     * Ported from game.BBoxPoints
     * @param AxisAlignedBB $bb Bounding box
     * @return Vector3[] Array of 9 points (8 corners + center)
     */
    public static function getBBoxPoints(AxisAlignedBB $bb): array {
        $min = new Vector3($bb->minX, $bb->minY, $bb->minZ);
        $max = new Vector3($bb->maxX, $bb->maxY, $bb->maxZ);
        $center = self::getCenter($bb);

        return [
            $min,                                                    // 0: min
            new Vector3($min->x, $min->y, $max->z),                 // 1: min.x, min.y, max.z
            new Vector3($min->x, $max->y, $min->z),                 // 2: min.x, max.y, min.z
            new Vector3($min->x, $max->y, $max->z),                 // 3: min.x, max.y, max.z
            new Vector3($max->x, $min->y, $min->z),                 // 4: max.x, min.y, min.z
            new Vector3($max->x, $min->y, $max->z),                 // 5: max.x, min.y, max.z
            new Vector3($max->x, $max->y, $min->z),                 // 6: max.x, max.y, min.z
            $max,                                                    // 7: max
            $center                                                  // 8: center
        ];
    }

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {}
}
