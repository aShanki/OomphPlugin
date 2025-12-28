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

    // Device OS constants (from protocol)
    private const DEVICE_OS_ANDROID = 1;
    private const DEVICE_OS_IOS = 2;
    /** @phpstan-ignore classConstant.unused */
    private const DEVICE_OS_OSX = 3;
    private const DEVICE_OS_FIREOS = 4;    // Amazon FireOS (Go: DeviceFireOS)
    private const DEVICE_OS_WINDOWS_10 = 7; // Windows UWP (Go: DeviceWin10)
    private const DEVICE_OS_WINDOWS_32 = 8; // Windows GDK (Go: DeviceWin32) - 1.21.120+
    private const DEVICE_OS_ORBIS = 11;     // PlayStation (Go: DeviceOrbis)
    private const DEVICE_OS_XBOX = 12;      // Xbox (Go: DeviceXBOX)
    private const DEVICE_OS_NX = 13;        // Nintendo Switch (Go: DeviceNX)
    private const DEVICE_OS_WP = 14;        // Windows Phone (Go: DeviceWP)

    // Title ID constants (Go: lines 13-23)
    private const TITLE_ID_ANDROID = "1739947436";
    private const TITLE_ID_IOS = "1810924247";
    private const TITLE_ID_FIREOS = "1944307183";
    private const TITLE_ID_WINDOWS = "896928775";
    private const TITLE_ID_ORBIS = "2044456598";  // PlayStation - FIXED (was swapped with Nintendo)
    private const TITLE_ID_NX = "2047319603";     // Nintendo - FIXED (was swapped with PlayStation)
    private const TITLE_ID_XBOX = "1828326430";
    private const TITLE_ID_WP = "1916611344";
    private const TITLE_ID_PREVIEW = "1904044383";

    // Valid TitleID mappings per DeviceOS (Go: knownTitleIDs map)
    private const VALID_TITLE_IDS = [
        self::DEVICE_OS_ANDROID => [self::TITLE_ID_ANDROID],
        self::DEVICE_OS_IOS => [self::TITLE_ID_IOS],
        self::DEVICE_OS_FIREOS => [self::TITLE_ID_FIREOS],
        self::DEVICE_OS_WINDOWS_10 => [self::TITLE_ID_WINDOWS],
        self::DEVICE_OS_WINDOWS_32 => [self::TITLE_ID_WINDOWS],
        self::DEVICE_OS_ORBIS => [self::TITLE_ID_ORBIS],  // PlayStation
        self::DEVICE_OS_XBOX => [self::TITLE_ID_XBOX],
        self::DEVICE_OS_NX => [self::TITLE_ID_NX],        // Nintendo
        self::DEVICE_OS_WP => [self::TITLE_ID_WP],
    ];

    // Devices that can use preview edition (Go: previewEditionClients)
    private const PREVIEW_EDITION_DEVICES = [
        self::DEVICE_OS_WINDOWS_10,
        self::DEVICE_OS_IOS,
        self::DEVICE_OS_XBOX,
    ];

    // Protocol version for 1.21.120 (GDK Windows available)
    private const PROTOCOL_VERSION_1_21_120 = 748;

    // Protocol version for 1.21.80 (empty titleID bug)
    private const PROTOCOL_VERSION_1_21_80 = 729;

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
     *
     * @param OomphPlayer $player The player to check
     * @param int $deviceOS The device OS from ClientData
     * @param string $titleID The title ID from IdentityData
     * @param int $protocolVersion The client's protocol version
     */
    public function check(OomphPlayer $player, int $deviceOS, string $titleID, int $protocolVersion = 999): void {
        // Handle preview edition clients (Go: lines 101-111)
        if ($titleID === self::TITLE_ID_PREVIEW) {
            if (!in_array($deviceOS, self::PREVIEW_EDITION_DEVICES, true)) {
                $this->fail($player, 1.0, [
                    'device_os' => $deviceOS,
                    'title_id' => $titleID,
                    'expected_os' => 'Windows/iOS/Xbox (preview edition)'
                ]);
            }
            return;
        }

        // Check for GDK Windows on older protocol versions (Go: lines 114-123)
        if ($deviceOS === self::DEVICE_OS_WINDOWS_32 && $protocolVersion < self::PROTOCOL_VERSION_1_21_120) {
            $this->fail($player, 1.0, [
                'device_os' => $deviceOS,
                'title_id' => $titleID,
                'reason' => 'GDK not available on this protocol version'
            ]);
            return;
        }

        // Get valid TitleIDs for this DeviceOS
        $validTitleIds = self::VALID_TITLE_IDS[$deviceOS] ?? null;

        // If we don't have mapping for this OS, skip check (unknown device)
        if ($validTitleIds === null) {
            return;
        }

        // Check if TitleID is valid for this DeviceOS
        if (!in_array($titleID, $validTitleIds, true)) {
            // BedrockTogether workaround: Some console players use Android titleID (Go: lines 133-135)
            if ($titleID === self::TITLE_ID_ANDROID &&
                ($deviceOS === self::DEVICE_OS_ORBIS || $deviceOS === self::DEVICE_OS_XBOX)) {
                return;
            }

            // Bug with old game version - empty titleID on 1.21.80 (Go: lines 138-140)
            if (strlen($titleID) === 0 && $protocolVersion === self::PROTOCOL_VERSION_1_21_80) {
                return;
            }

            // TitleID doesn't match expected values for this DeviceOS
            $this->fail($player, 1.0, [
                'device_os' => $deviceOS,
                'title_id' => $titleID,
                'expected' => implode('/', $validTitleIds)
            ]);
        }
    }
}
