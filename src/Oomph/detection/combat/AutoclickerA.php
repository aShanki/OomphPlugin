<?php

declare(strict_types=1);

namespace Oomph\detection\combat;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * AutoclickerA Detection
 *
 * Purpose: Detect autoclickers by flagging CPS above 25 for left/right click.
 * 25 CPS is beyond human capability for sustained clicking.
 *
 * Detection logic:
 * - Left click CPS > 25 = flag
 * - Right click CPS > 25 = flag
 * - Tracks sustained high CPS over multiple ticks to avoid false positives
 */
class AutoclickerA extends Detection {

    /** Maximum allowed CPS before flagging */
    private const MAX_CPS = 25;

    /** Maximum CPS for mobile (same as desktop - 25 is the hard limit) */
    private const MAX_CPS_MOBILE = 25;

    /** Consecutive ticks above threshold before flagging */
    private int $leftViolationTicks = 0;
    private int $rightViolationTicks = 0;

    /** Threshold ticks to confirm autoclicker (not just a spike) */
    private const VIOLATION_TICK_THRESHOLD = 3;

    public function __construct() {
        parent::__construct(
            maxBuffer: 5.0,
            failBuffer: 2.0,
            trustDuration: 200 // 10 second decay
        );
    }

    public function getName(): string {
        return "AutoclickerA";
    }

    public function getType(): string {
        return self::TYPE_COMBAT;
    }

    public function getMaxViolations(): float {
        return 15.0;
    }

    /**
     * Process CPS check on each tick with clicks
     *
     * @param OomphPlayer $player The player to check
     * @param bool $leftClick Whether left click occurred
     * @param bool $rightClick Whether right click occurred
     */
    public function process(OomphPlayer $player, bool $leftClick, bool $rightClick = false): void {
        $clicks = $player->getClicksComponent();
        $isMobile = $player->isMobileDevice();
        $maxCPS = $isMobile ? self::MAX_CPS_MOBILE : self::MAX_CPS;

        $leftCPS = $clicks->getLeftCPS();
        $rightCPS = $clicks->getRightCPS();

        // Check left click CPS
        if ($leftCPS > $maxCPS) {
            $this->leftViolationTicks++;

            if ($this->leftViolationTicks >= self::VIOLATION_TICK_THRESHOLD) {
                $this->fail($player, 1.0, [
                    'click_type' => 'left',
                    'cps' => $leftCPS,
                    'max_allowed' => $maxCPS,
                    'sustained_ticks' => $this->leftViolationTicks
                ]);
            }
        } else {
            // Decay violation ticks slowly
            $this->leftViolationTicks = max(0, $this->leftViolationTicks - 1);
        }

        // Check right click CPS
        if ($rightCPS > $maxCPS) {
            $this->rightViolationTicks++;

            if ($this->rightViolationTicks >= self::VIOLATION_TICK_THRESHOLD) {
                $this->fail($player, 1.0, [
                    'click_type' => 'right',
                    'cps' => $rightCPS,
                    'max_allowed' => $maxCPS,
                    'sustained_ticks' => $this->rightViolationTicks
                ]);
            }
        } else {
            // Decay violation ticks slowly
            $this->rightViolationTicks = max(0, $this->rightViolationTicks - 1);
        }

        // Decay buffer if both are fine
        if ($leftCPS <= $maxCPS && $rightCPS <= $maxCPS) {
            $this->pass(0.05);
        }
    }

    /**
     * Called each tick for decay
     */
    public function tick(OomphPlayer $player): void {
        $this->pass(0.01);
    }
}
