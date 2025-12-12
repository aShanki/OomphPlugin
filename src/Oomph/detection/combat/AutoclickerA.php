<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * AutoclickerA Detection
 *
 * Purpose: Detect players clicking faster than humanly possible
 * Method: Track CPS (Clicks Per Second) in 1-second circular buffer
 *
 * Desktop limits: ~20 CPS left click, ~20 CPS right click
 * Mobile limits: ~25 CPS (higher threshold for touch screens)
 */
class AutoclickerA extends Detection {

    /** Default CPS limit for desktop left click */
    private const DEFAULT_LEFT_CPS_LIMIT = 20;

    /** Default CPS limit for desktop right click */
    private const DEFAULT_RIGHT_CPS_LIMIT = 20;

    /** Default CPS limit for mobile left click */
    private const DEFAULT_LEFT_CPS_LIMIT_MOBILE = 25;

    /** Default CPS limit for mobile right click */
    private const DEFAULT_RIGHT_CPS_LIMIT_MOBILE = 25;

    /** CPS limits (configurable) */
    private int $leftCPSLimit;
    private int $rightCPSLimit;
    private int $leftCPSLimitMobile;
    private int $rightCPSLimitMobile;

    public function __construct(
        int $leftCPSLimit = self::DEFAULT_LEFT_CPS_LIMIT,
        int $rightCPSLimit = self::DEFAULT_RIGHT_CPS_LIMIT,
        int $leftCPSLimitMobile = self::DEFAULT_LEFT_CPS_LIMIT_MOBILE,
        int $rightCPSLimitMobile = self::DEFAULT_RIGHT_CPS_LIMIT_MOBILE
    ) {
        parent::__construct(
            maxBuffer: 4.0,
            failBuffer: 4.0,
            trustDuration: -1
        );

        $this->leftCPSLimit = $leftCPSLimit;
        $this->rightCPSLimit = $rightCPSLimit;
        $this->leftCPSLimitMobile = $leftCPSLimitMobile;
        $this->rightCPSLimitMobile = $rightCPSLimitMobile;
    }

    public function getName(): string {
        return "AutoclickerA";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 20.0;
    }

    /**
     * Process click data and check for autoclicker
     *
     * @param OomphPlayer $player The player to check
     * @param bool $leftClick Whether left click occurred this tick
     * @param bool $rightClick Whether right click occurred this tick
     */
    public function process(OomphPlayer $player, bool $leftClick, bool $rightClick): void {
        // Get player's click tracking component
        $clicks = $player->getClicksComponent();

        // Update click delays and CPS
        if ($leftClick) {
            $clicks->incrementLeftClicks();
        }
        if ($rightClick) {
            $clicks->incrementRightClicks();
        }

        // Get current CPS values
        $leftCPS = $clicks->getLeftCPS();
        $rightCPS = $clicks->getRightCPS();

        // Determine limits based on device type
        $isMobile = $player->isMobileDevice();
        $leftLimit = $isMobile ? $this->leftCPSLimitMobile : $this->leftCPSLimit;
        $rightLimit = $isMobile ? $this->rightCPSLimitMobile : $this->rightCPSLimit;

        // Check left click CPS
        if ($leftCPS > $leftLimit) {
            $this->fail($player, 1.0);
            return;
        }

        // Check right click CPS
        if ($rightCPS > $rightLimit) {
            $this->fail($player, 1.0);
            return;
        }

        // Player is clicking at legitimate rate
        $this->pass(0.1);
    }

    /**
     * Get left CPS limit for desktop
     */
    public function getLeftCPSLimit(): int {
        return $this->leftCPSLimit;
    }

    /**
     * Get right CPS limit for desktop
     */
    public function getRightCPSLimit(): int {
        return $this->rightCPSLimit;
    }

    /**
     * Get left CPS limit for mobile
     */
    public function getLeftCPSLimitMobile(): int {
        return $this->leftCPSLimitMobile;
    }

    /**
     * Get right CPS limit for mobile
     */
    public function getRightCPSLimitMobile(): int {
        return $this->rightCPSLimitMobile;
    }
}
