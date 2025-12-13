<?php

declare(strict_types=1);

namespace Oomph\utils;

use pocketmine\math\Vector3;

/**
 * Minecraft-accurate math functions using lookup tables
 * Ported from game/minecraft.go and game/math.go
 */
final class MinecraftMath {
    /**
     * @var float[]|null Sin lookup table (65536 entries matching Minecraft)
     */
    private static ?array $sinTable = null;

    /**
     * Initialize the sin lookup table (65536 entries like Minecraft)
     * This is automatically called on first use
     */
    public static function init(): void {
        if (self::$sinTable !== null) {
            return;
        }

        self::$sinTable = [];
        for ($i = 0; $i < 65536; $i++) {
            self::$sinTable[$i] = sin($i * M_PI * 2.0 / 65536.0);
        }
    }

    /**
     * Minecraft-accurate sin using lookup table
     * Ported from game.MCSin
     * @param float $val Angle in radians
     * @return float Sin value
     */
    public static function mcSin(float $val): float {
        $table = self::getSinTable();
        $index = ((int)($val * 10430.378)) & 65535;
        return $table[$index];
    }

    /**
     * Minecraft-accurate cos using lookup table
     * Ported from game.MCCos
     * @param float $val Angle in radians
     * @return float Cos value
     */
    public static function mcCos(float $val): float {
        $table = self::getSinTable();
        $index = ((int)($val * 10430.378 + 16384.0)) & 65535;
        return $table[$index];
    }

    /**
     * Get the sin table, initializing if needed
     * @return array<int, float>
     */
    private static function getSinTable(): array {
        if (self::$sinTable === null) {
            self::init();
        }
        /** @var array<int, float> $sinTable */
        $sinTable = self::$sinTable;
        return $sinTable;
    }

    /**
     * Get direction vector from yaw and pitch (in degrees)
     * Ported from game.DirectionVector
     * @param float $yaw Yaw angle in degrees
     * @param float $pitch Pitch angle in degrees
     * @return Vector3 Direction vector
     */
    public static function directionVector(float $yaw, float $pitch): Vector3 {
        $yawRad = deg2rad($yaw);
        $pitchRad = deg2rad($pitch);
        $m = cos($pitchRad);

        return new Vector3(
            -$m * sin($yawRad),
            -sin($pitchRad),
            $m * cos($yawRad)
        );
    }

    /**
     * Get angle difference from origin to target given current rotation
     * Ported from game.AngleToPoint
     * @param Vector3 $origin Origin position
     * @param Vector3 $target Target position
     * @param Vector3 $rotation Current rotation (pitch, 0, yaw)
     * @return array{float, float} [yawDiff, pitchDiff] in degrees
     */
    public static function angleToPoint(Vector3 $origin, Vector3 $target, Vector3 $rotation): array {
        $rot = self::getRotationToPoint($origin, $target);
        $yawDiff = $rot[0] - $rotation->z;
        $pitchDiff = $rot[1] - $rotation->x;
        return [self::wrapYawDelta($yawDiff), $pitchDiff];
    }

    /**
     * Get yaw/pitch to look at target from origin
     * Ported from game.GetRotationToPoint
     * @param Vector3 $origin Origin position
     * @param Vector3 $target Target position
     * @return array{float, float} [yaw, pitch] in degrees
     */
    public static function getRotationToPoint(Vector3 $origin, Vector3 $target): array {
        $diff = $target->subtractVector($origin);
        $yaw = (atan2($diff->z, $diff->x) * 180.0 / M_PI) - 90.0;
        $pitch = atan2($diff->y, sqrt($diff->x * $diff->x + $diff->z * $diff->z)) * 180.0 / M_PI;

        if ($yaw < -180.0) {
            $yaw += 360.0;
        } elseif ($yaw > 180.0) {
            $yaw -= 360.0;
        }

        return [$yaw, -$pitch];
    }

    /**
     * Wrap yaw delta to -180 to 180 range
     * Ported from game.WrapYawDelta
     * @param float $delta Yaw delta
     * @return float Wrapped delta
     */
    public static function wrapYawDelta(float $delta): float {
        if ($delta > 180.0) {
            $delta -= 360.0;
        } elseif ($delta < -180.0) {
            $delta += 360.0;
        }
        return $delta;
    }

    /**
     * Linear interpolation between two vectors
     * @param Vector3 $start Start vector
     * @param Vector3 $end End vector
     * @param float $t Interpolation factor (0.0 to 1.0)
     * @return Vector3 Interpolated vector
     */
    public static function lerpVector3(Vector3 $start, Vector3 $end, float $t): Vector3 {
        return new Vector3(
            $start->x + ($end->x - $start->x) * $t,
            $start->y + ($end->y - $start->y) * $t,
            $start->z + ($end->z - $start->z) * $t
        );
    }

    /**
     * Clamp float to range
     * Ported from game.ClampFloat
     * @param float $value Value to clamp
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @return float Clamped value
     */
    public static function clamp(float $value, float $min, float $max): float {
        if ($value < $min) {
            return $min;
        }
        return min($value, $max);
    }

    /**
     * Check if float values are approximately equal
     * Ported from game.Float32ApproxEq
     * @param float $a First value
     * @param float $b Second value
     * @param float $epsilon Epsilon threshold (default 1e-5)
     * @return bool True if approximately equal
     */
    public static function approxEquals(float $a, float $b, float $epsilon = 1e-5): bool {
        return abs($a - $b) <= $epsilon;
    }

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {}
}
