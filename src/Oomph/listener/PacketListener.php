<?php

declare(strict_types=1);

namespace Oomph\listener;

use Oomph\Main;
use Oomph\player\PlayerManager;
use Oomph\player\OomphPlayer;
use Oomph\detection\combat\AutoclickerA;
use Oomph\detection\combat\AimA;
use Oomph\detection\combat\KillauraA;
use Oomph\detection\combat\ReachA;
use Oomph\detection\combat\ReachB;
use Oomph\detection\combat\HitboxA;
use Oomph\detection\packet\BadPacketB;
use Oomph\detection\packet\BadPacketC;
use Oomph\detection\packet\BadPacketD;
use Oomph\detection\packet\BadPacketE;
use Oomph\detection\packet\BadPacketF;
use Oomph\detection\movement\InvMoveA;
use Oomph\detection\world\ScaffoldA;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ItemStackRequestPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\PlayerActionType;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftCreativeStackRequestAction;
use pocketmine\Server;

class PacketListener implements Listener {

    public function __construct(
        private Main $plugin,
        private PlayerManager $playerManager
    ) {}

    /**
     * @priority HIGHEST
     */
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();

        if ($player === null) {
            return;
        }

        $oomphPlayer = $this->playerManager->get($player);
        if ($oomphPlayer === null) {
            return;
        }

        $serverTick = Server::getInstance()->getTick();
        $oomphPlayer->setServerTick($serverTick);

        // Process PlayerAuthInputPacket
        if ($packet instanceof PlayerAuthInputPacket) {
            $this->handlePlayerAuthInput($packet, $oomphPlayer);
        }

        // Process InventoryTransactionPacket
        if ($packet instanceof InventoryTransactionPacket) {
            $this->handleInventoryTransaction($packet, $oomphPlayer);
        }

        // Process ItemStackRequestPacket
        if ($packet instanceof ItemStackRequestPacket) {
            $this->handleItemStackRequest($packet, $oomphPlayer);
        }

        // Process PlayerActionPacket
        if ($packet instanceof PlayerActionPacket) {
            $this->handlePlayerAction($packet, $oomphPlayer);
        }

        // Process InteractPacket
        if ($packet instanceof InteractPacket) {
            $this->handleInteract($packet, $oomphPlayer);
        }

        // Process AnimatePacket
        if ($packet instanceof AnimatePacket) {
            $this->handleAnimate($packet, $oomphPlayer);
        }
    }

    private function handlePlayerAuthInput(PlayerAuthInputPacket $packet, OomphPlayer $oomphPlayer): void {
        $movement = $oomphPlayer->getMovementComponent();
        $combat = $oomphPlayer->getCombatComponent();
        $clicks = $oomphPlayer->getClicksComponent();
        $dm = $oomphPlayer->getDetectionManager();

        // Extract position and rotation
        $position = $packet->getPosition();
        $yaw = $packet->getYaw();
        $pitch = $packet->getPitch();

        // Update MovementComponent (store previous state first via update)
        $movement->update();
        $movement->updatePosition($position->subtract(0, 1.62, 0)); // Remove eye height offset
        $movement->updateRotation($yaw, $pitch);

        // Extract input data
        $inputFlags = $packet->getInputFlags();
        $inputMode = $packet->getInputMode();
        $moveVecX = $packet->getMoveVecX();
        $moveVecZ = $packet->getMoveVecZ();

        // Update player's input mode
        $oomphPlayer->setInputMode($inputMode);

        // Store impulse values
        $movement->setImpulseForward($moveVecZ);
        $movement->setImpulseStrafe($moveVecX);

        // Check for left click (MISSED_SWING flag)
        $leftClick = $inputFlags->get(PlayerAuthInputFlags::MISSED_SWING);
        if ($leftClick) {
            $clicks->addLeftClick($oomphPlayer->getServerTick());
            $combat->updateLastSwingTick($oomphPlayer->getServerTick());
        }

        // === Run detection checks ===

        // AimA: Check rotation deltas for aimbot (mouse input only)
        $aimA = $dm->get("AimA");
        if ($aimA instanceof AimA) {
            $aimA->process($oomphPlayer);
        }

        // AutoclickerA: Check CPS limits
        $autoclickerA = $dm->get("AutoclickerA");
        if ($autoclickerA instanceof AutoclickerA) {
            $autoclickerA->process($oomphPlayer, $leftClick, false);
        }

        // BadPacketE: Validate moveVec is within [-1.001, 1.001]
        $badPacketE = $dm->get("BadPacketE");
        if ($badPacketE instanceof BadPacketE) {
            $badPacketE->process($oomphPlayer, $moveVecX, $moveVecZ);
        }

        // Update clicks component
        $clicks->update();

        // Tick reach detection (updates teleport counter)
        $reachA = $dm->get("ReachA");
        if ($reachA instanceof ReachA) {
            $reachA->tick();
        }
    }

    private function handleInventoryTransaction(InventoryTransactionPacket $packet, OomphPlayer $oomphPlayer): void {
        $transactionData = $packet->trData;
        $dm = $oomphPlayer->getDetectionManager();

        // Check for UseItemOnEntityTransactionData (combat)
        if ($transactionData instanceof UseItemOnEntityTransactionData) {
            $entityRuntimeId = $transactionData->getActorRuntimeId();
            $actionType = $transactionData->getActionType();

            // Check if player is attacking
            if ($actionType === UseItemOnEntityTransactionData::ACTION_ATTACK) {
                $player = $oomphPlayer->getPlayer();

                // BadPacketB: Check for self-hitting
                $badPacketB = $dm->get("BadPacketB");
                if ($badPacketB instanceof BadPacketB) {
                    $badPacketB->process($oomphPlayer, $entityRuntimeId);
                }

                // Skip further checks if self-hitting
                if ($entityRuntimeId === $player->getId()) {
                    return;
                }

                // KillauraA: Check if attack occurred without swing animation
                $killauraA = $dm->get("KillauraA");
                if ($killauraA instanceof KillauraA) {
                    $killauraA->check($oomphPlayer);
                }

                // Get target entity for reach checks
                $world = $player->getWorld();
                $target = $world->getEntity($entityRuntimeId);

                if ($target !== null) {
                    // ReachA: Raycast-based reach check
                    $reachA = $dm->get("ReachA");
                    if ($reachA instanceof ReachA) {
                        $reachA->check($oomphPlayer, $target, $oomphPlayer->getInputMode());
                    }

                    // ReachB: Closest point distance check
                    $reachB = $dm->get("ReachB");
                    if ($reachB instanceof ReachB) {
                        $reachB->check($oomphPlayer, $target);
                    }
                }
            }
        }
    }

    private function handleItemStackRequest(ItemStackRequestPacket $packet, OomphPlayer $oomphPlayer): void {
        $dm = $oomphPlayer->getDetectionManager();
        $movement = $oomphPlayer->getMovementComponent();

        // InvMoveA: Check if player is moving while in inventory
        $invMoveA = $dm->get("InvMoveA");
        if ($invMoveA instanceof InvMoveA) {
            $impulse = new Vector2(
                $movement->getImpulseForward(),
                $movement->getImpulseStrafe()
            );
            $invMoveA->check($oomphPlayer, $impulse);
        }

        // BadPacketD: Check for creative transactions in survival
        $badPacketD = $dm->get("BadPacketD");
        if ($badPacketD instanceof BadPacketD) {
            $isCreativeAction = false;

            // Check all requests for creative actions
            foreach ($packet->getRequests() as $request) {
                foreach ($request->getActions() as $action) {
                    if ($action instanceof CraftCreativeStackRequestAction) {
                        $isCreativeAction = true;
                        break 2;
                    }
                }
            }

            if ($isCreativeAction) {
                $badPacketD->process($oomphPlayer, true);
            }
        }
    }

    private function handlePlayerAction(PlayerActionPacket $packet, OomphPlayer $oomphPlayer): void {
        $dm = $oomphPlayer->getDetectionManager();

        // BadPacketC: Check for invalid block breaking
        $badPacketC = $dm->get("BadPacketC");
        if ($badPacketC instanceof BadPacketC) {
            $action = $packet->action;

            // Check for break-related actions
            $isBreakAction = in_array($action, [
                PlayerActionType::START_BREAK,
                PlayerActionType::ABORT_BREAK,
                PlayerActionType::STOP_BREAK,
                PlayerActionType::CRACK_BREAK,
                PlayerActionType::CREATIVE_PLAYER_DESTROY_BLOCK
            ], true);

            // Only flag creative_player_destroy_block in non-creative
            if ($action === PlayerActionType::CREATIVE_PLAYER_DESTROY_BLOCK) {
                $badPacketC->process($oomphPlayer, true);
            }
        }
    }

    private function handleInteract(InteractPacket $packet, OomphPlayer $oomphPlayer): void {
        // InteractPacket only has action and actorRuntimeId
        // HitboxA detection requires position data which isn't available in this packet
        // HitboxA would need to be triggered from a different packet type or use entity tracking

        // For now, we just track the interaction for potential future use
        if ($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE) {
            // Player left a vehicle - could be used for vehicle-related checks
        }
    }

    private function handleAnimate(AnimatePacket $packet, OomphPlayer $oomphPlayer): void {
        // Update lastSwingTick in CombatComponent
        if ($packet->action === AnimatePacket::ACTION_SWING_ARM) {
            $combat = $oomphPlayer->getCombatComponent();
            $combat->updateLastSwingTick($oomphPlayer->getServerTick());
        }
    }
}
