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

    /** Last server tick we processed */
    private int $lastServerTick = 0;

    /** Number of inputs received this server tick */
    private int $inputsThisTick = 0;

    /** Balance of inputs (positive = too many, negative = too few) */
    private float $balance = 0.0;

    /** Maximum balance before flagging */
    private const MAX_BALANCE = 20.0;

    /** Balance added per extra input */
    private const BALANCE_PER_INPUT = 1.0;

    /** Balance removed per tick */
    private const BALANCE_DECAY = 1.05;

    /** Maximum inputs allowed per server tick (with latency buffer) */
    private const MAX_INPUTS_PER_TICK = 3;

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
        $serverTick = $player->getServerTick();
        $clientTick = $player->getClientTick();
        $ping = $player->getPing();

        // Reset counter if new server tick
        if ($serverTick !== $this->lastServerTick) {
            // Decay balance each server tick
            $this->balance = max(0.0, $this->balance - self::BALANCE_DECAY);
            $this->inputsThisTick = 0;
            $this->lastServerTick = $serverTick;
        }

        $this->inputsThisTick++;

        // Calculate allowed inputs based on latency
        // Higher ping = more leeway for bunched packets
        $latencyTicks = (int) ceil($ping / 50.0); // 50ms per tick
        $allowedInputs = self::MAX_INPUTS_PER_TICK + min($latencyTicks, 5);

        // Check if exceeding allowed inputs
        if ($this->inputsThisTick > $allowedInputs) {
            $this->balance += self::BALANCE_PER_INPUT;

            // Flag if balance too high (sustained timer usage)
            if ($this->balance >= self::MAX_BALANCE) {
                $this->fail($player, 1.0, [
                    'inputs_this_tick' => $this->inputsThisTick,
                    'allowed' => $allowedInputs,
                    'balance' => $this->balance,
                    'ping' => $ping
                ]);

                // Reset balance after flagging
                $this->balance = self::MAX_BALANCE / 2;
            }
        } else {
            // Decay buffer when behaving normally
            $this->pass(0.02);
        }
    }

    /**
     * Called each server tick for decay
     */
    public function tick(OomphPlayer $player): void {
        // Additional decay in tick loop
        $this->pass(0.01);
    }
}
