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

    // Input mode constants (from protocol)
    private const INPUT_MODE_TOUCH = 0;
    private const INPUT_MODE_MOUSE = 1;
    /** @phpstan-ignore classConstant.unused */
    private const INPUT_MODE_GAMEPAD = 2;
    /** @phpstan-ignore classConstant.unused */
    private const INPUT_MODE_MOTION_CONTROLLER = 3; // Legacy (pre-1.21.120)
    /** @phpstan-ignore classConstant.unused */
    private const INPUT_MODE_GDK = 4; // Added in 1.21.120+

    // Max valid input modes per protocol version (Go: lines 66-69)
    private const MAX_INPUT_MODE_NEW = 2;  // packet.InputModeGamePad (1.21.120+)
    private const MAX_INPUT_MODE_OLD = 4;  // packet.InputModeMotionController (pre-1.21.120)

    // Protocol version where input mode changed
    private const PROTOCOL_VERSION_1_21_120 = 748;

    // Device OS constants
    private const DEVICE_OS_ANDROID = 1;
    private const DEVICE_OS_IOS = 2;
    private const DEVICE_OS_FIREOS = 4;    // Amazon FireOS (Go: includes in mobile check)
    private const DEVICE_OS_ORBIS = 11;    // PlayStation
    private const DEVICE_OS_XBOX = 12;     // Xbox

    // Known invalid inputs per device (Go: knownInvalidInputs map lines 14-17)
    // PlayStation and Xbox cannot use touch input
    private const KNOWN_INVALID_INPUTS = [
        self::DEVICE_OS_ORBIS => [self::INPUT_MODE_TOUCH],
        self::DEVICE_OS_XBOX => [self::INPUT_MODE_TOUCH],
    ];

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
        // Determine max valid input mode based on protocol version (Go: lines 66-69)
        $maxInputMode = $protocolVersion >= self::PROTOCOL_VERSION_1_21_120
            ? self::MAX_INPUT_MODE_NEW   // InputModeGamePad (1.21.120+)
            : self::MAX_INPUT_MODE_OLD;  // InputModeMotionController (pre-1.21.120)

        // Check if input mode is within valid range (Go: line 71)
        // Note: Go requires inputMode >= InputModeMouse (1) and <= maxInputMode
        if ($inputMode > $maxInputMode || $inputMode < self::INPUT_MODE_MOUSE) {
            // Invalid input mode value
            $this->fail($player, 1.0, [
                'input_mode' => $inputMode,
                'max_valid' => $maxInputMode
            ]);
            return;
        }

        // Check known invalid inputs per device (Go: lines 76-78)
        $invalidInputs = self::KNOWN_INVALID_INPUTS[$deviceOS] ?? null;
        /** @phpstan-ignore function.impossibleType */
        if ($invalidInputs !== null && in_array($inputMode, $invalidInputs, true)) {
            $this->fail($player, 1.0, [
                'device_os' => $deviceOS,
                'input_mode' => $inputMode,
                'reason' => 'invalid_input_for_device'
            ]);
            return;
        }

        // Check if non-mobile device is using touch input (Go: lines 80-81)
        /** @phpstan-ignore booleanAnd.alwaysFalse, identical.alwaysFalse */
        if (!$this->allowNonMobileTouch && $inputMode === self::INPUT_MODE_TOUCH) {
            // Check if device is mobile (Go: includes FireOS)
            $isMobile = $deviceOS === self::DEVICE_OS_ANDROID
                     || $deviceOS === self::DEVICE_OS_IOS
                     || $deviceOS === self::DEVICE_OS_FIREOS;

            if (!$isMobile) {
                // Non-mobile device using touch input mode
                $this->fail($player, 1.0, [
                    'device_os' => $deviceOS,
                    'input_mode' => 'touch_on_non_mobile'
                ]);
            }
        }
    }
}
