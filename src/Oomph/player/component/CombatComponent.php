<?php

declare(strict_types=1);

namespace Oomph\player\component;

use Oomph\entity\EntityTracker;

/**
 * Tracks combat-related state for killaura and reach detection
 */
class CombatComponent {

    // Entity tracking for lag compensation
    private EntityTracker $entityTracker;

    // Last tick when player swung their arm
    private int $lastSwingTick = 0;

    // Set of entity runtime IDs attacked this tick
    /** @var array<int, true> */
    private array $attackedEntitiesThisTick = [];

    // Last reach distance when attacking
    private float $lastReachDistance = 0.0;

    // Last angle to target when attacking (degrees)
    private float $lastAngle = 0.0;

    public function __construct() {
        $this->entityTracker = new EntityTracker();
    }

    /**
     * Record a swing action
     */
    public function recordSwing(int $tick): void {
        $this->lastSwingTick = $tick;
    }

    /**
     * Alias for recordSwing() for backwards compatibility
     */
    public function updateLastSwingTick(int $tick): void {
        $this->recordSwing($tick);
    }

    /**
     * Record an attack on an entity
     * @param int $entityRuntimeId The runtime ID of the attacked entity
     * @param float $reachDistance The distance to the entity
     * @param float $angle The angle to the entity in degrees
     */
    public function recordAttack(int $entityRuntimeId, float $reachDistance, float $angle): void {
        $this->attackedEntitiesThisTick[$entityRuntimeId] = true;
        $this->lastReachDistance = $reachDistance;
        $this->lastAngle = $angle;
    }

    /**
     * Alias for simple entity tracking without distance/angle
     * @param int $entityRuntimeId The runtime ID of the attacked entity
     */
    public function addAttackedEntity(int $entityRuntimeId): void {
        $this->attackedEntitiesThisTick[$entityRuntimeId] = true;
    }

    /**
     * Reset per-tick state (should be called at end of tick)
     */
    public function reset(): void {
        $this->attackedEntitiesThisTick = [];
    }

    /**
     * Get the last tick when player swung
     */
    public function getLastSwingTick(): int {
        return $this->lastSwingTick;
    }

    /**
     * Get all entities attacked this tick
     * @return array<int, true>
     */
    public function getAttackedEntitiesThisTick(): array {
        return $this->attackedEntitiesThisTick;
    }

    /**
     * Get the number of entities attacked this tick
     */
    public function getAttackedEntityCount(): int {
        return count($this->attackedEntitiesThisTick);
    }

    /**
     * Check if a specific entity was attacked this tick
     */
    public function wasEntityAttacked(int $entityRuntimeId): bool {
        return isset($this->attackedEntitiesThisTick[$entityRuntimeId]);
    }

    /**
     * Get the last reach distance
     */
    public function getLastReachDistance(): float {
        return $this->lastReachDistance;
    }

    /**
     * Get the last angle to target
     */
    public function getLastAngle(): float {
        return $this->lastAngle;
    }

    /**
     * Get the entity tracker for lag compensation
     */
    public function getEntityTracker(): EntityTracker {
        return $this->entityTracker;
    }
}
