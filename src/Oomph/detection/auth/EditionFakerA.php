<?php

declare(strict_types=1);

namespace Oomph\detection\auth;

use Oomph\detection\Detection;
use Oomph\player\OomphPlayer;

/**
 * EditionFakerA Detection
 *
 * Detects spoofed device information by validating DeviceOS vs TitleID mismatch.
 * Each operating system has specific valid TitleIDs that should match.
 */
class EditionFakerA extends Detection {

    // Device OS constants
    private const DEVICE_OS_ANDROID = 1;
    private const DEVICE_OS_IOS = 2;
    /** @phpstan-ignore classConstant.unused */
    private const DEVICE_OS_OSX = 3;
    private const DEVICE_OS_WINDOWS_10 = 7;
    private const DEVICE_OS_PLAYSTATION = 11;
    private const DEVICE_OS_XBOX = 12;
    private const DEVICE_OS_NINTENDO = 13;

    // Valid TitleID mappings per DeviceOS
    private const VALID_TITLE_IDS = [
        self::DEVICE_OS_ANDROID => [1739947436, 1810924247],
        self::DEVICE_OS_IOS => [1810924247],
        self::DEVICE_OS_WINDOWS_10 => [896928775, 1739947436, 1828326430], // Preview available
        self::DEVICE_OS_XBOX => [1739947436, 1828326430], // Preview available
        self::DEVICE_OS_PLAYSTATION => [2047319603],
        self::DEVICE_OS_NINTENDO => [2044456598],
    ];

    public function __construct() {
        // MaxViolations: 1 (instant kick on detection)
        // No buffer system needed (FailBuffer = MaxBuffer = 0)
        parent::__construct(
            maxBuffer: 1.0,
            failBuffer: 1.0,
            trustDuration: -1
        );
    }

    public function getName(): string {
        return "EditionFakerA";
    }

    public function getType(): string {
        return self::TYPE_AUTH;
    }

    public function getMaxViolations(): float {
        return 1.0;
    }

    /**
     * Check if player's DeviceOS matches their TitleID
     */
    public function check(OomphPlayer $player, int $deviceOS, string $titleID): void {
        // Get valid TitleIDs for this DeviceOS
        $validTitleIds = self::VALID_TITLE_IDS[$deviceOS] ?? null;

        // If we don't have mapping for this OS, skip check
        if ($validTitleIds === null) {
            return;
        }

        // Convert TitleID string to int for comparison
        $titleIdInt = (int) $titleID;

        // Check if TitleID is valid for this DeviceOS
        if (!in_array($titleIdInt, $validTitleIds, true)) {
            // TitleID doesn't match expected values for this DeviceOS
            $this->fail($player);
        }
    }
}
