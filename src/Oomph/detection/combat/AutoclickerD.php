<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * AutoclickerD Detection
 *
 * Purpose: Detect autoclickers using sequence analysis methods that identify
 * non-random patterns in click timing that "randomized" autoclickers exhibit.
 *
 * Detection methods:
 * - Runs Test (Wald-Wolfowitz): Tests if above/below median values alternate randomly
 *   Autoclickers often produce too few or too many runs (|Z| > 2.0)
 * - Autocorrelation: Measures if sequential intervals are correlated
 *   Human clicks are independent (|r| < 0.3), autoclickers show patterns (|r| > 0.4)
 * - Coefficient of Variation: Enhanced check for very low CV with high sample count
 *
 * These methods detect sequential patterns that distribution-based checks miss.
 */
class AutoclickerD extends Detection {

    /** Minimum samples needed for reliable analysis */
    private const MIN_SAMPLES = 50;

    /** Minimum CPS to trigger check */
    private const MIN_CPS_THRESHOLD = 8;

    /** Runs test Z-score thresholds */
    private const RUNS_Z_SUSPICIOUS = 5.5;   // |Z| above this is suspicious (relaxed from 3.0)
    private const RUNS_Z_DEFINITE = 7.0;     // |Z| above this is almost certain

    /** Autocorrelation thresholds - humans have low autocorrelation */
    private const AUTOCORR_SUSPICIOUS = 0.65;  // |r| above this is suspicious (relaxed from 0.5)
    private const AUTOCORR_DEFINITE = 0.8;     // |r| above this is almost certain

    /** CV threshold for very consistent clicking (enhanced from AutoclickerB) */
    private const CV_THRESHOLD = 0.04;  // 4% CV is very suspicious at high sample counts

    /** Track consecutive suspicious checks */
    private int $leftSuspiciousCount = 0;
    private int $rightSuspiciousCount = 0;

    /** Threshold for consecutive suspicious checks before flagging */
    private const SUSPICIOUS_COUNT_THRESHOLD = 10;

    public function __construct() {
        parent::__construct(
            maxBuffer: 8.0,
            failBuffer: 4.0,
            trustDuration: 400 // 20 seconds to build trust
        );
    }

    public function getName(): string {
        return "AutoclickerD";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 8.0;
    }

    /**
     * Process sequence analysis for click patterns
     *
     * @param OomphPlayer $player The player to check
     * @param bool $leftClick Whether left click occurred this tick
     * @param bool $rightClick Whether right click occurred this tick
     */
    public function process(OomphPlayer $player, bool $leftClick, bool $rightClick = false): void {
        if (!$leftClick && !$rightClick) {
            return;
        }

        $clicks = $player->getClicksComponent();

        // Check left click sequence
        if ($leftClick) {
            $this->checkSequence(
                $player,
                $clicks->getLeftCPS(),
                $clicks->getLeftIntervalRunsZ(),
                $clicks->getLeftIntervalAutocorrelation(),
                $clicks->getLeftIntervalStdDev(),
                $clicks->getLeftIntervalMean(),
                $clicks->getLeftIntervals()->count(),
                'left'
            );
        }

        // Check right click sequence
        if ($rightClick) {
            $this->checkSequence(
                $player,
                $clicks->getRightCPS(),
                $clicks->getRightIntervalRunsZ(),
                $clicks->getRightIntervalAutocorrelation(),
                $clicks->getRightIntervalStdDev(),
                $clicks->getRightIntervalMean(),
                $clicks->getRightIntervals()->count(),
                'right'
            );
        }
    }

    /**
     * Check click sequence for a specific click type
     *
     * @param OomphPlayer $player The player to check
     * @param int $cps Current clicks per second
     * @param float $runsZ Runs test Z-score
     * @param float $autocorr Lag-1 autocorrelation
     * @param float $stdDev Standard deviation of intervals
     * @param float $mean Mean of intervals
     * @param int $samples Number of samples
     * @param string $clickType 'left' or 'right'
     */
    private function checkSequence(
        OomphPlayer $player,
        int $cps,
        float $runsZ,
        float $autocorr,
        float $stdDev,
        float $mean,
        int $samples,
        string $clickType
    ): void {
        // Need minimum CPS to check
        if ($cps < self::MIN_CPS_THRESHOLD) {
            $this->pass(0.05);
            return;
        }

        // Need enough samples for reliable analysis
        if ($samples < self::MIN_SAMPLES) {
            return;
        }

        // Skip if any metric is invalid
        if ($runsZ <= -900 || $autocorr <= -900 || $stdDev < 0 || $mean <= 0) {
            return;
        }

        // Calculate CV for additional check
        $cv = $stdDev / $mean;

        // Calculate suspicion scores (0.0 = normal, 1.0 = highly suspicious)
        $runsScore = $this->calculateRunsScore($runsZ);
        $autocorrScore = $this->calculateAutocorrScore($autocorr);
        $cvScore = $this->calculateCVScore($cv, $samples);

        // Weighted combination (runs and autocorr are primary, CV is supporting)
        $totalScore = ($runsScore * 0.40) + ($autocorrScore * 0.40) + ($cvScore * 0.20);

        // Track consecutive suspicious checks
        $suspiciousCount = $clickType === 'left' ? $this->leftSuspiciousCount : $this->rightSuspiciousCount;

        if ($totalScore >= 0.5) {
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

        // Flag if consistently suspicious
        $shouldFlag = false;
        $reason = '';

        if ($totalScore >= 0.8) {
            // Very high score - immediate flag
            $shouldFlag = true;
            $reason = 'high_score';
        } elseif ($totalScore >= 0.5 && $suspiciousCount >= self::SUSPICIOUS_COUNT_THRESHOLD) {
            // Moderate score but consistent over time
            $shouldFlag = true;
            $reason = 'consistent_pattern';
        }

        if ($shouldFlag) {
            $this->fail($player, 1.0, [
                'click_type' => $clickType,
                'reason' => $reason,
                'cps' => $cps,
                'runs_z' => round($runsZ, 3),
                'runs_score' => round($runsScore, 3),
                'autocorr' => round($autocorr, 3),
                'autocorr_score' => round($autocorrScore, 3),
                'cv' => round($cv, 4),
                'cv_score' => round($cvScore, 3),
                'total_score' => round($totalScore, 3),
                'samples' => $samples,
                'pattern_count' => $suspiciousCount
            ]);
        } else {
            // Good sequence - decay buffer
            $this->pass(0.08);
        }
    }

    /**
     * Calculate suspicion score from runs test Z-score
     * Normal: |Z| < 2.5, Suspicious: |Z| > 3.0, Definite: |Z| > 4.0
     */
    private function calculateRunsScore(float $runsZ): float {
        $absZ = abs($runsZ);
        if ($absZ >= self::RUNS_Z_DEFINITE) {
            return 1.0;
        } elseif ($absZ >= self::RUNS_Z_SUSPICIOUS) {
            return 0.5 + 0.5 * ($absZ - self::RUNS_Z_SUSPICIOUS) / (self::RUNS_Z_DEFINITE - self::RUNS_Z_SUSPICIOUS);
        }
        return 0.0;
    }

    /**
     * Calculate suspicion score from autocorrelation
     * Normal: |r| < 0.4, Suspicious: |r| > 0.5, Definite: |r| > 0.7
     */
    private function calculateAutocorrScore(float $autocorr): float {
        $absR = abs($autocorr);
        if ($absR >= self::AUTOCORR_DEFINITE) {
            return 1.0;
        } elseif ($absR >= self::AUTOCORR_SUSPICIOUS) {
            return 0.5 + 0.5 * ($absR - self::AUTOCORR_SUSPICIOUS) / (self::AUTOCORR_DEFINITE - self::AUTOCORR_SUSPICIOUS);
        }
        return 0.0;
    }

    /**
     * Calculate suspicion score from coefficient of variation
     * Enhanced check: Very low CV with high sample count is suspicious
     */
    private function calculateCVScore(float $cv, int $samples): float {
        // Only suspicious with sufficient samples
        if ($samples < 70) {
            return 0.0;
        }

        if ($cv <= 0.03) {
            return 1.0;  // Extremely consistent - almost certainly automated
        } elseif ($cv <= self::CV_THRESHOLD) {
            return 0.5 + 0.5 * (self::CV_THRESHOLD - $cv) / (self::CV_THRESHOLD - 0.03);
        }
        return 0.0;
    }
}
