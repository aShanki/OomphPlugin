<?php

declare(strict_types=1);

namespace Oomph\detection;

use Oomph\player\OomphPlayer;
use Oomph\utils\WebhookNotifier;

/**
 * Abstract base class for all anticheat detections
 *
 * Implements a buffer-based violation system where:
 * - Buffer accumulates on suspicious behavior
 * - Buffer must exceed FailBuffer threshold to count as violation
 * - Violations accumulate until MaxViolations reached (punishment)
 * - Buffer decays over time when player behaves legitimately
 */
abstract class Detection {

    // Detection type constants
    public const TYPE_COMBAT = "combat";
    public const TYPE_MOVEMENT = "movement";
    public const TYPE_PACKET = "packet";
    public const TYPE_AUTH = "auth";
    public const TYPE_WORLD = "world";

    /** Current violation count */
    protected float $violations = 0.0;

    /** Current buffer level */
    protected float $buffer = 0.0;

    /** Maximum buffer value */
    protected float $maxBuffer;

    /** Buffer threshold to trigger violation */
    protected float $failBuffer;

    /** Trust duration in ticks (-1 for immediate violation) */
    protected int $trustDuration;

    /** Server tick when last flagged */
    protected int $lastFlagged = 0;

    /**
     * @param float $maxBuffer Maximum buffer value
     * @param float $failBuffer Buffer threshold to trigger violation
     * @param int $trustDuration Trust duration in ticks (-1 for immediate)
     */
    public function __construct(float $maxBuffer, float $failBuffer, int $trustDuration = -1) {
        $this->maxBuffer = $maxBuffer;
        $this->failBuffer = $failBuffer;
        $this->trustDuration = $trustDuration;
    }

    /**
     * Get the detection name (e.g., "AutoclickerA", "BadPacketB")
     */
    abstract public function getName(): string;

    /**
     * Get the detection type
     */
    abstract public function getType(): string;

    /**
     * Get maximum violations before punishment
     */
    abstract public function getMaxViolations(): float;

    /**
     * Increment buffer and check for violation
     *
     * @param OomphPlayer $player The player being checked
     * @param float $extra Additional buffer to add (default: 1.0)
     * @param array<string, mixed> $debugData Optional debug data for logging
     */
    public function fail(OomphPlayer $player, float $extra = 1.0, array $debugData = []): void {
        // Increment buffer (capped at maxBuffer)
        $this->buffer = min($this->buffer + $extra, $this->maxBuffer);

        // Check if buffer threshold reached
        if ($this->buffer < $this->failBuffer) {
            return;
        }

        // Calculate violation increment based on trust duration
        $increment = 1.0;
        if ($this->trustDuration > 0) {
            $serverTick = $player->getServerTick();
            $ticksSinceFlag = $serverTick - $this->lastFlagged;
            $increment = min(1.0, $ticksSinceFlag / $this->trustDuration);
        }

        // Add violations
        $this->violations += $increment;
        $this->lastFlagged = $player->getServerTick();

        // Log every flag to console
        $playerName = $player->getPlayer()->getName();
        $debugStr = '';
        if ($debugData !== []) {
            $parts = [];
            foreach ($debugData as $key => $value) {
                if (is_float($value)) {
                    $parts[] = "$key=" . round($value, 3);
                } elseif (is_scalar($value)) {
                    $parts[] = "$key=" . (string) $value;
                } else {
                    $parts[] = "$key=" . gettype($value);
                }
            }
            $debugStr = ' [' . implode(', ', $parts) . ']';
        }

        // Console log the detection flag
        $this->logToConsole(sprintf(
            "[Oomph] %s flagged %s (vl=%.1f/%.0f, buffer=%.2f/%.2f)%s",
            $playerName,
            $this->getName(),
            $this->violations,
            $this->getMaxViolations(),
            $this->buffer,
            $this->maxBuffer,
            $debugStr
        ));

        // Check if max violations reached (log only, no kick)
        $isMaxViolation = $this->violations >= $this->getMaxViolations();
        if ($isMaxViolation) {
            $this->logToConsole(sprintf(
                "[Oomph] %s reached max violations for %s - would be punished (Unfair Advantage)",
                $playerName,
                $this->getName()
            ));
        }

        // Send webhook notification
        WebhookNotifier::sendDetection(
            $playerName,
            $this->getName(),
            $this->getType(),
            $this->violations,
            $this->getMaxViolations(),
            $this->buffer,
            $this->maxBuffer,
            $debugData,
            $isMaxViolation
        );
    }

    /**
     * Log a message to console
     */
    protected function logToConsole(string $message): void {
        $plugin = \Oomph\Main::getInstance();
        $config = $plugin->getConfig();
        $enabledRaw = $config->getNested('logging.console_flags', true);
        $enabled = is_bool($enabledRaw) ? $enabledRaw : true;
        if (!$enabled) {
            return;
        }

        // Use error_log for console output in PocketMine
        error_log($message);
    }


    /**
     * Decay buffer (called when player behaves legitimately)
     *
     * @param float $amount Amount to decrease buffer by (default: 0.1)
     */
    public function pass(float $amount = 0.1): void {
        $this->buffer = max(0.0, $this->buffer - $amount);
    }

    /**
     * Reset violations and buffer to zero
     */
    public function reset(): void {
        $this->violations = 0.0;
        $this->buffer = 0.0;
    }

    /**
     * Get current violation count
     */
    public function getViolations(): float {
        return $this->violations;
    }

    /**
     * Get current buffer value
     */
    public function getBuffer(): float {
        return $this->buffer;
    }

    /**
     * Get fail buffer threshold
     */
    public function getFailBuffer(): float {
        return $this->failBuffer;
    }

    /**
     * Get max buffer value
     */
    public function getMaxBuffer(): float {
        return $this->maxBuffer;
    }

    /**
     * Get trust duration
     */
    public function getTrustDuration(): int {
        return $this->trustDuration;
    }

    /**
     * Get last flagged tick
     */
    public function getLastFlagged(): int {
        return $this->lastFlagged;
    }

    /**
     * Whether this detection can cancel events (e.g., cancel attacks)
     * Override this in subclasses that should cancel events
     */
    public function isCancellable(): bool {
        return false;
    }

    /**
     * Whether an event should be cancelled based on current buffer
     * This checks if the buffer has reached the fail threshold
     */
    public function shouldCancel(): bool {
        return $this->buffer >= $this->failBuffer;
    }
}
