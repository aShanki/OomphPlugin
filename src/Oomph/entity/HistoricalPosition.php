<?php

declare(strict_types=1);

namespace Oomph\entity;

use pocketmine\math\Vector3;

/**
 * Represents a historical position snapshot of an entity.
 * Used for lag compensation and rewind calculations.
 */
class HistoricalPosition {

    public function __construct(
        public readonly Vector3 $position,
        public readonly Vector3 $prevPosition,
        public readonly int $tick,
        public readonly bool $wasTeleport = false
    ) {}

    /**
     * Create a clone with updated values.
     */
    public function withPosition(Vector3 $position): self {
        return new self($position, $this->prevPosition, $this->tick, $this->wasTeleport);
    }

    /**
     * Calculate the distance moved between prevPosition and position.
     */
    public function getDistanceMoved(): float {
        return $this->prevPosition->distance($this->position);
    }

    /**
     * Get the velocity vector between previous and current position.
     */
    public function getVelocity(): Vector3 {
        return $this->position->subtractVector($this->prevPosition);
    }
}
