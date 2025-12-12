<?php

declare(strict_types=1);

namespace Oomph\player\component;

use Oomph\utils\CircularQueue;

/**
 * Tracks click history and CPS (clicks per second) for both mouse buttons
 */
class ClicksComponent {

    private const HISTORY_SIZE = 20; // 1 second at 20 TPS

    // Click history queues (stores 1 or 0 for each tick)
    private CircularQueue $clicksLeft;
    private CircularQueue $clicksRight;

    // Ticks since last click
    private int $delayLeft = 0;
    private int $delayRight = 0;

    // Calculated CPS
    private int $cpsLeft = 0;
    private int $cpsRight = 0;

    public function __construct() {
        $this->clicksLeft = new CircularQueue(self::HISTORY_SIZE);
        $this->clicksRight = new CircularQueue(self::HISTORY_SIZE);

        // Initialize with zeros
        for ($i = 0; $i < self::HISTORY_SIZE; $i++) {
            $this->clicksLeft->push(0);
            $this->clicksRight->push(0);
        }
    }

    /**
     * Record a left click
     */
    public function recordLeftClick(): void {
        $this->delayLeft = 0;
    }

    /**
     * Record a right click
     */
    public function recordRightClick(): void {
        $this->delayRight = 0;
    }

    /**
     * Update click tracking (should be called every tick)
     */
    public function update(): void {
        // Increment delays
        $this->delayLeft++;
        $this->delayRight++;

        // Push current click state to history (1 if clicked this tick, 0 otherwise)
        $this->clicksLeft->push($this->delayLeft === 1 ? 1 : 0);
        $this->clicksRight->push($this->delayRight === 1 ? 1 : 0);

        // Calculate CPS from history
        $this->cpsLeft = $this->calculateCPS($this->clicksLeft);
        $this->cpsRight = $this->calculateCPS($this->clicksRight);
    }

    /**
     * Calculate CPS from a click history queue
     */
    private function calculateCPS(CircularQueue $queue): int {
        $clicks = 0;
        $history = $queue->toArray();

        foreach ($history as $value) {
            $clicks += $value;
        }

        return $clicks;
    }

    /**
     * Get the current left click CPS
     */
    public function getCPSLeft(): int {
        return $this->cpsLeft;
    }

    /**
     * Alias for getCPSLeft
     */
    public function getLeftCPS(): int {
        return $this->cpsLeft;
    }

    /**
     * Get the current right click CPS
     */
    public function getCPSRight(): int {
        return $this->cpsRight;
    }

    /**
     * Alias for getCPSRight
     */
    public function getRightCPS(): int {
        return $this->cpsRight;
    }

    /**
     * Alias for recordLeftClick
     */
    public function incrementLeftClicks(): void {
        $this->recordLeftClick();
    }

    /**
     * Alias for recordRightClick
     */
    public function incrementRightClicks(): void {
        $this->recordRightClick();
    }

    /**
     * Add a left click at the specified tick
     */
    public function addLeftClick(int $tick): void {
        $this->recordLeftClick();
    }

    /**
     * Add a right click at the specified tick
     */
    public function addRightClick(int $tick): void {
        $this->recordRightClick();
    }

    /**
     * Increment delays (called each tick to advance the delay counter)
     */
    public function incrementDelays(): void {
        // This is handled by update() but provided for direct access
    }

    /**
     * Get both CPS values as an array
     * @return array{left: int, right: int}
     */
    public function getCPS(): array {
        return [
            'left' => $this->cpsLeft,
            'right' => $this->cpsRight
        ];
    }

    /**
     * Get ticks since last left click
     */
    public function getDelayLeft(): int {
        return $this->delayLeft;
    }

    /**
     * Get ticks since last right click
     */
    public function getDelayRight(): int {
        return $this->delayRight;
    }

    /**
     * Get the left click history queue
     */
    public function getClicksLeftHistory(): CircularQueue {
        return $this->clicksLeft;
    }

    /**
     * Get the right click history queue
     */
    public function getClicksRightHistory(): CircularQueue {
        return $this->clicksRight;
    }

    /**
     * Reset all click tracking
     */
    public function reset(): void {
        $this->clicksLeft->clear();
        $this->clicksRight->clear();
        $this->delayLeft = 0;
        $this->delayRight = 0;
        $this->cpsLeft = 0;
        $this->cpsRight = 0;

        // Re-initialize with zeros
        for ($i = 0; $i < self::HISTORY_SIZE; $i++) {
            $this->clicksLeft->push(0);
            $this->clicksRight->push(0);
        }
    }
}
