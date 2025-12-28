<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * AutoclickerB Detection
 *
 * Purpose: Detect autoclickers by analyzing click timing consistency
 * Method: Calculate standard deviation and coefficient of variation of click intervals
 *
 * Human clicks have natural variance - they are not perfectly timed.
 * Autoclickers produce suspiciously consistent intervals (low std dev).
 *
 * Detection logic:
 * - Coefficient of Variation (CV) = stddev / mean
 * - Low CV (<0.15) with high CPS (>8) indicates robotic clicking
 * - Very low std dev (<0.3) alone is also suspicious at any CPS
 */
class AutoclickerB extends Detection {

    /** @phpstan-ignore classConstant.unused */
    private const MIN_SAMPLES = 10;

    /** Minimum CPS to trigger consistency check (too low CPS could be legitimate slow clicking) */
    private const MIN_CPS_THRESHOLD = 8;

    /** Coefficient of variation threshold - below this is suspicious */
    private const CV_THRESHOLD = 0.15;

    /** Absolute std dev threshold - below this is always suspicious regardless of CV */
    private const ABSOLUTE_STDDEV_THRESHOLD = 0.3;

    /** Std dev threshold for mobile (more lenient due to touch mechanics) */
    private const ABSOLUTE_STDDEV_THRESHOLD_MOBILE = 0.2;

    public function __construct() {
        parent::__construct(
            maxBuffer: 5.0,
            failBuffer: 3.0,
            trustDuration: 600 // 30 seconds (600 ticks) to build trust
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
     * Process click consistency check
     *
     * @param OomphPlayer $player The player to check
     * @param bool $leftClick Whether left click occurred this tick
     */
    public function process(OomphPlayer $player, bool $leftClick): void {
        if (!$leftClick) {
            return; // Only check on click events
        }

        $clicks = $player->getClicksComponent();
        $leftCPS = $clicks->getLeftCPS();

        // Need minimum CPS to check - slow clicking naturally has high variance
        if ($leftCPS < self::MIN_CPS_THRESHOLD) {
            $this->pass(0.05);
            return;
        }

        // Get interval statistics
        $stdDev = $clicks->getLeftIntervalStdDev();
        $mean = $clicks->getLeftIntervalMean();

        // Need enough samples for reliable analysis
        if ($stdDev < 0 || $mean < 0) {
            return; // Not enough samples yet
        }

        // Calculate coefficient of variation
        $cv = $mean > 0 ? $stdDev / $mean : 0;

        $isMobile = $player->isMobileDevice();
        $absThreshold = $isMobile ? self::ABSOLUTE_STDDEV_THRESHOLD_MOBILE : self::ABSOLUTE_STDDEV_THRESHOLD;

        // Check for suspicious consistency
        $suspicious = false;
        $reason = '';

        if ($stdDev < $absThreshold) {
            // Very low absolute std dev - almost robotic timing
            $suspicious = true;
            $reason = 'low_stddev';
        } elseif ($cv < self::CV_THRESHOLD && $leftCPS >= 12) {
            // Low coefficient of variation at high CPS
            $suspicious = true;
            $reason = 'low_cv';
        }

        if ($suspicious) {
            $this->fail($player, 1.0, [
                'reason' => $reason,
                'cps' => $leftCPS,
                'stddev' => $stdDev,
                'mean' => $mean,
                'cv' => $cv,
                'samples' => $clicks->getLeftIntervals()->count()
            ]);
        } else {
            // Legitimate variance - decay buffer
            $this->pass(0.15);
        }
    }
}
