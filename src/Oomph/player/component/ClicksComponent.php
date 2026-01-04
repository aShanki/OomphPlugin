<?php

declare(strict_types=1);

namespace Oomph\player\component;

use Oomph\utils\CircularQueue;

/**
 * Tracks click history and CPS (clicks per second) for both mouse buttons
 */
class ClicksComponent {

    private const HISTORY_SIZE = 20; // 1 second at 20 TPS
    private const INTERVAL_HISTORY_SIZE = 100; // Store last 100 click intervals for long-term consistency analysis
    private const MICRO_WINDOW_SIZE = 10; // Small window for burst detection
    private const BURST_GAP_THRESHOLD = 6; // Ticks without click to end a burst (0.3s)

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

    // Micro-window for burst detection (last 10 intervals only)
    /** @var CircularQueue<int> */
    private CircularQueue $leftMicroWindow;
    /** @var CircularQueue<int> */
    private CircularQueue $rightMicroWindow;

    // Burst session tracking
    private int $leftBurstClickCount = 0;
    private int $rightBurstClickCount = 0;
    private int $ticksSinceLeftClick = 0;
    private int $ticksSinceRightClick = 0;

    // Burst statistics for cross-burst comparison
    /** @var array<int, array{clicks: int, peak_cps: int, min_interval: int, max_interval: int, mean_interval: float, stddev: float, range: int}> */
    private array $leftBurstHistory = [];
    /** @var array<int, array{clicks: int, peak_cps: int, min_interval: int, max_interval: int, mean_interval: float, stddev: float, range: int}> */
    private array $rightBurstHistory = [];
    private const MAX_BURST_HISTORY = 10;

    // Current burst tracking
    private int $leftBurstPeakCPS = 0;
    private int $rightBurstPeakCPS = 0;
    private int $leftBurstMinInterval = PHP_INT_MAX;
    private int $leftBurstMaxInterval = 0;
    private int $rightBurstMinInterval = PHP_INT_MAX;
    private int $rightBurstMaxInterval = 0;

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
        $this->leftMicroWindow = new CircularQueue(self::MICRO_WINDOW_SIZE);
        $this->rightMicroWindow = new CircularQueue(self::MICRO_WINDOW_SIZE);

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
            $this->leftMicroWindow->push($this->delayLeft);

            // Update burst statistics
            if ($this->delayLeft < $this->leftBurstMinInterval) {
                $this->leftBurstMinInterval = $this->delayLeft;
            }
            if ($this->delayLeft > $this->leftBurstMaxInterval) {
                $this->leftBurstMaxInterval = $this->delayLeft;
            }
        }

        // Track burst click count and peak CPS
        $this->leftBurstClickCount++;
        if ($this->cpsLeft > $this->leftBurstPeakCPS) {
            $this->leftBurstPeakCPS = $this->cpsLeft;
        }

        $this->ticksSinceLeftClick = 0;
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
            $this->rightMicroWindow->push($this->delayRight);

            // Update burst statistics
            if ($this->delayRight < $this->rightBurstMinInterval) {
                $this->rightBurstMinInterval = $this->delayRight;
            }
            if ($this->delayRight > $this->rightBurstMaxInterval) {
                $this->rightBurstMaxInterval = $this->delayRight;
            }
        }

        // Track burst click count and peak CPS
        $this->rightBurstClickCount++;
        if ($this->cpsRight > $this->rightBurstPeakCPS) {
            $this->rightBurstPeakCPS = $this->cpsRight;
        }

        $this->ticksSinceRightClick = 0;
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

        // Track ticks since last click for burst detection
        $this->ticksSinceLeftClick++;
        $this->ticksSinceRightClick++;

        // Detect left burst end
        if ($this->ticksSinceLeftClick >= self::BURST_GAP_THRESHOLD && $this->leftBurstClickCount >= 3) {
            $this->finalizeLeftBurst();
        }

        // Detect right burst end
        if ($this->ticksSinceRightClick >= self::BURST_GAP_THRESHOLD && $this->rightBurstClickCount >= 3) {
            $this->finalizeRightBurst();
        }

        // Reset pending for next tick
        $this->pendingLeftClicks = 0;
        $this->pendingRightClicks = 0;
    }

    /**
     * Finalize a left click burst and store its statistics
     */
    private function finalizeLeftBurst(): void {
        // Only store bursts with meaningful data
        if ($this->leftBurstClickCount >= 3 && $this->leftBurstMaxInterval > 0) {
            // Calculate mean interval from micro window
            $intervals = $this->leftMicroWindow->toArray();
            $sum = 0.0;
            $count = count($intervals);
            if ($count > 0) {
                foreach ($intervals as $interval) {
                    $sum += (float) $interval;
                }
                $meanInterval = $sum / $count;

                // Calculate stddev for this burst
                $variance = 0.0;
                foreach ($intervals as $interval) {
                    $diff = (float) $interval - $meanInterval;
                    $variance += $diff * $diff;
                }
                $stdDev = $count > 1 ? sqrt($variance / $count) : 0.0;

                // Store burst data
                $this->leftBurstHistory[] = [
                    'clicks' => $this->leftBurstClickCount,
                    'peak_cps' => $this->leftBurstPeakCPS,
                    'min_interval' => $this->leftBurstMinInterval,
                    'max_interval' => $this->leftBurstMaxInterval,
                    'mean_interval' => $meanInterval,
                    'stddev' => $stdDev,
                    'range' => $this->leftBurstMaxInterval - $this->leftBurstMinInterval,
                ];

                // Keep only last N bursts
                if (count($this->leftBurstHistory) > self::MAX_BURST_HISTORY) {
                    array_shift($this->leftBurstHistory);
                }
            }
        }

        // Reset burst tracking
        $this->leftBurstClickCount = 0;
        $this->leftBurstPeakCPS = 0;
        $this->leftBurstMinInterval = PHP_INT_MAX;
        $this->leftBurstMaxInterval = 0;
        $this->leftMicroWindow->clear();
    }

    /**
     * Finalize a right click burst and store its statistics
     */
    private function finalizeRightBurst(): void {
        // Only store bursts with meaningful data
        if ($this->rightBurstClickCount >= 3 && $this->rightBurstMaxInterval > 0) {
            // Calculate mean interval from micro window
            $intervals = $this->rightMicroWindow->toArray();
            $sum = 0.0;
            $count = count($intervals);
            if ($count > 0) {
                foreach ($intervals as $interval) {
                    $sum += (float) $interval;
                }
                $meanInterval = $sum / $count;

                // Calculate stddev for this burst
                $variance = 0.0;
                foreach ($intervals as $interval) {
                    $diff = (float) $interval - $meanInterval;
                    $variance += $diff * $diff;
                }
                $stdDev = $count > 1 ? sqrt($variance / $count) : 0.0;

                // Store burst data
                $this->rightBurstHistory[] = [
                    'clicks' => $this->rightBurstClickCount,
                    'peak_cps' => $this->rightBurstPeakCPS,
                    'min_interval' => $this->rightBurstMinInterval,
                    'max_interval' => $this->rightBurstMaxInterval,
                    'mean_interval' => $meanInterval,
                    'stddev' => $stdDev,
                    'range' => $this->rightBurstMaxInterval - $this->rightBurstMinInterval,
                ];

                // Keep only last N bursts
                if (count($this->rightBurstHistory) > self::MAX_BURST_HISTORY) {
                    array_shift($this->rightBurstHistory);
                }
            }
        }

        // Reset burst tracking
        $this->rightBurstClickCount = 0;
        $this->rightBurstPeakCPS = 0;
        $this->rightBurstMinInterval = PHP_INT_MAX;
        $this->rightBurstMaxInterval = 0;
        $this->rightMicroWindow->clear();
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
     * Calculate kurtosis of left click intervals
     * Kurtosis measures "tailedness" - human clicks have heavier tails
     * Returns -999 if not enough samples
     */
    public function getLeftIntervalKurtosis(): float {
        return $this->calculateKurtosis($this->leftIntervals);
    }

    /**
     * Calculate kurtosis of right click intervals
     * Returns -999 if not enough samples
     */
    public function getRightIntervalKurtosis(): float {
        return $this->calculateKurtosis($this->rightIntervals);
    }

    /**
     * Calculate excess kurtosis of intervals
     * Formula: (1/n) * SUM[(xi - mean)^4] / (stdDev^4) - 3
     * @param CircularQueue<int> $queue
     * @return float Excess kurtosis, or -999 if insufficient samples
     */
    private function calculateKurtosis(CircularQueue $queue): float {
        $count = $queue->count();
        if ($count < 20) {
            return -999.0; // Need more samples for reliable kurtosis
        }

        $intervals = $queue->toArray();

        // Calculate mean
        $sum = 0.0;
        foreach ($intervals as $interval) {
            $sum += (float) $interval;
        }
        $mean = $sum / $count;

        // Calculate variance and fourth moment
        $m2 = 0.0; // Second moment (variance)
        $m4 = 0.0; // Fourth moment
        foreach ($intervals as $interval) {
            $diff = (float) $interval - $mean;
            $m2 += $diff * $diff;
            $m4 += $diff * $diff * $diff * $diff;
        }
        $m2 /= $count;
        $m4 /= $count;

        if ($m2 < 0.0001) {
            return -999.0; // Avoid division by near-zero
        }

        // Excess kurtosis (subtract 3 so normal distribution = 0)
        return ($m4 / ($m2 * $m2)) - 3.0;
    }

    /**
     * Calculate skewness of left click intervals
     * Skewness measures asymmetry - human clicks are typically right-skewed
     * Returns -999 if not enough samples
     */
    public function getLeftIntervalSkewness(): float {
        return $this->calculateSkewness($this->leftIntervals);
    }

    /**
     * Calculate skewness of right click intervals
     * Returns -999 if not enough samples
     */
    public function getRightIntervalSkewness(): float {
        return $this->calculateSkewness($this->rightIntervals);
    }

    /**
     * Calculate skewness of intervals
     * Formula: (1/n) * SUM[(xi - mean)^3] / (stdDev^3)
     * @param CircularQueue<int> $queue
     * @return float Skewness, or -999 if insufficient samples
     */
    private function calculateSkewness(CircularQueue $queue): float {
        $count = $queue->count();
        if ($count < 20) {
            return -999.0; // Need more samples for reliable skewness
        }

        $intervals = $queue->toArray();

        // Calculate mean
        $sum = 0.0;
        foreach ($intervals as $interval) {
            $sum += (float) $interval;
        }
        $mean = $sum / $count;

        // Calculate variance and third moment
        $m2 = 0.0; // Second moment
        $m3 = 0.0; // Third moment
        foreach ($intervals as $interval) {
            $diff = (float) $interval - $mean;
            $m2 += $diff * $diff;
            $m3 += $diff * $diff * $diff;
        }
        $m2 /= $count;
        $m3 /= $count;

        $stdDev = sqrt($m2);
        if ($stdDev < 0.0001) {
            return -999.0; // Avoid division by near-zero
        }

        return $m3 / ($stdDev * $stdDev * $stdDev);
    }

    /**
     * Calculate Shannon entropy of left click intervals
     * Higher entropy = more random (human-like)
     * Returns -1 if not enough samples
     */
    public function getLeftIntervalEntropy(): float {
        return $this->calculateEntropy($this->leftIntervals);
    }

    /**
     * Calculate Shannon entropy of right click intervals
     * Returns -1 if not enough samples
     */
    public function getRightIntervalEntropy(): float {
        return $this->calculateEntropy($this->rightIntervals);
    }

    /**
     * Calculate Shannon entropy of intervals using 8 bins
     * Formula: H(X) = -SUM[P(xi) * log2(P(xi))]
     * @param CircularQueue<int> $queue
     * @return float Entropy in bits, or -1 if insufficient samples
     */
    private function calculateEntropy(CircularQueue $queue): float {
        $count = $queue->count();
        if ($count < 20) {
            return -1.0;
        }

        $intervals = $queue->toArray();

        // Find min/max for binning
        $min = PHP_INT_MAX;
        $max = PHP_INT_MIN;
        foreach ($intervals as $interval) {
            $val = (int) $interval;
            if ($val < $min) $min = $val;
            if ($val > $max) $max = $val;
        }

        if ($max <= $min) {
            return 0.0; // All same value = zero entropy
        }

        // Create 8 bins
        $numBins = 8;
        $binWidth = ($max - $min + 1) / $numBins;
        $bins = array_fill(0, $numBins, 0);

        foreach ($intervals as $interval) {
            $binIndex = (int) min($numBins - 1, floor(((int) $interval - $min) / $binWidth));
            $bins[$binIndex]++;
        }

        // Calculate entropy
        $entropy = 0.0;
        foreach ($bins as $binCount) {
            if ($binCount > 0) {
                $p = $binCount / $count;
                $entropy -= $p * log($p, 2);
            }
        }

        return $entropy;
    }

    /**
     * Calculate runs test Z-score for left click intervals
     * Tests randomness of above/below median sequence
     * Returns -999 if not enough samples
     */
    public function getLeftIntervalRunsZ(): float {
        return $this->calculateRunsTest($this->leftIntervals);
    }

    /**
     * Calculate runs test Z-score for right click intervals
     * Returns -999 if not enough samples
     */
    public function getRightIntervalRunsZ(): float {
        return $this->calculateRunsTest($this->rightIntervals);
    }

    /**
     * Calculate Wald-Wolfowitz runs test Z-score
     * Tests if above/below median values alternate randomly
     * @param CircularQueue<int> $queue
     * @return float Z-score, or -999 if insufficient samples
     */
    private function calculateRunsTest(CircularQueue $queue): float {
        $count = $queue->count();
        if ($count < 20) {
            return -999.0;
        }

        $intervals = $queue->toArray();

        // Find median
        $sorted = $intervals;
        sort($sorted);
        $median = (float) $sorted[(int) floor($count / 2)];

        // Count runs and elements above/below median
        $n1 = 0; // Count above median
        $n2 = 0; // Count below median
        $runs = 1;
        $lastAbove = ((float) $intervals[0]) > $median;

        if ($lastAbove) {
            $n1++;
        } else {
            $n2++;
        }

        for ($i = 1; $i < $count; $i++) {
            $currentAbove = ((float) $intervals[$i]) > $median;
            if ($currentAbove) {
                $n1++;
            } else {
                $n2++;
            }
            if ($currentAbove !== $lastAbove) {
                $runs++;
                $lastAbove = $currentAbove;
            }
        }

        if ($n1 < 2 || $n2 < 2) {
            return -999.0; // Not enough variation
        }

        // Calculate expected runs and standard deviation
        $expectedRuns = (2.0 * $n1 * $n2) / ($n1 + $n2) + 1;
        $variance = (2.0 * $n1 * $n2 * (2.0 * $n1 * $n2 - $n1 - $n2)) /
                    (($n1 + $n2) * ($n1 + $n2) * ($n1 + $n2 - 1));

        if ($variance < 0.0001) {
            return -999.0;
        }

        $stdDev = sqrt($variance);
        return ($runs - $expectedRuns) / $stdDev;
    }

    /**
     * Calculate lag-1 autocorrelation for left click intervals
     * Measures if sequential intervals are correlated
     * Returns -999 if not enough samples
     */
    public function getLeftIntervalAutocorrelation(): float {
        return $this->calculateAutocorrelation($this->leftIntervals);
    }

    /**
     * Calculate lag-1 autocorrelation for right click intervals
     * Returns -999 if not enough samples
     */
    public function getRightIntervalAutocorrelation(): float {
        return $this->calculateAutocorrelation($this->rightIntervals);
    }

    /**
     * Calculate lag-1 autocorrelation of intervals
     * Formula: r1 = SUM[(xi - mean)(xi+1 - mean)] / SUM[(xi - mean)^2]
     * @param CircularQueue<int> $queue
     * @return float Autocorrelation (-1 to 1), or -999 if insufficient samples
     */
    private function calculateAutocorrelation(CircularQueue $queue): float {
        $count = $queue->count();
        if ($count < 20) {
            return -999.0;
        }

        $intervals = $queue->toArray();

        // Calculate mean
        $sum = 0.0;
        foreach ($intervals as $interval) {
            $sum += (float) $interval;
        }
        $mean = $sum / $count;

        // Calculate autocorrelation
        $numerator = 0.0;
        $denominator = 0.0;

        for ($i = 0; $i < $count - 1; $i++) {
            $diff1 = (float) $intervals[$i] - $mean;
            $diff2 = (float) $intervals[$i + 1] - $mean;
            $numerator += $diff1 * $diff2;
            $denominator += $diff1 * $diff1;
        }

        // Add last element to denominator
        $lastDiff = (float) $intervals[$count - 1] - $mean;
        $denominator += $lastDiff * $lastDiff;

        if ($denominator < 0.0001) {
            return -999.0;
        }

        return $numerator / $denominator;
    }

    /**
     * Get the left click burst history for cross-burst analysis
     * @return array<int, array{clicks: int, peak_cps: int, min_interval: int, max_interval: int, mean_interval: float, stddev: float, range: int}>
     */
    public function getLeftBurstHistory(): array {
        return $this->leftBurstHistory;
    }

    /**
     * Get the right click burst history for cross-burst analysis
     * @return array<int, array{clicks: int, peak_cps: int, min_interval: int, max_interval: int, mean_interval: float, stddev: float, range: int}>
     */
    public function getRightBurstHistory(): array {
        return $this->rightBurstHistory;
    }

    /**
     * Get the left micro-window queue for small-sample analysis
     * @return CircularQueue<int>
     */
    public function getLeftMicroWindow(): CircularQueue {
        return $this->leftMicroWindow;
    }

    /**
     * Get the right micro-window queue for small-sample analysis
     * @return CircularQueue<int>
     */
    public function getRightMicroWindow(): CircularQueue {
        return $this->rightMicroWindow;
    }

    /**
     * Get the current left burst click count (in progress burst)
     */
    public function getLeftBurstClickCount(): int {
        return $this->leftBurstClickCount;
    }

    /**
     * Get the current right burst click count (in progress burst)
     */
    public function getRightBurstClickCount(): int {
        return $this->rightBurstClickCount;
    }

    /**
     * Get ticks since last left click
     */
    public function getTicksSinceLeftClick(): int {
        return $this->ticksSinceLeftClick;
    }

    /**
     * Get ticks since last right click
     */
    public function getTicksSinceRightClick(): int {
        return $this->ticksSinceRightClick;
    }

    /**
     * Reset all click tracking (Go: resetAndPropagateLeft/Right)
     */
    public function reset(): void {
        $this->clicksLeft->clear();
        $this->clicksRight->clear();
        $this->leftIntervals->clear();
        $this->rightIntervals->clear();
        $this->leftMicroWindow->clear();
        $this->rightMicroWindow->clear();
        $this->delayLeft = 0;
        $this->delayRight = 0;
        $this->lastLeftClick = 0;
        $this->lastRightClick = 0;
        $this->cpsLeft = 0;
        $this->cpsRight = 0;
        $this->pendingLeftClicks = 0;
        $this->pendingRightClicks = 0;

        // Reset burst tracking
        $this->leftBurstClickCount = 0;
        $this->rightBurstClickCount = 0;
        $this->ticksSinceLeftClick = 0;
        $this->ticksSinceRightClick = 0;
        $this->leftBurstHistory = [];
        $this->rightBurstHistory = [];
        $this->leftBurstPeakCPS = 0;
        $this->rightBurstPeakCPS = 0;
        $this->leftBurstMinInterval = PHP_INT_MAX;
        $this->leftBurstMaxInterval = 0;
        $this->rightBurstMinInterval = PHP_INT_MAX;
        $this->rightBurstMaxInterval = 0;

        // Re-initialize with zeros
        for ($i = 0; $i < self::HISTORY_SIZE; $i++) {
            $this->clicksLeft->push(0);
            $this->clicksRight->push(0);
        }
    }
}
