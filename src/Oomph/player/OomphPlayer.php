<?php

declare(strict_types=1);

namespace Oomph\player;

use pocketmine\player\Player;
use pocketmine\player\GameMode;
use Oomph\player\component\MovementComponent;
use Oomph\player\component\CombatComponent;
use Oomph\player\component\ClicksComponent;
use Oomph\detection\DetectionManager;
use Oomph\detection\combat\AutoclickerA;
use Oomph\detection\combat\AimA;
use Oomph\detection\combat\KillauraA;
use Oomph\detection\combat\ReachA;
use Oomph\detection\combat\ReachB;
use Oomph\detection\combat\HitboxA;
use Oomph\detection\packet\BadPacketA;
use Oomph\detection\packet\BadPacketB;
use Oomph\detection\packet\BadPacketC;
use Oomph\detection\packet\BadPacketD;
use Oomph\detection\packet\BadPacketE;
use Oomph\detection\packet\BadPacketF;
use Oomph\detection\packet\BadPacketG;
use Oomph\detection\auth\EditionFakerA;
use Oomph\detection\auth\EditionFakerB;
use Oomph\detection\auth\EditionFakerC;
use Oomph\detection\movement\InvMoveA;
use Oomph\detection\world\ScaffoldA;

/**
 * Main player state wrapper for Oomph anticheat
 * Wraps pocketmine Player and tracks all relevant state for detection
 */
class OomphPlayer {

    private Player $player;

    // Timing and synchronization
    private int $runtimeId;
    private int $serverTick = 0;
    private int $clientTick = 0;
    private int $simulationFrame = 0;
    private int $inputCount = 0;

    // Network and device info
    private int $stackLatency = 0; // ping in milliseconds
    private GameMode $gameMode;
    private int $inputMode = 0; // 0 = touch, 1 = mouse/keyboard, 2 = controller
    private string $deviceOS = "Unknown";
    private string $version = "Unknown";

    // State flags
    private bool $ready = false;
    private bool $pendingCorrectionACK = false;

    // Components
    private MovementComponent $movementComponent;
    private CombatComponent $combatComponent;
    private ClicksComponent $clicksComponent;

    // Detection manager
    private DetectionManager $detectionManager;

    public function __construct(Player $player) {
        $this->player = $player;
        $this->runtimeId = $player->getId();
        $this->gameMode = $player->getGamemode();

        // Initialize components
        $location = $player->getLocation();
        $this->movementComponent = new MovementComponent(
            $location->asVector3(),
            $location->getYaw(),
            $location->getPitch(),
            $location->getYaw() // headYaw initially same as yaw
        );
        $this->combatComponent = new CombatComponent();
        $this->clicksComponent = new ClicksComponent();

        // Initialize detection manager
        $this->detectionManager = new DetectionManager($this);

        // Register all detections
        $this->registerDetections();
    }

    /**
     * Register all detection instances
     */
    private function registerDetections(): void {
        $dm = $this->detectionManager;

        // Combat detections
        $dm->register(new AutoclickerA());
        $dm->register(new AimA());
        $dm->register(new KillauraA());
        $dm->register(new ReachA());
        $dm->register(new ReachB());
        $dm->register(new HitboxA());

        // Packet detections
        $dm->register(new BadPacketA());
        $dm->register(new BadPacketB());
        $dm->register(new BadPacketC());
        $dm->register(new BadPacketD());
        $dm->register(new BadPacketE());
        $dm->register(new BadPacketF());
        $dm->register(new BadPacketG());

        // Auth detections
        $dm->register(new EditionFakerA());
        $dm->register(new EditionFakerB());
        $dm->register(new EditionFakerC());

        // Movement detections
        $dm->register(new InvMoveA());

        // World detections
        $dm->register(new ScaffoldA());
    }

    // Player wrapper
    public function getPlayer(): Player {
        return $this->player;
    }

    public function getRuntimeId(): int {
        return $this->runtimeId;
    }

    // Timing getters/setters
    public function getServerTick(): int {
        return $this->serverTick;
    }

    public function setServerTick(int $tick): void {
        $this->serverTick = $tick;
    }

    public function incrementServerTick(): void {
        $this->serverTick++;
    }

    public function getClientTick(): int {
        return $this->clientTick;
    }

    public function setClientTick(int $tick): void {
        $this->clientTick = $tick;
    }

    public function getSimulationFrame(): int {
        return $this->simulationFrame;
    }

    public function setSimulationFrame(int $frame): void {
        $this->simulationFrame = $frame;
    }

    public function getInputCount(): int {
        return $this->inputCount;
    }

    public function incrementInputCount(): void {
        $this->inputCount++;
    }

    // Network and device info
    public function getStackLatency(): int {
        return $this->stackLatency;
    }

    public function setStackLatency(int $latency): void {
        $this->stackLatency = $latency;
    }

    public function getPing(): int {
        return $this->stackLatency;
    }

    public function getGameMode(): GameMode {
        return $this->gameMode;
    }

    public function setGameMode(GameMode $gameMode): void {
        $this->gameMode = $gameMode;
    }

    public function getInputMode(): int {
        return $this->inputMode;
    }

    public function setInputMode(int $mode): void {
        $this->inputMode = $mode;
    }

    public function getInputModeName(): string {
        return match($this->inputMode) {
            0 => "Touch",
            1 => "Mouse/Keyboard",
            2 => "Controller",
            default => "Unknown"
        };
    }

    /**
     * Check if player is using a mobile device (touch input)
     */
    public function isMobileDevice(): bool {
        return $this->inputMode === 0; // Touch = mobile
    }

    /**
     * Check if player is using mouse input
     */
    public function isMouseInput(): bool {
        return $this->inputMode === 1; // Mouse/Keyboard
    }

    /**
     * Check if player is using controller input
     */
    public function isControllerInput(): bool {
        return $this->inputMode === 2;
    }

    public function getDeviceOS(): string {
        return $this->deviceOS;
    }

    public function setDeviceOS(string $os): void {
        $this->deviceOS = $os;
    }

    public function getVersion(): string {
        return $this->version;
    }

    public function setVersion(string $version): void {
        $this->version = $version;
    }

    // State flags
    public function isReady(): bool {
        return $this->ready;
    }

    public function setReady(bool $ready): void {
        $this->ready = $ready;
    }

    public function isPendingCorrectionACK(): bool {
        return $this->pendingCorrectionACK;
    }

    public function setPendingCorrectionACK(bool $pending): void {
        $this->pendingCorrectionACK = $pending;
    }

    // Component accessors
    public function getMovementComponent(): MovementComponent {
        return $this->movementComponent;
    }

    public function getCombatComponent(): CombatComponent {
        return $this->combatComponent;
    }

    public function getClicksComponent(): ClicksComponent {
        return $this->clicksComponent;
    }

    public function getDetectionManager(): DetectionManager {
        return $this->detectionManager;
    }

    /**
     * Update all components (should be called each tick)
     */
    public function tick(): void {
        $this->movementComponent->update();
        $this->clicksComponent->update();
        $this->detectionManager->runDetections();
    }

    /**
     * Reset per-tick state
     */
    public function resetTickState(): void {
        $this->combatComponent->reset();
    }
}
