<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * AutoclickerB Detection
 *
 * Purpose: Detect autoclickers by analyzing click timing consistency over long periods
 * Method: Calculate standard deviation and coefficient of variation of click intervals
 *
 * Human clicks have natural variance - they are not perfectly timed.
 * Autoclickers produce suspiciously consistent intervals (low std dev).
 *
 * Detection logic:
 * - Tracks up to 100 click intervals for long-term analysis
 * - Coefficient of Variation (CV) = stddev / mean
 * - Low CV (<0.12) with high CPS (>10) indicates robotic clicking
 * - Very low std dev (<0.25) alone is suspicious at any CPS
 * - Also flags if std dev is suspiciously constant over time
 *
 * Tracks both left clicks (combat) and right clicks (block placement).
 */
class AutoclickerB extends Detection {

    /** Minimum samples needed for reliable analysis */
    private const MIN_SAMPLES = 20;

    /** Minimum CPS to trigger consistency check */
    private const MIN_CPS_THRESHOLD = 2;

    /** Coefficient of variation threshold - below this is suspicious */
    private const CV_THRESHOLD = 0.08;

    /** Absolute std dev threshold - below this is always suspicious */
    private const ABSOLUTE_STDDEV_THRESHOLD = 0.15;

    /** Std dev threshold for mobile (more lenient due to touch mechanics) */
    private const ABSOLUTE_STDDEV_THRESHOLD_MOBILE = 0.10;

    public function __construct() {
        parent::__construct(
            maxBuffer: 6.0,
            failBuffer: 3.0,
            trustDuration: 400 // 20 seconds to build trust
        );
    }

    public function getName(): string {
        return "AutoclickerB";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 10.0;
    }

    /**
     * Process click consistency check for both left and right clicks
     *
     * @param OomphPlayer $player The player to check
     * @param bool $leftClick Whether left click occurred this tick
     * @param bool $rightClick Whether right click occurred this tick
     */
    public function process(OomphPlayer $player, bool $leftClick, bool $rightClick = false): void {
        if (!$leftClick && !$rightClick) {
            return; // Only check on click events
        }

        $clicks = $player->getClicksComponent();
        $isMobile = $player->isMobileDevice();
        $absThreshold = $isMobile ? self::ABSOLUTE_STDDEV_THRESHOLD_MOBILE : self::ABSOLUTE_STDDEV_THRESHOLD;

        // Check left click consistency
        if ($leftClick) {
            $this->checkClickConsistency(
                $player,
                $clicks->getLeftCPS(),
                $clicks->getLeftIntervalStdDev(),
                $clicks->getLeftIntervalMean(),
                $clicks->getLeftIntervals()->count(),
                $absThreshold,
                'left'
            );
        }

        // Check right click consistency (block placement)
        if ($rightClick) {
            $this->checkClickConsistency(
                $player,
                $clicks->getRightCPS(),
                $clicks->getRightIntervalStdDev(),
                $clicks->getRightIntervalMean(),
                $clicks->getRightIntervals()->count(),
                $absThreshold,
                'right'
            );
        }
    }

    /**
     * Check click consistency for a specific click type
     *
     * @param OomphPlayer $player The player to check
     * @param int $cps Current clicks per second
     * @param float $stdDev Standard deviation of intervals
     * @param float $mean Mean of intervals
     * @param int $samples Number of samples
     * @param float $absThreshold Absolute stddev threshold
     * @param string $clickType 'left' or 'right'
     */
    private function checkClickConsistency(
        OomphPlayer $player,
        int $cps,
        float $stdDev,
        float $mean,
        int $samples,
        float $absThreshold,
        string $clickType
    ): void {
        // Need minimum CPS to check - slow clicking naturally has high variance
        if ($cps < self::MIN_CPS_THRESHOLD) {
            $this->pass(0.05);
            return;
        }

        // Need enough samples for reliable long-term analysis
        if ($stdDev < 0 || $mean < 0 || $samples < self::MIN_SAMPLES) {
            return; // Not enough samples yet
        }

        // Calculate coefficient of variation
        $cv = $mean > 0 ? $stdDev / $mean : 0;

        // Check for suspicious consistency
        // Only flag for truly robotic patterns - low stddev OR low CV
        // Removed constant_pattern check as it false-flags butterfly clicking
        $suspicious = false;
        $reason = '';

        if ($stdDev < $absThreshold) {
            // Very low absolute std dev - almost robotic timing
            $suspicious = true;
            $reason = 'low_stddev';
        } elseif ($cv < self::CV_THRESHOLD && $cps >= 5) {
            // Low coefficient of variation at moderate+ CPS
            $suspicious = true;
            $reason = 'low_cv';
        }

        if ($suspicious) {
            $this->fail($player, 1.0, [
                'click_type' => $clickType,
                'reason' => $reason,
                'cps' => $cps,
                'stddev' => round($stdDev, 4),
                'mean' => round($mean, 4),
                'cv' => round($cv, 4),
                'samples' => $samples
            ]);
        } else {
            // Legitimate variance - decay buffer
            $this->pass(0.1);
        }
    }
}
