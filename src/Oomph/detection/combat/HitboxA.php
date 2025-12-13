<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;
use Oomph\entity\TrackedEntity;
use Oomph\utils\AABBUtils;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;

/**
 * HitboxA Detection
 *
 * Checks if the player is using a hitbox modification greater than the one sent by the server.
 * Based on anticheat-reference/player/detection/hitbox_a.go
 *
 * Uses the Interact packet's click position to validate hitbox against entity bbox.
 * Requires client entity tracking to be enabled.
 */
class HitboxA extends Detection {

    // Distance threshold from Go implementation
    private const DISTANCE_THRESHOLD = 0.004;

    // Minimum ticks since entity teleport to check
    private const MIN_TICKS_SINCE_TELEPORT = 10;

    public function __construct() {
        // From Go: FailBuffer: 6, MaxBuffer: 6, MaxViolations: 10, no TrustDuration
        parent::__construct(
            maxBuffer: 6.0,
            failBuffer: 6.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "HitboxA";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 10.0;
    }

    /**
     * This detection can cancel attacks
     */
    public function isCancellable(): bool {
        return false; // Go implementation doesn't explicitly cancel, just flags
    }

    /**
     * Check if client-reported interaction position matches expected entity hitbox
     * This matches the Go implementation from lines 48-74
     *
     * @param OomphPlayer $player The player who sent the Interact packet
     * @param TrackedEntity $entity The target entity from client tracker
     * @param Vector3 $clickPosition Position reported by client in Interact packet
     */
    public function check(OomphPlayer $player, TrackedEntity $entity, Vector3 $clickPosition): void {
        // Skip if entity is not a player (line 59 in Go)
        if (!$entity->isPlayer) {
            return;
        }

        // Skip if entity recently teleported (line 59 in Go)
        if ($entity->getTicksSinceTeleport() <= self::MIN_TICKS_SINCE_TELEPORT) {
            return;
        }

        // Check click position against both current and previous entity position (lines 62-63 in Go)
        // Entity bbox grown by 0.1 for tolerance
        $bbox1 = $entity->getBoundingBoxAt($entity->prevPosition);
        $expandedBbox1 = AABBUtils::expand($bbox1, 0.1);

        $bbox2 = $entity->getBoundingBoxAt($entity->position);
        $expandedBbox2 = AABBUtils::expand($bbox2, 0.1);

        // Calculate distance to closest point on both bboxes (lines 64-67 in Go)
        $closestPoint1 = AABBUtils::closestPointOnAABB($expandedBbox1, $clickPosition);
        $dist1 = $clickPosition->distance($closestPoint1);

        $closestPoint2 = AABBUtils::closestPointOnAABB($expandedBbox2, $clickPosition);
        $dist2 = $clickPosition->distance($closestPoint2);

        // Use minimum distance (line 64 in Go: math32.Min)
        $dist = min($dist1, $dist2);

        // Flag if distance exceeds threshold (line 68 in Go)
        if ($dist > self::DISTANCE_THRESHOLD) {
            // Calculate dynamic fail amount based on distance (line 69 in Go)
            // amt = 0.6 + (dist * 2), rounded to 3 decimals
            $amount = round(0.6 + ($dist * 2.0), 3);
            $this->fail($player, $amount, [
                'dist' => $dist,
                'expansion' => $amount
            ]);
        } else {
            // Pass and decay entire buffer (line 71 in Go)
            $this->pass($this->buffer);
        }
    }

    /**
     * Get the distance threshold for debugging
     */
    public function getDistanceThreshold(): float {
        return self::DISTANCE_THRESHOLD;
    }
}
