<?php

declare(strict_types=1);

namespace Oomph\utils;

use pocketmine\math\Vector3;

/**
 * Static math utility class for anticheat calculations
 */
class Math {
    /**
     * Interpolate between two rotation angles with proper wrapping
     * @param float $from Starting angle in degrees
     * @param float $to Ending angle in degrees
     * @param float $t Interpolation factor (0.0 to 1.0)
     * @return float Interpolated angle
     */
    public static function lerpRotation(float $from, float $to, float $t): float {
        $delta = self::wrapDegrees($to - $from);
        return $from + $delta * $t;
    }

    /**
     * Wrap degrees to [-180, 180] range
     * @param float $degrees Angle in degrees
     * @return float Wrapped angle
     */
    public static function wrapDegrees(float $degrees): float {
        $wrapped = fmod($degrees, 360.0);
        if ($wrapped > 180.0) {
            $wrapped -= 360.0;
        } elseif ($wrapped < -180.0) {
            $wrapped += 360.0;
        }
        return $wrapped;
    }

    /**
     * Round a value to a specific number of decimal places
     * @param float $value Value to round
     * @param int $precision Number of decimal places
     * @return float Rounded value
     */
    public static function roundTo(float $value, int $precision): float {
        $multiplier = 10 ** $precision;
        return round($value * $multiplier) / $multiplier;
    }

    /**
     * Get direction vector from yaw and pitch angles
     * @param float $yaw Yaw angle in degrees
     * @param float $pitch Pitch angle in degrees
     * @return Vector3 Normalized direction vector
     */
    public static function directionVector(float $yaw, float $pitch): Vector3 {
        $yawRad = deg2rad($yaw);
        $pitchRad = deg2rad($pitch);

        $x = -sin($yawRad) * cos($pitchRad);
        $y = -sin($pitchRad);
        $z = cos($yawRad) * cos($pitchRad);

        return new Vector3($x, $y, $z);
    }

    /**
     * Compare two floats with epsilon tolerance
     * @param float $a First value
     * @param float $b Second value
     * @param float $epsilon Tolerance (default 1e-5)
     * @return bool True if approximately equal
     */
    public static function floatApproxEq(float $a, float $b, float $epsilon = 1e-5): bool {
        return abs($a - $b) < $epsilon;
    }

    /**
     * Clamp a value between min and max
     * @param float $value Value to clamp
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @return float Clamped value
     */
    public static function clamp(float $value, float $min, float $max): float {
        return max($min, min($max, $value));
    }

    /**
     * Calculate the squared distance between two points (faster than distance)
     * @param Vector3 $a First point
     * @param Vector3 $b Second point
     * @return float Squared distance
     */
    public static function distanceSquared(Vector3 $a, Vector3 $b): float {
        $dx = $a->x - $b->x;
        $dy = $a->y - $b->y;
        $dz = $a->z - $b->z;
        return $dx * $dx + $dy * $dy + $dz * $dz;
    }

    /**
     * Calculate the horizontal distance between two points
     * @param Vector3 $a First point
     * @param Vector3 $b Second point
     * @return float Horizontal distance
     */
    public static function horizontalDistance(Vector3 $a, Vector3 $b): float {
        $dx = $a->x - $b->x;
        $dz = $a->z - $b->z;
        return sqrt($dx * $dx + $dz * $dz);
    }

    /**
     * Calculate the horizontal squared distance between two points
     * @param Vector3 $a First point
     * @param Vector3 $b Second point
     * @return float Horizontal squared distance
     */
    public static function horizontalDistanceSquared(Vector3 $a, Vector3 $b): float {
        $dx = $a->x - $b->x;
        $dz = $a->z - $b->z;
        return $dx * $dx + $dz * $dz;
    }
}
