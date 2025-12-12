<?php

declare(strict_types=1);

namespace Oomph\entity;

use pocketmine\math\Vector3;

/**
 * Tracks all entities visible to a player for lag compensation.
 * Each player has their own EntityTracker instance.
 */
class EntityTracker {

    /** @var array<int, TrackedEntity> */
    private array $entities = [];

    /**
     * Add a new entity to the tracker.
     */
    public function addEntity(
        int $runtimeId,
        Vector3 $position,
        float $width,
        float $height,
        float $scale = 1.0,
        bool $isPlayer = false
    ): void {
        $this->entities[$runtimeId] = new TrackedEntity(
            runtimeId: $runtimeId,
            position: $position,
            prevPosition: $position,
            serverPosition: $position,
            velocity: Vector3::zero(),
            width: $width,
            height: $height,
            scale: $scale,
            isPlayer: $isPlayer
        );
    }

    /**
     * Remove an entity from tracking.
     */
    public function removeEntity(int $runtimeId): void {
        unset($this->entities[$runtimeId]);
    }

    /**
     * Update an entity's position.
     */
    public function updateEntity(int $runtimeId, Vector3 $position, int $tick, bool $wasTeleport = false): void {
        if (isset($this->entities[$runtimeId])) {
            $this->entities[$runtimeId]->updatePosition($position, $tick, $wasTeleport);
        }
    }

    /**
     * Update an entity's server position (authoritative).
     */
    public function updateServerPosition(int $runtimeId, Vector3 $position): void {
        if (isset($this->entities[$runtimeId])) {
            $this->entities[$runtimeId]->updateServerPosition($position);
        }
    }

    /**
     * Update an entity's velocity.
     */
    public function updateVelocity(int $runtimeId, Vector3 $velocity): void {
        if (isset($this->entities[$runtimeId])) {
            $this->entities[$runtimeId]->updateVelocity($velocity);
        }
    }

    /**
     * Get a tracked entity by runtime ID.
     */
    public function getEntity(int $runtimeId): ?TrackedEntity {
        return $this->entities[$runtimeId] ?? null;
    }

    /**
     * Check if an entity is being tracked.
     */
    public function hasEntity(int $runtimeId): bool {
        return isset($this->entities[$runtimeId]);
    }

    /**
     * Get all tracked entities.
     * @return array<int, TrackedEntity>
     */
    public function getAllEntities(): array {
        return $this->entities;
    }

    /**
     * Get the number of tracked entities.
     */
    public function getEntityCount(): int {
        return count($this->entities);
    }

    /**
     * Rewind an entity to a specific tick for lag compensation.
     * Returns the historical position closest to the target tick.
     */
    public function rewind(int $runtimeId, int $targetTick): ?HistoricalPosition {
        $entity = $this->getEntity($runtimeId);
        if ($entity === null) {
            return null;
        }

        return $entity->getHistoricalPosition($targetTick);
    }

    /**
     * Clear all tracked entities.
     */
    public function clear(): void {
        $this->entities = [];
    }

    /**
     * Remove entities that haven't been updated recently.
     * Call this periodically to clean up stale entity data.
     */
    public function cleanupStaleEntities(int $currentTick, int $maxAge = 100): void {
        foreach ($this->entities as $runtimeId => $entity) {
            // Check if the most recent historical position is too old
            $history = $entity->getPositionHistory();
            if (!empty($history)) {
                $lastHistorical = end($history);
                if (($currentTick - $lastHistorical->tick) > $maxAge) {
                    $this->removeEntity($runtimeId);
                }
            }
        }
    }
}
