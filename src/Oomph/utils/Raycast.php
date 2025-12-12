<?php

declare(strict_types=1);

namespace Oomph\utils;

use pocketmine\math\Vector3;

/**
 * DDA voxel raytracing implementation
 */
class Raycast {
    /**
     * Get all block positions between two points using DDA algorithm
     * @param Vector3 $start Starting position
     * @param Vector3 $end Ending position
     * @return array<Vector3> Array of block positions
     */
    public static function blocksBetween(Vector3 $start, Vector3 $end): array {
        $blocks = [];

        // Current voxel coordinates
        $x = (int)floor($start->x);
        $y = (int)floor($start->y);
        $z = (int)floor($start->z);

        // Target voxel coordinates
        $endX = (int)floor($end->x);
        $endY = (int)floor($end->y);
        $endZ = (int)floor($end->z);

        // Direction of ray
        $dx = $end->x - $start->x;
        $dy = $end->y - $start->y;
        $dz = $end->z - $start->z;

        // Step direction
        $stepX = $dx > 0 ? 1 : ($dx < 0 ? -1 : 0);
        $stepY = $dy > 0 ? 1 : ($dy < 0 ? -1 : 0);
        $stepZ = $dz > 0 ? 1 : ($dz < 0 ? -1 : 0);

        // Distance to next voxel boundary
        $tMaxX = $dx != 0 ? self::calculateInitialT($start->x, $stepX, $dx) : PHP_FLOAT_MAX;
        $tMaxY = $dy != 0 ? self::calculateInitialT($start->y, $stepY, $dy) : PHP_FLOAT_MAX;
        $tMaxZ = $dz != 0 ? self::calculateInitialT($start->z, $stepZ, $dz) : PHP_FLOAT_MAX;

        // Distance to traverse one voxel along each axis
        $tDeltaX = $dx != 0 ? abs(1.0 / $dx) : PHP_FLOAT_MAX;
        $tDeltaY = $dy != 0 ? abs(1.0 / $dy) : PHP_FLOAT_MAX;
        $tDeltaZ = $dz != 0 ? abs(1.0 / $dz) : PHP_FLOAT_MAX;

        // Add starting block
        $blocks[] = new Vector3($x, $y, $z);

        // Maximum iterations to prevent infinite loops
        $maxIterations = 200;
        $iterations = 0;

        // DDA traversal
        while ($iterations < $maxIterations) {
            $iterations++;

            // Check if we've reached the end voxel
            if ($x === $endX && $y === $endY && $z === $endZ) {
                break;
            }

            // Step to next voxel boundary
            if ($tMaxX < $tMaxY) {
                if ($tMaxX < $tMaxZ) {
                    $x += $stepX;
                    $tMaxX += $tDeltaX;
                } else {
                    $z += $stepZ;
                    $tMaxZ += $tDeltaZ;
                }
            } else {
                if ($tMaxY < $tMaxZ) {
                    $y += $stepY;
                    $tMaxY += $tDeltaY;
                } else {
                    $z += $stepZ;
                    $tMaxZ += $tDeltaZ;
                }
            }

            $blocks[] = new Vector3($x, $y, $z);

            // Break if we've gone too far past the target
            if (abs($x - $endX) > 1 && $stepX != 0 && (($x > $endX && $stepX > 0) || ($x < $endX && $stepX < 0))) {
                break;
            }
            if (abs($y - $endY) > 1 && $stepY != 0 && (($y > $endY && $stepY > 0) || ($y < $endY && $stepY < 0))) {
                break;
            }
            if (abs($z - $endZ) > 1 && $stepZ != 0 && (($z > $endZ && $stepZ > 0) || ($z < $endZ && $stepZ < 0))) {
                break;
            }
        }

        return $blocks;
    }

    /**
     * Calculate initial t value for DDA algorithm
     * @param float $pos Current position on axis
     * @param int $step Step direction
     * @param float $delta Direction component
     * @return float Initial t value
     */
    private static function calculateInitialT(float $pos, int $step, float $delta): float {
        if ($step > 0) {
            return (ceil($pos) - $pos) / $delta;
        } elseif ($step < 0) {
            return (floor($pos) - $pos) / $delta;
        }
        return PHP_FLOAT_MAX;
    }

    /**
     * Simple raycast to check if there's a clear line of sight between two points
     * Uses block positions only (no sub-block precision)
     * @param Vector3 $start Starting position
     * @param Vector3 $end Ending position
     * @param callable $blockCheck Callback that receives Vector3 and returns true if blocked
     * @return bool True if line of sight is clear
     */
    public static function hasLineOfSight(Vector3 $start, Vector3 $end, callable $blockCheck): bool {
        $blocks = self::blocksBetween($start, $end);

        foreach ($blocks as $blockPos) {
            if ($blockCheck($blockPos)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find the first blocking block between two points
     * @param Vector3 $start Starting position
     * @param Vector3 $end Ending position
     * @param callable $blockCheck Callback that receives Vector3 and returns true if blocked
     * @return Vector3|null The first blocking block position, or null if none
     */
    public static function firstBlockingBlock(Vector3 $start, Vector3 $end, callable $blockCheck): ?Vector3 {
        $blocks = self::blocksBetween($start, $end);

        foreach ($blocks as $blockPos) {
            if ($blockCheck($blockPos)) {
                return $blockPos;
            }
        }

        return null;
    }
}
