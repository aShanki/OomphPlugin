<?php

declare(strict_types=1);

namespace Oomph\detection\auth;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * EditionFakerC Detection
 *
 * Validates input mode is within valid range and optionally blocks non-mobile devices
 * from using touch input mode (common spoofing technique).
 */
class EditionFakerC extends Detection {

    // Input mode constants
    private const INPUT_MODE_TOUCH = 0;
    /** @phpstan-ignore classConstant.unused */
    private const INPUT_MODE_MOUSE = 1;
    /** @phpstan-ignore classConstant.unused */
    private const INPUT_MODE_GAMEPAD = 2;
    /** @phpstan-ignore classConstant.unused */
    private const INPUT_MODE_MOTION_CONTROLLER = 3;
    /** @phpstan-ignore classConstant.unused */
    private const INPUT_MODE_GDK = 4; // Added in 1.21.120+
    private const INPUT_MODE_COUNT_OLD = 4; // Pre-1.21.120
    private const INPUT_MODE_COUNT_NEW = 5; // 1.21.120+

    // Protocol version where GDK mode was added
    private const PROTOCOL_VERSION_1_21_120 = 859;

    // Device OS constants
    private const DEVICE_OS_ANDROID = 1;
    private const DEVICE_OS_IOS = 2;

    public function __construct(
        private bool $allowNonMobileTouch = true // Set false to block non-mobile using touch
    ) {
        // MaxViolations: 1 (instant kick on detection)
        // No buffer system needed
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "EditionFakerC";
    }

    public function getType(): string {
        return self::TYPE_AUTH;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check if player's input mode is valid for their client version and device
     */
    public function check(OomphPlayer $player, int $inputMode, int $protocolVersion, int $deviceOS): void {
        // Determine max valid input mode based on protocol version
        $maxInputMode = $protocolVersion >= self::PROTOCOL_VERSION_1_21_120
            ? self::INPUT_MODE_COUNT_NEW - 1  // 0-4 (includes GDK)
            : self::INPUT_MODE_COUNT_OLD - 1; // 0-3

        // Check if input mode is within valid range
        if ($inputMode < 0 || $inputMode > $maxInputMode) {
            // Invalid input mode value
            $this->fail($player);
            return;
        }

        // Optional: Check if non-mobile device is using touch input
        if (!$this->allowNonMobileTouch && $inputMode === self::INPUT_MODE_TOUCH) {
            // Check if device is mobile
            $isMobile = $deviceOS === self::DEVICE_OS_ANDROID || $deviceOS === self::DEVICE_OS_IOS;

            if (!$isMobile) {
                // Non-mobile device using touch input mode
                $this->fail($player);
            }
        }
    }
}
