<?php

declare(strict_types=1);

namespace Oomph\listener;

use Oomph\Main;
use Oomph\player\PlayerManager;
use Oomph\detection\auth\EditionFakerA;
use Oomph\detection\auth\EditionFakerB;
use Oomph\detection\auth\EditionFakerC;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class PlayerListener implements Listener {

    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private Main $plugin,
        private PlayerManager $playerManager
    ) {}

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        // Create OomphPlayer instance
        $oomphPlayer = $this->playerManager->create($player);

        // Get player's network session info
        $networkSession = $player->getNetworkSession();
        $playerInfo = $networkSession->getPlayerInfo();

        if ($playerInfo === null) {
            return;
        }

        // Extract device info from extra data
        $extraData = $playerInfo->getExtraData();

        $deviceOS = isset($extraData["DeviceOS"]) && is_int($extraData["DeviceOS"]) ? $extraData["DeviceOS"] : 0;
        $deviceId = isset($extraData["DeviceId"]) && is_string($extraData["DeviceId"]) ? $extraData["DeviceId"] : "";
        $deviceModel = isset($extraData["DeviceModel"]) && is_string($extraData["DeviceModel"]) ? $extraData["DeviceModel"] : "";
        $titleId = isset($extraData["TitleId"]) && is_string($extraData["TitleId"]) ? $extraData["TitleId"] : "";
        $defaultInputMode = isset($extraData["DefaultInputMode"]) && is_int($extraData["DefaultInputMode"]) ? $extraData["DefaultInputMode"] : 0;
        $currentInputMode = isset($extraData["CurrentInputMode"]) && is_int($extraData["CurrentInputMode"]) ? $extraData["CurrentInputMode"] : 0;

        // Store device info in OomphPlayer
        $oomphPlayer->setDeviceOS($this->getDeviceOSName($deviceOS));
        $oomphPlayer->setInputMode($currentInputMode);

        // Get protocol version
        $protocolVersion = ProtocolInfo::CURRENT_PROTOCOL;

        // Get detection manager
        $dm = $oomphPlayer->getDetectionManager();

        // Run EditionFaker checks

        // EditionFakerA: Check DeviceOS vs TitleID mismatch
        $editionFakerA = $dm->get("EditionFakerA");
        if ($editionFakerA instanceof EditionFakerA && $titleId !== "") {
            $editionFakerA->check($oomphPlayer, $deviceOS, $titleId);
        }

        // EditionFakerB: Check DefaultInputMode vs DeviceOS
        $editionFakerB = $dm->get("EditionFakerB");
        if ($editionFakerB instanceof EditionFakerB) {
            $editionFakerB->check($oomphPlayer, $deviceOS, $defaultInputMode);
        }

        // EditionFakerC: Check if input mode is valid for client version
        $editionFakerC = $dm->get("EditionFakerC");
        if ($editionFakerC instanceof EditionFakerC) {
            $editionFakerC->check($oomphPlayer, $currentInputMode, $protocolVersion, $deviceOS);
        }

        // Mark player as ready for detection
        $oomphPlayer->setReady(true);
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();

        // Remove OomphPlayer instance
        $this->playerManager->remove($player);
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $oomphPlayer = $this->playerManager->get($player);

        if ($oomphPlayer === null) {
            return;
        }

        // Additional movement validation
        $from = $event->getFrom();
        $to = $event->getTo();

        // Movement state tracking is primarily handled via PlayerAuthInput packets
        // This event can be used for additional server-side validation if needed
    }

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if (!$damager instanceof Player) {
            return;
        }

        $oomphPlayer = $this->playerManager->get($damager);
        if ($oomphPlayer === null) {
            return;
        }

        // Combat logging - track attacked entity
        $combat = $oomphPlayer->getCombatComponent();
        $combat->addAttackedEntity($entity->getId());

        // Combat validation is primarily handled via InventoryTransaction packets
        // This event can be used for additional server-side combat validation
    }

    /**
     * Convert device OS code to human-readable name
     */
    private function getDeviceOSName(int $os): string {
        return match($os) {
            1 => "Android",
            2 => "iOS",
            3 => "macOS",
            4 => "FireOS",
            5 => "GearVR",
            6 => "HoloLens",
            7 => "Windows 10",
            8 => "Windows",
            9 => "Dedicated",
            10 => "tvOS",
            11 => "PlayStation",
            12 => "Xbox",
            13 => "Nintendo Switch",
            default => "Unknown ($os)"
        };
    }
}
