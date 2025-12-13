<?php

declare(strict_types=1);

namespace Oomph\player\component;

use Oomph\entity\EntityTracker;
use Oomph\entity\TrackedEntity;
use Oomph\utils\Math;
use Oomph\utils\AABBUtils;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;

/**
 * Combat component with 10-step lerp validation
 * Based on anticheat-reference/player/component/combat.go
 */
class CombatComponent {

    // Constants from Go implementation
    public const LERP_STEPS = 10;
    public const SURVIVAL_REACH = 2.9;
    public const SNEAKING_EYE_HEIGHT = 1.54;  // 1.8 - 0.26
    public const DEFAULT_EYE_HEIGHT = 1.62;   // Normal standing eye height

    // Entity tracking for lag compensation
    private EntityTracker $entityTracker;

    // Last tick when player swung their arm
    private int $lastSwingTick = 0;

    // Combat validation state
    private Vector3 $startAttackPos;
    private Vector3 $endAttackPos;
    private Vector3 $startEntityPos;
    private Vector3 $endEntityPos;
    private Vector3 $startRotation;
    private Vector3 $endRotation;

    private ?TrackedEntity $targetedEntity = null;
    private int $targetedRuntimeID = 0;
    private ?AxisAlignedBB $entityBB = null;

    /** @var float[] raycast distances */
    private array $raycastResults = [];
    /** @var float[] raw distances to closest point */
    private array $rawResults = [];
    /** @var float[] angles to entity */
    private array $angleResults = [];

    private ?InventoryTransactionPacket $attackInput = null;
    private bool $attacked = false;
    private bool $checkMisprediction = false;

    public function __construct() {
        $this->entityTracker = new EntityTracker();

        // Initialize vectors to prevent null issues
        $this->startAttackPos = Vector3::zero();
        $this->endAttackPos = Vector3::zero();
        $this->startEntityPos = Vector3::zero();
        $this->endEntityPos = Vector3::zero();
        $this->startRotation = Vector3::zero();
        $this->endRotation = Vector3::zero();
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
     * Get the last tick when player swung
     */
    public function getLastSwingTick(): int {
        return $this->lastSwingTick;
    }

    /**
     * Get the entity tracker for lag compensation
     */
    public function getEntityTracker(): EntityTracker {
        return $this->entityTracker;
    }

    /**
     * Record attack input and set up validation state
     *
     * @param InventoryTransactionPacket|null $input Attack packet from client
     * @param Vector3 $attackerPos Current position of attacker
     * @param Vector3 $lastAttackerPos Previous position of attacker
     * @param bool $isSneaking Whether attacker is sneaking
     * @param TrackedEntity|null $target Target entity (null for air swing check)
     * @param int $clientTick Client's current tick for rewind
     */
    public function attack(
        ?InventoryTransactionPacket $input,
        Vector3 $attackerPos,
        Vector3 $lastAttackerPos,
        bool $isSneaking,
        ?TrackedEntity $target = null,
        int $clientTick = 0
    ): void {
        // Do not allow another attack if we already have input this tick
        if ($this->attackInput !== null) {
            return;
        }

        // Set up attack state
        $this->attacked = true;
        $this->attackInput = $input;
        $this->targetedEntity = $target;
        $this->targetedRuntimeID = $target !== null ? $target->runtimeId : 0;

        // Calculate eye position for attacker
        $eyeHeight = $isSneaking ? self::SNEAKING_EYE_HEIGHT : self::DEFAULT_EYE_HEIGHT;
        $this->startAttackPos = $lastAttackerPos->add(0, $eyeHeight, 0);
        $this->endAttackPos = $attackerPos->add(0, $eyeHeight, 0);

        // If we have a target, set up entity position and bounding box
        if ($target !== null) {
            // Try to get historical position for lag compensation
            $rewindData = $this->entityTracker->rewindWithPrev($target->runtimeId, $clientTick);

            if ($rewindData !== null) {
                $this->startEntityPos = $rewindData['prevPosition'];
                $this->endEntityPos = $rewindData['position'];
            } else {
                // Fallback to current position
                $this->startEntityPos = $target->prevPosition;
                $this->endEntityPos = $target->position;
            }

            $this->entityBB = $target->getBoundingBox();
        }
    }

    /**
     * Run 10-step lerp combat validation
     *
     * @param Vector3 $rotation Current rotation (pitch, yaw, headYaw)
     * @param Vector3 $lastRotation Previous rotation
     * @param int $inputMode Player's input mode (0=touch, 1=mouse, 2=controller)
     * @return bool True if hit is valid
     */
    public function calculate(Vector3 $rotation, Vector3 $lastRotation, int $inputMode = 1): bool {
        if (!$this->attacked) {
            return false;
        }

        // Clear results from previous calculation
        $this->raycastResults = [];
        $this->rawResults = [];
        $this->angleResults = [];

        // No target means no validation needed
        if ($this->targetedEntity === null || $this->entityBB === null) {
            return false;
        }

        $this->startRotation = $lastRotation;
        $this->endRotation = $rotation;

        $hitValid = false;
        $closestRaycastDist = 1000000.0;
        $closestRawDist = 1000000.0;
        $closestAngle = 1000000.0;

        // 10-step lerp validation
        $stepAmt = 1.0 / self::LERP_STEPS;
        for ($i = 0; $i <= self::LERP_STEPS; $i++) {
            $partialTicks = $i * $stepAmt;
            $lerped = $this->lerp($partialTicks);

            // Create entity bounding box at interpolated position, grown by 0.1 for tolerance
            $entityBB = $this->createBBoxAt($lerped['entityPos'])->expand(0.1, 0.1, 0.1);

            // If attack position is inside the entity's bounding box, hit is valid
            if ($this->vec3Within($lerped['attackPos'], $entityBB)) {
                $this->raycastResults[] = 0.0;
                $this->rawResults[] = 0.0;
                $this->angleResults[] = 0.0;
                $closestRaycastDist = 0.0;
                $closestRawDist = 0.0;
                $closestAngle = 0.0;
                $hitValid = true;
                break;
            }

            // Calculate angle between look direction and entity
            $angle = $this->calculateAngle($lerped['attackPos'], $lerped['entityPos'], $lerped['rotation']);
            $this->angleResults[] = $angle;
            $closestAngle = min($closestAngle, $angle);

            // Perform raycast from attack position in look direction
            $direction = Math::directionVector($lerped['rotation']->x, $lerped['rotation']->y);
            $rayEnd = $lerped['attackPos']->addVector($direction->multiply(7.0));

            $raycastDist = AABBUtils::rayIntersectsAABB($lerped['attackPos'], $direction, $entityBB);
            if ($raycastDist !== null) {
                $this->raycastResults[] = $raycastDist;
                $hitValid = $hitValid || $raycastDist <= self::SURVIVAL_REACH;
                $closestRaycastDist = min($closestRaycastDist, $raycastDist);
            }

            // Calculate raw distance to closest point on bounding box
            $closestPoint = AABBUtils::closestPointOnAABB($entityBB, $lerped['attackPos']);
            $rawDist = $lerped['attackPos']->distance($closestPoint);
            $this->rawResults[] = $rawDist;
            $closestRawDist = min($closestRawDist, $rawDist);
        }

        // Touch mode: allow hits based on raw distance if raycast didn't hit
        if (!$hitValid && $inputMode === 0) { // 0 = touch
            // Touch players can hit if close enough and looking roughly at target
            $hitValid = $closestRawDist <= self::SURVIVAL_REACH && $closestAngle <= 90.0;
        }

        return $hitValid;
    }

    /**
     * Interpolate attack pos, entity pos, and rotation
     *
     * @param float $partialTicks Interpolation factor (0.0 to 1.0)
     * @return array{attackPos: Vector3, entityPos: Vector3, rotation: Vector3}
     */
    private function lerp(float $partialTicks): array {
        if ($partialTicks === 0.0) {
            return [
                'attackPos' => $this->startAttackPos,
                'entityPos' => $this->startEntityPos,
                'rotation' => $this->startRotation
            ];
        } elseif ($partialTicks === 1.0) {
            return [
                'attackPos' => $this->endAttackPos,
                'entityPos' => $this->endEntityPos,
                'rotation' => $this->endRotation
            ];
        }

        // Linear interpolation for positions
        $attackPosDelta = $this->endAttackPos->subtractVector($this->startAttackPos)->multiply($partialTicks);
        $entityPosDelta = $this->endEntityPos->subtractVector($this->startEntityPos)->multiply($partialTicks);

        // Rotation interpolation with wrapping
        $yaw = Math::lerpRotation($this->startRotation->x, $this->endRotation->x, $partialTicks);
        $pitch = Math::lerpRotation($this->startRotation->y, $this->endRotation->y, $partialTicks);
        $headYaw = Math::lerpRotation($this->startRotation->z, $this->endRotation->z, $partialTicks);

        return [
            'attackPos' => $this->startAttackPos->addVector($attackPosDelta),
            'entityPos' => $this->startEntityPos->addVector($entityPosDelta),
            'rotation' => new Vector3($yaw, $pitch, $headYaw)
        ];
    }

    /**
     * Create a bounding box at a specific position using the stored entity dimensions
     */
    private function createBBoxAt(Vector3 $pos): AxisAlignedBB {
        if ($this->entityBB === null) {
            return new AxisAlignedBB(0, 0, 0, 0, 0, 0);
        }

        $width = $this->entityBB->maxX - $this->entityBB->minX;
        $height = $this->entityBB->maxY - $this->entityBB->minY;
        $depth = $this->entityBB->maxZ - $this->entityBB->minZ;

        $halfWidth = $width / 2.0;
        $halfDepth = $depth / 2.0;

        return new AxisAlignedBB(
            $pos->x - $halfWidth,
            $pos->y,
            $pos->z - $halfDepth,
            $pos->x + $halfWidth,
            $pos->y + $height,
            $pos->z + $halfDepth
        );
    }

    /**
     * Check if a point is within an AABB
     */
    private function vec3Within(Vector3 $point, AxisAlignedBB $box): bool {
        return $point->x >= $box->minX && $point->x <= $box->maxX &&
               $point->y >= $box->minY && $point->y <= $box->maxY &&
               $point->z >= $box->minZ && $point->z <= $box->maxZ;
    }

    /**
     * Calculate angle between attack position and entity position
     * @return float Angle in degrees
     */
    private function calculateAngle(Vector3 $attackPos, Vector3 $entityPos, Vector3 $rotation): float {
        // Vector from attacker to entity
        $toEntity = $entityPos->subtractVector($attackPos);

        // Direction the player is looking
        $lookDir = Math::directionVector($rotation->x, $rotation->y);

        // Calculate angle between vectors
        $dot = $lookDir->dot($toEntity);
        $magProduct = $lookDir->length() * $toEntity->length();

        if ($magProduct === 0.0) {
            return 0.0;
        }

        $cosAngle = $dot / $magProduct;
        $cosAngle = max(-1.0, min(1.0, $cosAngle)); // Clamp to prevent NaN

        return abs(rad2deg(acos($cosAngle)));
    }

    // Getters for detection use

    /**
     * Get all raycast distances from last calculation
     * @return float[]
     */
    public function getRaycasts(): array {
        return $this->raycastResults;
    }

    /**
     * Get all raw distances from last calculation
     * @return float[]
     */
    public function getRaws(): array {
        return $this->rawResults;
    }

    /**
     * Get all angles from last calculation
     * @return float[]
     */
    public function getAngles(): array {
        return $this->angleResults;
    }

    /**
     * Get the targeted entity from last attack
     */
    public function getTargetedEntity(): ?TrackedEntity {
        return $this->targetedEntity;
    }

    /**
     * Get the targeted entity's runtime ID
     */
    public function getTargetedRuntimeID(): int {
        return $this->targetedRuntimeID;
    }

    /**
     * Check if misprediction check is needed
     */
    public function shouldCheckMisprediction(): bool {
        return $this->checkMisprediction;
    }

    /**
     * Set whether misprediction check is needed
     */
    public function setCheckMisprediction(bool $check): void {
        $this->checkMisprediction = $check;
    }

    /**
     * Check if an attack was processed this tick
     */
    public function wasAttacked(): bool {
        return $this->attacked;
    }

    /**
     * Get the attack input packet
     */
    public function getAttackInput(): ?InventoryTransactionPacket {
        return $this->attackInput;
    }

    /**
     * Reset per-tick state (should be called at end of tick)
     */
    public function reset(): void {
        $this->attackInput = null;
        $this->targetedEntity = null;
        $this->targetedRuntimeID = 0;
        $this->entityBB = null;
        $this->checkMisprediction = false;
        $this->raycastResults = [];
        $this->rawResults = [];
        $this->angleResults = [];
        $this->attacked = false;
    }
}
