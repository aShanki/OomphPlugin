<?php

declare(strict_types=1);

namespace Oomph\player\component;

use Oomph\utils\CircularQueue;

/**
 * Tracks click history and CPS (clicks per second) for both mouse buttons
 */
class ClicksComponent {

    private const HISTORY_SIZE = 20; // 1 second at 20 TPS
    private const INTERVAL_HISTORY_SIZE = 20; // Store last 20 click intervals for consistency analysis

    // Click history queues (stores click COUNT per tick, not 0/1 - Go: current+1)
    /** @var CircularQueue<int> */
    private CircularQueue $clicksLeft;
    /** @var CircularQueue<int> */
    private CircularQueue $clicksRight;

    // Click interval history (time between clicks for consistency detection)
    /** @var CircularQueue<int> */
    private CircularQueue $leftIntervals;
    /** @var CircularQueue<int> */
    private CircularQueue $rightIntervals;

    // Ticks since last click
    private int $delayLeft = 0;
    private int $delayRight = 0;

    // Last click tick for delay calculation
    private int $lastLeftClick = 0;
    private int $lastRightClick = 0;
    private int $inputCount = 0;

    // Calculated CPS (running total, not recalculated each tick)
    private int $cpsLeft = 0;
    private int $cpsRight = 0;

    // Pending clicks to add this tick (Go: multiple clicks per tick counted)
    private int $pendingLeftClicks = 0;
    private int $pendingRightClicks = 0;

    public function __construct() {
        $this->clicksLeft = new CircularQueue(self::HISTORY_SIZE);
        $this->clicksRight = new CircularQueue(self::HISTORY_SIZE);
        $this->leftIntervals = new CircularQueue(self::INTERVAL_HISTORY_SIZE);
        $this->rightIntervals = new CircularQueue(self::INTERVAL_HISTORY_SIZE);

        // Initialize with zeros
        for ($i = 0; $i < self::HISTORY_SIZE; $i++) {
            $this->clicksLeft->push(0);
            $this->clicksRight->push(0);
        }
    }

    /**
     * Record a left click (Go: clickLeft() - stores count, not just 0/1)
     */
    public function recordLeftClick(): void {
        $this->pendingLeftClicks++;
        $this->cpsLeft++;
        $this->delayLeft = $this->inputCount - $this->lastLeftClick;

        // Store interval for consistency detection (only if we have a previous click)
        if ($this->lastLeftClick > 0 && $this->delayLeft > 0) {
            $this->leftIntervals->push($this->delayLeft);
        }

        $this->lastLeftClick = $this->inputCount;
    }

    /**
     * Record a right click (Go: clickRight() - stores count, not just 0/1)
     */
    public function recordRightClick(): void {
        $this->pendingRightClicks++;
        $this->cpsRight++;
        $this->delayRight = $this->inputCount - $this->lastRightClick;

        // Store interval for consistency detection (only if we have a previous click)
        if ($this->lastRightClick > 0 && $this->delayRight > 0) {
            $this->rightIntervals->push($this->delayRight);
        }

        $this->lastRightClick = $this->inputCount;
    }

    /**
     * Update click tracking (should be called every tick)
     * Go: Tick() - subtracts oldest value, appends 0 for new tick
     */
    public function update(): void {
        $this->inputCount++;

        // Subtract oldest clicks from CPS (Go: cpsLeft -= leftClicksOldest)
        $oldestLeft = $this->clicksLeft->get(0) ?? 0;
        $oldestRight = $this->clicksRight->get(0) ?? 0;
        $this->cpsLeft -= $oldestLeft;
        $this->cpsRight -= $oldestRight;

        // Push pending clicks for this tick (0 if no clicks)
        $this->clicksLeft->push($this->pendingLeftClicks);
        $this->clicksRight->push($this->pendingRightClicks);

        // Reset pending for next tick
        $this->pendingLeftClicks = 0;
        $this->pendingRightClicks = 0;
    }

    /**
     * Calculate CPS from a click history queue
     * @param CircularQueue<int> $queue
     */
    private function calculateCPS(CircularQueue $queue): int {
        $clicks = 0;
        $history = $queue->toArray();

        foreach ($history as $value) {
            $clicks += (int) $value;
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
     * @return CircularQueue<int>
     */
    public function getClicksLeftHistory(): CircularQueue {
        return $this->clicksLeft;
    }

    /**
     * Get the right click history queue
     * @return CircularQueue<int>
     */
    public function getClicksRightHistory(): CircularQueue {
        return $this->clicksRight;
    }

    /**
     * Get the left click interval history queue
     * @return CircularQueue<int>
     */
    public function getLeftIntervals(): CircularQueue {
        return $this->leftIntervals;
    }

    /**
     * Get the right click interval history queue
     * @return CircularQueue<int>
     */
    public function getRightIntervals(): CircularQueue {
        return $this->rightIntervals;
    }

    /**
     * Calculate standard deviation of left click intervals
     * Returns -1 if not enough samples (need at least 5)
     */
    public function getLeftIntervalStdDev(): float {
        return $this->calculateStdDev($this->leftIntervals);
    }

    /**
     * Calculate standard deviation of right click intervals
     * Returns -1 if not enough samples (need at least 5)
     */
    public function getRightIntervalStdDev(): float {
        return $this->calculateStdDev($this->rightIntervals);
    }

    /**
     * Calculate standard deviation of intervals in a queue
     * @param CircularQueue<int> $queue
     * @return float Standard deviation, or -1 if insufficient samples
     */
    private function calculateStdDev(CircularQueue $queue): float {
        $count = $queue->count();
        if ($count < 5) {
            return -1.0; // Not enough samples
        }

        $intervals = $queue->toArray();

        // Calculate mean
        $sum = 0.0;
        foreach ($intervals as $interval) {
            $sum += (float) $interval;
        }
        $mean = $sum / $count;

        // Calculate variance
        $variance = 0.0;
        foreach ($intervals as $interval) {
            $diff = (float) $interval - $mean;
            $variance += $diff * $diff;
        }
        $variance /= $count;

        return sqrt($variance);
    }

    /**
     * Get mean of left click intervals
     * Returns -1 if not enough samples
     */
    public function getLeftIntervalMean(): float {
        return $this->calculateMean($this->leftIntervals);
    }

    /**
     * Get mean of right click intervals
     * Returns -1 if not enough samples
     */
    public function getRightIntervalMean(): float {
        return $this->calculateMean($this->rightIntervals);
    }

    /**
     * Calculate mean of intervals in a queue
     * @param CircularQueue<int> $queue
     * @return float Mean, or -1 if insufficient samples
     */
    private function calculateMean(CircularQueue $queue): float {
        $count = $queue->count();
        if ($count < 5) {
            return -1.0;
        }

        $intervals = $queue->toArray();
        $sum = 0.0;
        foreach ($intervals as $interval) {
            $sum += (float) $interval;
        }
        return $sum / $count;
    }

    /**
     * Reset all click tracking (Go: resetAndPropagateLeft/Right)
     */
    public function reset(): void {
        $this->clicksLeft->clear();
        $this->clicksRight->clear();
        $this->leftIntervals->clear();
        $this->rightIntervals->clear();
        $this->delayLeft = 0;
        $this->delayRight = 0;
        $this->lastLeftClick = 0;
        $this->lastRightClick = 0;
        $this->cpsLeft = 0;
        $this->cpsRight = 0;
        $this->pendingLeftClicks = 0;
        $this->pendingRightClicks = 0;

        // Re-initialize with zeros
        for ($i = 0; $i < self::HISTORY_SIZE; $i++) {
            $this->clicksLeft->push(0);
            $this->clicksRight->push(0);
        }
    }
}
