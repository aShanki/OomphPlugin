<?php

declare(strict_types=1);

namespace Oomph\utils;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

/**
 * Handles sending anticheat detection notifications to Discord webhooks
 * Uses a queue system to avoid Discord rate limits
 */
class WebhookNotifier {

    private static ?string $webhookUrl = null;
    private static bool $notifyAllFlags = true;
    private static int $minViolationLevel = 0;

    /** @var array<int, array<string, mixed>> Queue of embeds waiting to be sent */
    private static array $embedQueue = [];

    /** Maximum embeds per Discord message (Discord limit is 10) */
    private const MAX_EMBEDS_PER_MESSAGE = 10;

    /**
     * Initialize the webhook notifier with config settings
     *
     * @param string $webhookUrl Discord webhook URL
     * @param bool $notifyAllFlags Whether to notify on all flags or just max violations
     * @param int $minViolationLevel Minimum violation level to trigger notification
     */
    public static function init(string $webhookUrl, bool $notifyAllFlags = true, int $minViolationLevel = 0): void {
        self::$webhookUrl = $webhookUrl !== "" ? $webhookUrl : null;
        self::$notifyAllFlags = $notifyAllFlags;
        self::$minViolationLevel = $minViolationLevel;
    }

    /**
     * Check if webhook notifications are enabled
     */
    public static function isEnabled(): bool {
        return self::$webhookUrl !== null;
    }

    /**
     * Check if we should notify for this violation level
     */
    public static function shouldNotify(float $violationLevel, bool $isMaxViolation, bool $isNewMaxViolation): bool {
        if (!self::isEnabled()) {
            return false;
        }

        if ($isMaxViolation) {
            if ($isNewMaxViolation) {
                return true;
            }
            return self::$notifyAllFlags;
        }

        return self::$notifyAllFlags && $violationLevel >= self::$minViolationLevel;
    }

    /**
     * Get current queue size for debugging
     */
    public static function getQueueSize(): int {
        return count(self::$embedQueue);
    }

    /**
     * Queue a detection notification for sending to Discord
     *
     * @param string $playerName Player who triggered the detection
     * @param string $detectionName Name of the detection (e.g., "ReachA")
     * @param string $detectionType Type of detection (combat, movement, packet, auth, world)
     * @param float $violations Current violation count
     * @param float $maxViolations Maximum violations before punishment
     * @param float $buffer Current buffer level
     * @param float $maxBuffer Maximum buffer level
     * @param array<string, mixed> $debugData Additional debug information
     * @param bool $isMaxViolation Whether this is a max violation (punishment threshold)
     */
    public static function sendDetection(
        string $playerName,
        string $detectionName,
        string $detectionType,
        float $violations,
        float $maxViolations,
        float $buffer,
        float $maxBuffer,
        array $debugData,
        bool $isMaxViolation,
        bool $isNewMaxViolation
    ): void {
        if (!self::shouldNotify($violations, $isMaxViolation, $isNewMaxViolation)) {
            return;
        }

        $color = match ($detectionType) {
            "combat" => 0xFF5555,    // Red
            "movement" => 0xFFAA00,  // Orange
            "packet" => 0xAA00AA,    // Purple
            "auth" => 0x5555FF,      // Blue
            "world" => 0x00AA00,     // Green
            default => 0xAAAAAA      // Gray
        };

        $title = $isMaxViolation
            ? "[!] MAX VIOLATIONS - $detectionName"
            : "[Flag] $detectionName";

        $debugStr = "";
        if ($debugData !== []) {
            $parts = [];
            foreach ($debugData as $key => $value) {
                if (is_float($value)) {
                    $parts[] = "$key: " . round($value, 3);
                } elseif (is_scalar($value)) {
                    $parts[] = "$key: " . (string) $value;
                }
            }
            $debugStr = implode("\n", $parts);
        }

        $embed = [
            "title" => $title,
            "color" => $color,
            "fields" => [
                [
                    "name" => "Player",
                    "value" => "`$playerName`",
                    "inline" => true
                ],
                [
                    "name" => "Detection",
                    "value" => "`$detectionName`",
                    "inline" => true
                ],
                [
                    "name" => "Type",
                    "value" => ucfirst($detectionType),
                    "inline" => true
                ],
                [
                    "name" => "Violations",
                    "value" => sprintf("%.1f / %.0f", $violations, $maxViolations),
                    "inline" => true
                ],
                [
                    "name" => "Buffer",
                    "value" => sprintf("%.2f / %.2f", $buffer, $maxBuffer),
                    "inline" => true
                ]
            ],
            "timestamp" => date("c")
        ];

        if ($debugStr !== "") {
            $embed["fields"][] = [
                "name" => "Debug Data",
                "value" => "```\n$debugStr\n```",
                "inline" => false
            ];
        }

        // Add to queue instead of sending immediately
        self::$embedQueue[] = $embed;
    }

    /**
     * Process the webhook queue - sends batched embeds to Discord
     * Called periodically by the scheduler to avoid rate limits
     */
    public static function processQueue(): void {
        if (self::$webhookUrl === null || empty(self::$embedQueue)) {
            return;
        }

        // Take up to MAX_EMBEDS_PER_MESSAGE from the queue
        $embedsToSend = array_splice(self::$embedQueue, 0, self::MAX_EMBEDS_PER_MESSAGE);

        if (empty($embedsToSend)) {
            return;
        }

        $payload = [
            "embeds" => $embedsToSend
        ];

        // Send async to avoid blocking the main thread
        Server::getInstance()->getAsyncPool()->submitTask(
            new WebhookSendTask(self::$webhookUrl, $payload)
        );

        // Log queue status periodically
        $remaining = count(self::$embedQueue);
        if ($remaining > 0) {
            error_log("[Oomph Webhook] Sent batch of " . count($embedsToSend) . ", $remaining remaining in queue");
        }
    }
}

/**
 * Async task for sending webhook requests
 */
class WebhookSendTask extends AsyncTask {

    private string $webhookUrl;
    private string $payload;

    /**
     * @param string $webhookUrl
     * @param array<string, mixed> $payload
     */
    public function __construct(string $webhookUrl, array $payload) {
        $this->webhookUrl = $webhookUrl;
        // Sanitize payload to handle NaN/Infinity which break JSON encoding
        $sanitized = self::sanitizeForJson($payload);
        $encoded = json_encode($sanitized);
        if ($encoded === false) {
            error_log("[Oomph Webhook] JSON encode failed: " . json_last_error_msg());
            $this->payload = '{}';
        } else {
            $this->payload = $encoded;
        }
    }

    /**
     * Recursively sanitize array for JSON encoding (handles NaN/Infinity)
     * @param mixed $data
     * @return mixed
     */
    private static function sanitizeForJson(mixed $data): mixed {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeForJson'], $data);
        }
        if (is_float($data)) {
            if (is_nan($data)) {
                return "NaN";
            }
            if (is_infinite($data)) {
                return $data > 0 ? "Infinity" : "-Infinity";
            }
        }
        return $data;
    }

    public function onRun(): void {
        $ch = curl_init($this->webhookUrl);
        if ($ch === false) {
            error_log("[Oomph Webhook] Failed to init curl");
            return;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->payload,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Content-Length: " . strlen($this->payload)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false // Docker containers may not have updated CA certs
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== "") {
            error_log("[Oomph Webhook] Curl error: " . $error);
        } elseif ($httpCode >= 400) {
            error_log("[Oomph Webhook] HTTP $httpCode: " . ($result !== false ? substr((string) $result, 0, 200) : "no response"));
        }
    }
}
