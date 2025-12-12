<?php

declare(strict_types=1);

namespace Oomph\detection\auth;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * EditionFakerB Detection
 *
 * Detects spoofed input mode by validating default input mode matches the device OS.
 * Each device type has an expected default input mode.
 */
class EditionFakerB extends Detection {

    // Device OS constants
    private const DEVICE_OS_ANDROID = 1;
    private const DEVICE_OS_IOS = 2;
    private const DEVICE_OS_OSX = 3;
    private const DEVICE_OS_WINDOWS_10 = 7;
    private const DEVICE_OS_PLAYSTATION = 11;
    private const DEVICE_OS_XBOX = 12;
    private const DEVICE_OS_NINTENDO = 13;

    // Input mode constants
    private const INPUT_MODE_TOUCH = 0;
    private const INPUT_MODE_MOUSE = 1;
    private const INPUT_MODE_GAMEPAD = 2;

    // Expected default input mode per DeviceOS
    private const EXPECTED_INPUT_MODES = [
        self::DEVICE_OS_ANDROID => self::INPUT_MODE_TOUCH,
        self::DEVICE_OS_IOS => self::INPUT_MODE_TOUCH,
        self::DEVICE_OS_OSX => self::INPUT_MODE_MOUSE,
        self::DEVICE_OS_WINDOWS_10 => self::INPUT_MODE_MOUSE,
        self::DEVICE_OS_XBOX => self::INPUT_MODE_GAMEPAD,
        self::DEVICE_OS_PLAYSTATION => self::INPUT_MODE_GAMEPAD,
        self::DEVICE_OS_NINTENDO => self::INPUT_MODE_GAMEPAD,
    ];

    public function __construct() {
        // MaxViolations: 1 (instant kick on detection)
        // No buffer system needed
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "EditionFakerB";
    }

    public function getType(): string {
        return self::TYPE_AUTH;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check if player's default input mode matches their DeviceOS
     */
    public function check(OomphPlayer $player, int $deviceOS, int $defaultInputMode): void {
        // Get expected input mode for this DeviceOS
        $expectedInputMode = self::EXPECTED_INPUT_MODES[$deviceOS] ?? null;

        // If we don't have mapping for this OS, skip check
        if ($expectedInputMode === null) {
            return;
        }

        // Check if input mode matches expected for this DeviceOS
        if ($defaultInputMode !== $expectedInputMode) {
            // Input mode doesn't match expected for this DeviceOS
            $this->fail($player);
        }
    }
}
