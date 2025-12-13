<?php

declare(strict_types=1);

namespace Oomph\utils;

/**
 * Physics constants ported from Oomph anticheat reference implementation
 * Values are from player/simulation/movement.go and game/movement.go
 */
final class PhysicsConstants {
    // Movement physics
    public const NORMAL_GRAVITY = 0.08;
    public const SLOW_FALLING_GRAVITY = 0.01;
    public const NORMAL_GRAVITY_MULTIPLIER = 0.98;
    public const LEVITATION_GRAVITY_MULTIPLIER = 0.05;
    public const DEFAULT_AIR_FRICTION = 0.91;
    public const DEFAULT_BLOCK_FRICTION = 0.6;

    // Jump
    public const DEFAULT_JUMP_HEIGHT = 0.42;
    public const JUMP_DELAY_TICKS = 10;

    // Step and climb
    public const STEP_HEIGHT = 0.6;
    public const CLIMB_SPEED = 0.2;

    // Impulse limits
    public const MAX_CONSUMING_IMPULSE = 0.1225;
    public const MAX_SNEAK_IMPULSE = 0.3;
    public const MAX_NORMALIZED_IMPULSE = 0.70710678118; // 1/sqrt(2)

    // Eye height offsets
    public const DEFAULT_PLAYER_HEIGHT_OFFSET = 1.62;
    public const SNEAKING_PLAYER_HEIGHT_OFFSET = 1.27;

    // Bounce multipliers
    public const SLIME_BOUNCE_MULTIPLIER = -1.0;
    public const BED_BOUNCE_MULTIPLIER = -0.66;
    public const SLIDE_OFFSET_MULTIPLIER = 0.4;

    // Combat
    public const COMBAT_LERP_STEPS = 10;
    public const SURVIVAL_REACH = 2.9;
    public const ENTITY_SEARCH_RADIUS = 6.0;
    public const HITBOX_THRESHOLD = 0.004;
    public const HITBOX_GROWTH = 0.1;
    public const MAX_SWING_TICK_DIFF = 10;
    public const MAX_RAYCAST_DISTANCE = 7.0;

    // Edge avoidance (bedrock-specific)
    public const EDGE_BOUNDARY = 0.025;
    public const EDGE_OFFSET = 0.05;

    // Cobweb multipliers
    public const COBWEB_X_MULTIPLIER = 0.25;
    public const COBWEB_Y_MULTIPLIER = 0.05;
    public const COBWEB_Z_MULTIPLIER = 0.25;

    // Input modes
    public const INPUT_MODE_TOUCH = 0;
    public const INPUT_MODE_MOUSE = 1;
    public const INPUT_MODE_CONTROLLER = 2;

    // Correction
    public const DEFAULT_CORRECTION_THRESHOLD = 0.1;
    public const TICKS_AFTER_TELEPORT = 20;

    // Epsilon values for floating point comparisons
    public const VELOCITY_EPSILON = 0.003;
    public const COLLISION_EPSILON = 0.0001;
    public const PENETRATION_EPSILON = 0.001;

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {}
}
