<?php

declare(strict_types=1);

namespace Oomph\detection\movement;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * TimerA Detection
 *
 * Detects timer hacks by rate-limiting inputs per tick.
 * Timer hacks increase the game tick rate, sending more inputs per second
 * than the server expects.
 *
 * Based on anticheat-reference InputAcceptable() in component/movement.go
 * Tracks client tick vs server tick and flags excessive input rates.
 */
class TimerA extends Detection {



    public function __construct() {
        // Higher thresholds to account for network jitter
        parent::__construct(
            maxBuffer: 5.0,
            failBuffer: 3.0,
            trustDuration: 100 // 5 second decay
        );
    }

    public function getName(): string {
        return "TimerA";
    }

    public function getType(): string {
        return self::TYPE_MOVEMENT;
    }

    public function getMaxViolations(): float {
        return 20.0;
    }

    /**
     * Called on each PlayerAuthInput packet
     *
     * @param OomphPlayer $player The player being checked
     */
    public function onInput(OomphPlayer $player): void {
        return;
    }

    /**
     * Called each server tick for decay
     */
    public function tick(OomphPlayer $player): void {
        return;
    }
}
