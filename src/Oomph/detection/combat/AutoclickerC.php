<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * AutoclickerC Detection
 *
 * Purpose: Detect autoclickers using advanced statistical distribution analysis
 * that catches "randomized" autoclickers which evade simple stddev/CV checks.
 *
 * Detection methods:
 * - Kurtosis: Human clicks have leptokurtic (heavy-tailed) distribution (>1.5)
 *   Autoclickers produce platykurtic (flat) distribution (<0.5)
 * - Skewness: Human clicks are right-skewed (0.5-3.0), autoclickers are symmetric (<0.3)
 * - Entropy: Human clicks have high entropy (2.5-3.0 bits), autoclickers lower (<2.0)
 *
 * These methods detect artificial randomness patterns that simple variance checks miss.
 */
class AutoclickerC extends Detection {

    /** Minimum samples needed for reliable analysis */
    private const MIN_SAMPLES = 50;

    /** Minimum CPS to trigger check */
    private const MIN_CPS_THRESHOLD = 8;

    /** Kurtosis thresholds - humans have leptokurtic (heavy-tailed) distributions */
    private const KURTOSIS_SUSPICIOUS = -1.5;  // Below this is suspicious (relaxed from -0.5)
    private const KURTOSIS_DEFINITE = -2.5;    // Below this is almost certainly autoclicker

    /** Skewness thresholds - humans have right-skewed distributions */
    private const SKEWNESS_SUSPICIOUS = 0.10;  // Below this is suspicious
    private const SKEWNESS_DEFINITE = 0.03;    // Below this is almost certainly autoclicker

    /** Entropy thresholds (8 bins, max ~3 bits) - humans have higher entropy */
    private const ENTROPY_SUSPICIOUS = 0.8;    // Below this is suspicious (relaxed from 1.2)
    private const ENTROPY_DEFINITE = 0.4;      // Below this is almost certainly autoclicker

    /** Track consecutive suspicious checks for pattern detection */
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
        return "AutoclickerC";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 8.0;
    }

    /**
     * Process distribution analysis for click patterns
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

        // Check left click distribution
        if ($leftClick) {
            $this->checkDistribution(
                $player,
                $clicks->getLeftCPS(),
                $clicks->getLeftIntervalKurtosis(),
                $clicks->getLeftIntervalSkewness(),
                $clicks->getLeftIntervalEntropy(),
                $clicks->getLeftIntervals()->count(),
                'left'
            );
        }

        // Check right click distribution
        if ($rightClick) {
            $this->checkDistribution(
                $player,
                $clicks->getRightCPS(),
                $clicks->getRightIntervalKurtosis(),
                $clicks->getRightIntervalSkewness(),
                $clicks->getRightIntervalEntropy(),
                $clicks->getRightIntervals()->count(),
                'right'
            );
        }
    }

    /**
     * Check click distribution for a specific click type
     *
     * @param OomphPlayer $player The player to check
     * @param int $cps Current clicks per second
     * @param float $kurtosis Excess kurtosis of intervals
     * @param float $skewness Skewness of intervals
     * @param float $entropy Shannon entropy of intervals
     * @param int $samples Number of samples
     * @param string $clickType 'left' or 'right'
     */
    private function checkDistribution(
        OomphPlayer $player,
        int $cps,
        float $kurtosis,
        float $skewness,
        float $entropy,
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
        if ($kurtosis <= -900 || $skewness <= -900 || $entropy < 0) {
            return;
        }

        // Calculate suspicion scores (0.0 = normal, 1.0 = highly suspicious)
        $kurtosisScore = $this->calculateKurtosisScore($kurtosis);
        $skewnessScore = $this->calculateSkewnessScore($skewness);
        $entropyScore = $this->calculateEntropyScore($entropy);

        // Weighted combination (kurtosis and entropy are most reliable)
        $totalScore = ($kurtosisScore * 0.35) + ($skewnessScore * 0.25) + ($entropyScore * 0.40);

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
                'kurtosis' => round($kurtosis, 3),
                'kurtosis_score' => round($kurtosisScore, 3),
                'skewness' => round($skewness, 3),
                'skewness_score' => round($skewnessScore, 3),
                'entropy' => round($entropy, 3),
                'entropy_score' => round($entropyScore, 3),
                'total_score' => round($totalScore, 3),
                'samples' => $samples,
                'pattern_count' => $suspiciousCount
            ]);
        } else {
            // Good distribution - decay buffer
            $this->pass(0.08);
        }
    }

    /**
     * Calculate suspicion score from kurtosis
     * Human clicks: >0 (leptokurtic), Autoclicker: <-0.5 (platykurtic)
     */
    private function calculateKurtosisScore(float $kurtosis): float {
        if ($kurtosis <= self::KURTOSIS_DEFINITE) {
            return 1.0;
        } elseif ($kurtosis <= self::KURTOSIS_SUSPICIOUS) {
            // Linear interpolation between suspicious and definite
            return 0.5 + 0.5 * (self::KURTOSIS_SUSPICIOUS - $kurtosis) / (self::KURTOSIS_SUSPICIOUS - self::KURTOSIS_DEFINITE);
        }
        return 0.0;
    }

    /**
     * Calculate suspicion score from skewness
     * Human clicks: >0.3 (right-skewed), Autoclicker: <0.15 (symmetric)
     */
    private function calculateSkewnessScore(float $skewness): float {
        $absSkewness = abs($skewness);
        if ($absSkewness <= self::SKEWNESS_DEFINITE) {
            return 1.0;
        } elseif ($absSkewness <= self::SKEWNESS_SUSPICIOUS) {
            return 0.5 + 0.5 * (self::SKEWNESS_SUSPICIOUS - $absSkewness) / (self::SKEWNESS_SUSPICIOUS - self::SKEWNESS_DEFINITE);
        }
        return 0.0;
    }

    /**
     * Calculate suspicion score from entropy
     * Human clicks: >1.5 bits, Autoclicker: <1.2 bits
     */
    private function calculateEntropyScore(float $entropy): float {
        if ($entropy <= self::ENTROPY_DEFINITE) {
            return 1.0;
        } elseif ($entropy <= self::ENTROPY_SUSPICIOUS) {
            return 0.5 + 0.5 * (self::ENTROPY_SUSPICIOUS - $entropy) / (self::ENTROPY_SUSPICIOUS - self::ENTROPY_DEFINITE);
        }
        return 0.0;
    }
}
