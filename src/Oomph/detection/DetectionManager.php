<?php

declare(strict_types=1);

namespace Oomph\detection;

use Oomph\player\OomphPlayer;

/**
 * Manages all detection instances for a player
 *
 * Holds a registry of Detection instances and provides methods to:
 * - Register new detections
 * - Retrieve specific detections by name
 * - Run all detections
 * - Get overall violation statistics
 */
class DetectionManager {

    /** @var Detection[] Map of detection name => Detection instance */
    private array $detections = [];

    /** The player this manager belongs to */
    private OomphPlayer $player;

    public function __construct(OomphPlayer $player) {
        $this->player = $player;
    }

    /**
     * Register a detection
     *
     * @param Detection $detection The detection to register
     */
    public function register(Detection $detection): void {
        $this->detections[$detection->getName()] = $detection;
    }

    /**
     * Get a detection by name
     *
     * @param string $name Detection name (e.g., "AutoclickerA")
     * @return Detection|null The detection or null if not found
     */
    public function get(string $name): ?Detection {
        return $this->detections[$name] ?? null;
    }

    /**
     * Get all registered detections
     *
     * @return Detection[]
     */
    public function getAll(): array {
        return $this->detections;
    }

    /**
     * Run all detections for the player
     * Note: Individual detections will be triggered from specific packet handlers
     * This method is for any global/periodic checks
     *
     * @param OomphPlayer $player The player to check
     */
    public function runAll(OomphPlayer $player): void {
        // Individual detections are triggered from packet handlers
        // This could be used for periodic checks or cleanup
    }

    /**
     * Get total violation count across all detections
     *
     * @return float Total violations
     */
    public function getTotalViolations(): float {
        $total = 0.0;
        foreach ($this->detections as $detection) {
            $total += $detection->getViolations();
        }
        return $total;
    }

    /**
     * Get violations grouped by detection type
     *
     * @return array<string, float> Map of type => total violations
     */
    public function getViolationsByType(): array {
        $byType = [];
        foreach ($this->detections as $detection) {
            $type = $detection->getType();
            if (!isset($byType[$type])) {
                $byType[$type] = 0.0;
            }
            $byType[$type] += $detection->getViolations();
        }
        return $byType;
    }

    /**
     * Get all detections with violations
     *
     * @return Detection[]
     */
    public function getViolatedDetections(): array {
        return array_filter($this->detections, function(Detection $detection) {
            return $detection->getViolations() > 0;
        });
    }

    /**
     * Reset all detections
     */
    public function resetAll(): void {
        foreach ($this->detections as $detection) {
            $detection->reset();
        }
    }

    /**
     * Get the player this manager belongs to
     */
    public function getPlayer(): OomphPlayer {
        return $this->player;
    }
}
