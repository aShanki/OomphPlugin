<?php

declare(strict_types=1);

namespace Oomph\entity;

use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use Oomph\utils\CircularQueue;

/**
 * Tracks an entity's position history for lag compensation.
 * Stores up to 100 historical positions (5 seconds at 20 TPS).
 */
class TrackedEntity {

    /** @var CircularQueue<HistoricalPosition> */
    private CircularQueue $positionHistory;

    private int $ticksSinceTeleport = 0;

    public function __construct(
        public readonly int $runtimeId,
        public Vector3 $position,
        public Vector3 $prevPosition,
        public Vector3 $serverPosition,
        public Vector3 $velocity,
        public readonly float $width,
        public readonly float $height,
        public readonly float $scale = 1.0,
        public readonly bool $isPlayer = false,
        int $historySize = 100
    ) {
        $this->positionHistory = new CircularQueue($historySize);
        
        // Initialize with current position
        $this->recordPosition($position, 0, false);
    }

    /**
     * Update the entity's position and record it in history.
     */
    public function updatePosition(Vector3 $pos, int $tick, bool $wasTeleport = false): void {
        $this->prevPosition = $this->position;
        $this->position = $pos;

        if ($wasTeleport) {
            $this->ticksSinceTeleport = 0;
        } else {
            $this->ticksSinceTeleport++;
        }

        $this->recordPosition($pos, $tick, $wasTeleport);
    }

    /**
     * Update the server-authoritative position (for validation).
     */
    public function updateServerPosition(Vector3 $pos): void {
        $this->serverPosition = $pos;
    }

    /**
     * Update the entity's velocity.
     */
    public function updateVelocity(Vector3 $vel): void {
        $this->velocity = $vel;
    }

    /**
     * Get the entity's current bounding box.
     */
    public function getBoundingBox(): AxisAlignedBB {
        $halfWidth = ($this->width * $this->scale) / 2;
        $height = $this->height * $this->scale;

        return new AxisAlignedBB(
            $this->position->x - $halfWidth,
            $this->position->y,
            $this->position->z - $halfWidth,
            $this->position->x + $halfWidth,
            $this->position->y + $height,
            $this->position->z + $halfWidth
        );
    }

    /**
     * Get the bounding box at a specific position (for historical positions).
     */
    public function getBoundingBoxAt(Vector3 $position): AxisAlignedBB {
        $halfWidth = ($this->width * $this->scale) / 2;
        $height = $this->height * $this->scale;

        return new AxisAlignedBB(
            $position->x - $halfWidth,
            $position->y,
            $position->z - $halfWidth,
            $position->x + $halfWidth,
            $position->y + $height,
            $position->z + $halfWidth
        );
    }

    /**
     * Rewind to find the historical position closest to the target tick.
     * Returns null if no history is available.
     */
    public function getHistoricalPosition(int $tick): ?HistoricalPosition {
        if ($this->positionHistory->isEmpty()) {
            return null;
        }

        $closest = null;
        $minDiff = PHP_INT_MAX;

        foreach ($this->positionHistory->toArray() as $historical) {
            $diff = abs($historical->tick - $tick);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $historical;
            }
        }

        return $closest;
    }

    /**
     * Get all historical positions.
     * @return array<HistoricalPosition>
     */
    public function getPositionHistory(): array {
        return $this->positionHistory->toArray();
    }

    /**
     * Get the number of ticks since the last teleport.
     */
    public function getTicksSinceTeleport(): int {
        return $this->ticksSinceTeleport;
    }

    /**
     * Clear all position history.
     */
    public function clearHistory(): void {
        $this->positionHistory->clear();
    }

    /**
     * Record a position in the history buffer.
     */
    private function recordPosition(Vector3 $pos, int $tick, bool $wasTeleport): void {
        $historical = new HistoricalPosition(
            $pos,
            $this->prevPosition,
            $tick,
            $wasTeleport
        );
        $this->positionHistory->push($historical);
    }
}
