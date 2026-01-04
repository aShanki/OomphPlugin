<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * AutoclickerE Detection - Burst Pattern Analysis
 *
 * Purpose: Detect burst autoclickers by analyzing cross-burst consistency.
 * Even randomized autoclickers produce suspiciously similar bursts.
 *
 * Detection methods:
 * - Cross-burst CPS consistency: Peak CPS too similar across bursts
 * - Cross-burst interval consistency: Mean interval too similar across bursts
 * - Variance-of-variance: The variance itself is too consistent (fake randomness)
 * - Range consistency: Interval range (max-min) too similar across bursts
 *
 * Human bursts have natural variation between them - different peak CPS,
 * different timing patterns. Autoclickers produce carbon-copy bursts.
 */
class AutoclickerE extends Detection {

    /** Minimum bursts needed for cross-burst analysis */
    private const MIN_BURSTS = 3;

    /** Minimum clicks per burst to count it */
    private const MIN_BURST_CLICKS = 3;

    /**
     * Coefficient of Variation thresholds for cross-burst metrics
     * CV = stddev / mean - measures relative consistency
     * Lower CV = more suspicious consistency across bursts
     */
    private const PEAK_CPS_CV_THRESHOLD = 0.08;       // Peak CPS variance across bursts
    private const MEAN_INTERVAL_CV_THRESHOLD = 0.06;  // Mean interval variance across bursts
    private const STDDEV_CV_THRESHOLD = 0.15;         // Variance-of-variance (fake randomness)
    private const RANGE_CV_THRESHOLD = 0.12;          // Range consistency across bursts

    /** Track consecutive suspicious checks */
    private int $leftSuspiciousCount = 0;
    private int $rightSuspiciousCount = 0;

    /** Track last analyzed burst count to avoid re-analyzing same bursts */
    private int $lastLeftBurstCount = 0;
    private int $lastRightBurstCount = 0;

    /** Threshold for consecutive suspicious analyses before flagging */
    private const SUSPICIOUS_COUNT_THRESHOLD = 3;

    public function __construct() {
        parent::__construct(
            maxBuffer: 6.0,
            failBuffer: 3.0,
            trustDuration: 300 // 15 seconds to build trust
        );
    }

    public function getName(): string {
        return "AutoclickerE";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 8.0;
    }

    /**
     * Process burst analysis when a new burst is recorded
     *
     * @param OomphPlayer $player The player to check
     * @param bool $leftClick Whether left click occurred this tick
     * @param bool $rightClick Whether right click occurred this tick
     */
    public function process(OomphPlayer $player, bool $leftClick, bool $rightClick = false): void {
        $clicks = $player->getClicksComponent();

        // Check left bursts - only analyze when NEW bursts have been added
        $leftBurstHistory = $clicks->getLeftBurstHistory();
        $leftBurstCount = count($leftBurstHistory);
        if ($leftBurstCount >= self::MIN_BURSTS && $leftBurstCount > $this->lastLeftBurstCount) {
            $this->lastLeftBurstCount = $leftBurstCount;
            $this->analyzeBurstConsistency($player, $leftBurstHistory, 'left');
        }

        // Check right bursts - only analyze when NEW bursts have been added
        $rightBurstHistory = $clicks->getRightBurstHistory();
        $rightBurstCount = count($rightBurstHistory);
        if ($rightBurstCount >= self::MIN_BURSTS && $rightBurstCount > $this->lastRightBurstCount) {
            $this->lastRightBurstCount = $rightBurstCount;
            $this->analyzeBurstConsistency($player, $rightBurstHistory, 'right');
        }
    }

    /**
     * Analyze cross-burst consistency
     *
     * @param OomphPlayer $player The player to check
     * @param array<int, array{clicks: int, peak_cps: int, min_interval: int, max_interval: int, mean_interval: float, stddev: float, range: int}> $burstHistory
     * @param string $clickType 'left' or 'right'
     */
    private function analyzeBurstConsistency(OomphPlayer $player, array $burstHistory, string $clickType): void {
        // Filter to meaningful bursts only
        $validBursts = array_filter($burstHistory, fn($b) => $b['clicks'] >= self::MIN_BURST_CLICKS);

        if (count($validBursts) < self::MIN_BURSTS) {
            return;
        }

        // Take only the most recent bursts for analysis
        $recentBursts = array_slice($validBursts, -self::MIN_BURSTS);

        // Extract metrics from bursts
        $peakCpsValues = array_column($recentBursts, 'peak_cps');
        $meanIntervalValues = array_column($recentBursts, 'mean_interval');
        $stddevValues = array_column($recentBursts, 'stddev');
        $rangeValues = array_column($recentBursts, 'range');

        // Calculate CV (coefficient of variation) for each metric
        $peakCpsCV = $this->calculateCV($peakCpsValues);
        $meanIntervalCV = $this->calculateCV($meanIntervalValues);
        $stddevCV = $this->calculateCV($stddevValues);
        $rangeCV = $this->calculateCV($rangeValues);

        // Calculate suspicion scores
        $peakCpsScore = $this->calculateSuspicionScore($peakCpsCV, self::PEAK_CPS_CV_THRESHOLD, 0.02);
        $meanIntervalScore = $this->calculateSuspicionScore($meanIntervalCV, self::MEAN_INTERVAL_CV_THRESHOLD, 0.02);
        $stddevScore = $this->calculateSuspicionScore($stddevCV, self::STDDEV_CV_THRESHOLD, 0.05);
        $rangeScore = $this->calculateSuspicionScore($rangeCV, self::RANGE_CV_THRESHOLD, 0.04);

        // Weighted total score
        // Peak CPS and mean interval are most reliable indicators
        // Stddev score catches "randomized" autoclickers specifically
        $totalScore = ($peakCpsScore * 0.30) + ($meanIntervalScore * 0.30) + ($stddevScore * 0.25) + ($rangeScore * 0.15);

        // Track consecutive suspicious checks
        $suspiciousCount = $clickType === 'left' ? $this->leftSuspiciousCount : $this->rightSuspiciousCount;

        if ($totalScore >= 0.4) {
            $suspiciousCount++;
        } else {
            $suspiciousCount = max(0, $suspiciousCount - 1);
        }

        // Update tracking
        if ($clickType === 'left') {
            $this->leftSuspiciousCount = $suspiciousCount;
        } else {
            $this->rightSuspiciousCount = $suspiciousCount;
        }

        // Determine if we should flag
        $shouldFlag = false;
        $reason = '';

        if ($totalScore >= 0.75) {
            // Very high score - immediate flag
            $shouldFlag = true;
            $reason = 'high_consistency';
        } elseif ($totalScore >= 0.5 && $suspiciousCount >= self::SUSPICIOUS_COUNT_THRESHOLD) {
            // Moderate score but consistent over time
            $shouldFlag = true;
            $reason = 'sustained_pattern';
        }

        if ($shouldFlag) {
            $this->fail($player, 1.0, [
                'click_type' => $clickType,
                'reason' => $reason,
                'bursts_analyzed' => count($recentBursts),
                'peak_cps_cv' => round($peakCpsCV, 4),
                'peak_cps_score' => round($peakCpsScore, 3),
                'mean_interval_cv' => round($meanIntervalCV, 4),
                'mean_interval_score' => round($meanIntervalScore, 3),
                'stddev_cv' => round($stddevCV, 4),
                'stddev_score' => round($stddevScore, 3),
                'range_cv' => round($rangeCV, 4),
                'range_score' => round($rangeScore, 3),
                'total_score' => round($totalScore, 3),
                'pattern_count' => $suspiciousCount
            ]);
        } else {
            // Good variation between bursts - decay buffer
            $this->pass(0.1);
        }
    }

    /**
     * Calculate Coefficient of Variation for an array of values
     * CV = stddev / mean
     *
     * @param array<int|float> $values
     * @return float CV value, or 999 if calculation fails
     */
    private function calculateCV(array $values): float {
        $count = count($values);
        if ($count < 2) {
            return 999.0; // Invalid - return high value (not suspicious)
        }

        // Calculate mean
        $sum = 0.0;
        foreach ($values as $value) {
            $sum += (float) $value;
        }
        $mean = $sum / $count;

        if ($mean < 0.001) {
            return 999.0; // Avoid division by near-zero
        }

        // Calculate stddev
        $variance = 0.0;
        foreach ($values as $value) {
            $diff = (float) $value - $mean;
            $variance += $diff * $diff;
        }
        $stddev = sqrt($variance / $count);

        return $stddev / $mean;
    }

    /**
     * Calculate suspicion score based on CV value
     * Lower CV = more suspicious (too consistent)
     *
     * @param float $cv The coefficient of variation
     * @param float $suspiciousThreshold Below this is suspicious (0.5 score)
     * @param float $definiteThreshold Below this is definite (1.0 score)
     * @return float Suspicion score 0.0 to 1.0
     */
    private function calculateSuspicionScore(float $cv, float $suspiciousThreshold, float $definiteThreshold): float {
        if ($cv >= 999.0) {
            return 0.0; // Invalid data
        }

        if ($cv <= $definiteThreshold) {
            return 1.0;
        } elseif ($cv <= $suspiciousThreshold) {
            // Linear interpolation
            return 0.5 + 0.5 * ($suspiciousThreshold - $cv) / ($suspiciousThreshold - $definiteThreshold);
        }

        return 0.0;
    }

    /**
     * Reset detection state when player resets
     */
    public function resetState(): void {
        $this->leftSuspiciousCount = 0;
        $this->rightSuspiciousCount = 0;
        $this->lastLeftBurstCount = 0;
        $this->lastRightBurstCount = 0;
    }
}
