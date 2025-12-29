<?php

declare(strict_types=1);

namespace Oomph\listener;

use Oomph\Main;
use Oomph\player\PlayerManager;
use Oomph\player\OomphPlayer;
use Oomph\entity\TrackedEntity;
use Oomph\detection\combat\AutoclickerB;
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
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CreativeCreateStackRequestAction;
use pocketmine\Server;
use pocketmine\entity\Living;

class PacketListener implements Listener {

    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
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

        // BadPacketA: Validate simulation frame
        $badPacketA = $dm->get("BadPacketA");
        if ($badPacketA instanceof BadPacketA) {
            $currentFrame = $packet->getTick();
            $lastFrame = $oomphPlayer->getSimulationFrame();
            $badPacketA->process($oomphPlayer, $currentFrame, $lastFrame);
            $oomphPlayer->setSimulationFrame($currentFrame);
        }

        // AimA: Check rotation deltas for aimbot (mouse input only)
        $aimA = $dm->get("AimA");
        if ($aimA instanceof AimA) {
            $aimA->process($oomphPlayer);
        }

        // AutoclickerB: Check click timing consistency (left and right clicks)
        $autoclickerB = $dm->get("AutoclickerB");
        if ($autoclickerB instanceof AutoclickerB) {
            $autoclickerB->process($oomphPlayer, $leftClick);
        }

        // BadPacketE: Validate moveVec is within [-1.001, 1.001]
        $badPacketE = $dm->get("BadPacketE");
        if ($badPacketE instanceof BadPacketE) {
            $badPacketE->process($oomphPlayer, $moveVecX, $moveVecZ);
        }

        // BadPacketF: Validate hotbar slot
        // Note: Hotbar slot validation is handled through inventory events, not PlayerAuthInput
        // The packet doesn't expose hotbar slot directly in PM5

        // Update clicks component
        $clicks->update();

        // Tick reach detection (updates teleport counter)
        $reachA = $dm->get("ReachA");
        if ($reachA instanceof ReachA) {
            $reachA->tick();
        }

        // InvMoveA: Check preFlag on PlayerAuthInput (second phase)
        $invMoveA = $dm->get("InvMoveA");
        if ($invMoveA instanceof InvMoveA) {
            $impulse = new Vector2(
                $movement->getImpulseForward(),
                $movement->getImpulseStrafe()
            );
            $invMoveA->onPlayerAuthInput($oomphPlayer, $impulse);
        }
    }

    private function handleInventoryTransaction(InventoryTransactionPacket $packet, OomphPlayer $oomphPlayer): void {
        $transactionData = $packet->trData;
        $dm = $oomphPlayer->getDetectionManager();
        $combatComponent = $oomphPlayer->getCombatComponent();
        $movementComponent = $oomphPlayer->getMovementComponent();
        $entityTracker = $combatComponent->getEntityTracker();

        // Check for UseItemTransactionData (block placement/interaction)
        if ($transactionData instanceof UseItemTransactionData) {
            $actionType = $transactionData->getActionType();

            // BadPacketG: Validate block face (skip for click air)
            if ($actionType !== UseItemTransactionData::ACTION_CLICK_AIR) {
                $badPacketG = $dm->get("BadPacketG");
                if ($badPacketG instanceof BadPacketG) {
                    $blockFace = $transactionData->getFace();
                    $badPacketG->process($oomphPlayer, $blockFace);
                }
            }

            // Track right clicks for block placement/interaction
            if ($actionType === UseItemTransactionData::ACTION_CLICK_BLOCK) {
                $clicks = $oomphPlayer->getClicksComponent();
                $clicks->incrementRightClicks();

                // AutoclickerB: Check right click consistency for block placement
                $autoclickerB = $dm->get("AutoclickerB");
                if ($autoclickerB instanceof AutoclickerB) {
                    $autoclickerB->process($oomphPlayer, false, true);
                }

                // ScaffoldA: Check for zero click position on block clicks
                $scaffoldA = $dm->get("ScaffoldA");
                if ($scaffoldA instanceof ScaffoldA) {
                    $clickPos = $transactionData->getClickPosition();
                    $scaffoldA->check($oomphPlayer, $clickPos);
                }
            }
        }

        // Check for UseItemOnEntityTransactionData (combat)
        if ($transactionData instanceof UseItemOnEntityTransactionData) {
            $entityRuntimeId = $transactionData->getActorRuntimeId();
            $actionType = $transactionData->getActionType();

            // Check if player is attacking
            if ($actionType === UseItemOnEntityTransactionData::ACTION_ATTACK) {
                $player = $oomphPlayer->getPlayer();
                $shouldCancelAttack = false;

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
                    $killauraA->check($oomphPlayer, $oomphPlayer->getServerTick());

                    // Check if killaura detection wants to cancel
                    if ($killauraA->isCancellable() && $killauraA->shouldCancel()) {
                        $shouldCancelAttack = true;
                        $oomphPlayer->getCancellationManager()->setCancelAttack(true, "KillauraA");
                    }
                }

                // Get target entity for reach/hitbox checks
                $world = $player->getWorld();
                $target = $world->getEntity($entityRuntimeId);

                // Get or create TrackedEntity for combat validation
                $trackedEntity = $entityTracker->getEntity($entityRuntimeId);
                if ($trackedEntity === null && $target instanceof Living) {
                    // Add entity to tracker if not present
                    $entityTracker->addEntity(
                        runtimeId: $entityRuntimeId,
                        position: $target->getPosition()->asVector3(),
                        width: $target->getSize()->getWidth(),
                        height: $target->getSize()->getHeight(),
                        scale: $target->getScale(),
                        isPlayer: $target instanceof \pocketmine\player\Player
                    );
                    $trackedEntity = $entityTracker->getEntity($entityRuntimeId);
                }

                if ($target !== null && $trackedEntity !== null) {
                    // Set up combat validation using 10-step lerp
                    $attackerPos = $movementComponent->getPosition();
                    $lastAttackerPos = $movementComponent->getPrevPosition();
                    $isSneaking = $movementComponent->isSneaking();

                    // Record attack and set up validation state
                    $combatComponent->attack(
                        input: $packet,
                        attackerPos: $attackerPos,
                        lastAttackerPos: $lastAttackerPos,
                        isSneaking: $isSneaking,
                        target: $trackedEntity,
                        clientTick: $oomphPlayer->getClientTick()
                    );

                    // Calculate hit validity using 10-step lerp validation
                    $rotation = new Vector3(
                        $movementComponent->getYaw(),
                        $movementComponent->getPitch(),
                        $movementComponent->getYaw() // headYaw
                    );
                    $lastRotation = new Vector3(
                        $movementComponent->getLastYaw(),
                        $movementComponent->getLastPitch(),
                        $movementComponent->getLastYaw() // last headYaw
                    );

                    $isValidHit = $combatComponent->calculate($rotation, $lastRotation, $oomphPlayer->getInputMode());

                    // ReachA: Raycast-based reach check using combat component results
                    $reachA = $dm->get("ReachA");
                    if ($reachA instanceof ReachA) {
                        $reachA->check($oomphPlayer);

                        // Check if reach detection wants to cancel
                        if ($reachA->isCancellable() && $reachA->shouldCancel()) {
                            $shouldCancelAttack = true;
                            $oomphPlayer->getCancellationManager()->setCancelAttack(true, "ReachA");
                        }
                    }

                    // ReachB: Closest point distance check (uses raw results)
                    $reachB = $dm->get("ReachB");
                    if ($reachB instanceof ReachB) {
                        $reachB->check($oomphPlayer, $target);
                    }

                    // NOTE: HitboxA is now handled in handleInteract() using the correct
                    // InteractPacket::ACTION_MOUSEOVER position data instead of the attack
                    // transaction's click position (which was causing false positives)

                    // Cancel attack if any detection flagged OR hit validation failed
                    if ($shouldCancelAttack || !$isValidHit) {
                        // Use cancellation manager to mark the attack as cancelled
                        $cancellation = $oomphPlayer->getCancellationManager();
                        if (!$cancellation->shouldCancelAttack()) {
                            $cancellation->setCancelAttack(true, $isValidHit ? "Detection" : "InvalidHit");
                        }
                    }
                }
            }
        }
    }

    private function handleItemStackRequest(ItemStackRequestPacket $packet, OomphPlayer $oomphPlayer): void {
        $dm = $oomphPlayer->getDetectionManager();
        $movement = $oomphPlayer->getMovementComponent();

        // InvMoveA: Record movement state during inventory action (first phase)
        $invMoveA = $dm->get("InvMoveA");
        if ($invMoveA instanceof InvMoveA) {
            $impulse = new Vector2(
                $movement->getImpulseForward(),
                $movement->getImpulseStrafe()
            );
            $invMoveA->onItemStackRequest($oomphPlayer, $impulse);
        }

        // BadPacketD: Check for creative transactions in survival
        $badPacketD = $dm->get("BadPacketD");
        if ($badPacketD instanceof BadPacketD) {
            $isCreativeAction = false;

            // Check all requests for creative actions
            foreach ($packet->getRequests() as $request) {
                foreach ($request->getActions() as $action) {
                    if ($action instanceof CreativeCreateStackRequestAction) {
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
                PlayerAction::START_BREAK,
                PlayerAction::ABORT_BREAK,
                PlayerAction::STOP_BREAK,
                PlayerAction::CRACK_BREAK,
                PlayerAction::CREATIVE_PLAYER_DESTROY_BLOCK
            ], true);

            // Only flag creative_player_destroy_block in non-creative
            if ($action === PlayerAction::CREATIVE_PLAYER_DESTROY_BLOCK) {
                $badPacketC->process($oomphPlayer, true);
            }
        }
    }

    /**
     * Handle InteractPacket - specifically ACTION_MOUSEOVER for HitboxA detection
     *
     * The Go reference (hitbox_a.go lines 52-74) uses InteractPacket with
     * ACTION_MOUSEOVER which contains the client's reported hitbox intersection point.
     * This is the correct packet for HitboxA validation, NOT the attack transaction.
     */
    private function handleInteract(InteractPacket $packet, OomphPlayer $oomphPlayer): void {
        // ACTION_MOUSEOVER contains x, y, z position data where client's
        // raycast intersected the target entity's bounding box
        if ($packet->action !== InteractPacket::ACTION_MOUSEOVER) {
            return;
        }

        $dm = $oomphPlayer->getDetectionManager();
        $combatComponent = $oomphPlayer->getCombatComponent();
        $entityTracker = $combatComponent->getEntityTracker();

        // Get the click position from InteractPacket (x, y, z fields for ACTION_MOUSEOVER)
        // These typed properties are only initialized for ACTION_MOUSEOVER
        // isset() returns false for uninitialized typed properties
        if (!isset($packet->x, $packet->y, $packet->z)) {
            // Properties not initialized - skip HitboxA check
            return;
        }

        $x = $packet->x;
        $y = $packet->y;
        $z = $packet->z;

        // Skip if position is zero/empty (Go: pk.Position == utils.EmptyVec32)
        if ($x === 0.0 && $y === 0.0 && $z === 0.0) {
            return;
        }
        $clickPosition = new Vector3($x, $y, $z);

        // Get target entity from tracker
        $targetRuntimeId = $packet->targetActorRuntimeId;
        $trackedEntity = $entityTracker->getEntity($targetRuntimeId);

        if ($trackedEntity === null) {
            // Try to add from world entity if not tracked
            $player = $oomphPlayer->getPlayer();
            $world = $player->getWorld();
            $target = $world->getEntity($targetRuntimeId);

            if ($target instanceof \pocketmine\entity\Living) {
                $entityTracker->addEntity(
                    runtimeId: $targetRuntimeId,
                    position: $target->getPosition()->asVector3(),
                    width: $target->getSize()->getWidth(),
                    height: $target->getSize()->getHeight(),
                    scale: $target->getScale(),
                    isPlayer: $target instanceof \pocketmine\player\Player
                );
                $trackedEntity = $entityTracker->getEntity($targetRuntimeId);
            }
        }

        if ($trackedEntity === null) {
            return;
        }

        // Run HitboxA check with the correct InteractPacket position data
        $hitboxA = $dm->get("HitboxA");
        if ($hitboxA instanceof HitboxA) {
            $hitboxA->check($oomphPlayer, $trackedEntity, $clickPosition);
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
