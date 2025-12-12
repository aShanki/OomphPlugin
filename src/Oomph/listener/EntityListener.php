<?php

declare(strict_types=1);

namespace Oomph\listener;

use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use Oomph\entity\EntityDimensions;
use Oomph\Main;
use Oomph\player\PlayerManager;

/**
 * Listens for entity-related events to maintain entity tracking for lag compensation.
 * Each player has their own entity tracker that records positions of nearby entities.
 */
class EntityListener implements Listener {

    public function __construct(
        private Main $plugin,
        private PlayerManager $playerManager
    ) {}

    /**
     * Handle entity spawn - add to all nearby players' trackers
     * @priority MONITOR
     */
    public function onEntitySpawn(EntitySpawnEvent $event): void {
        $entity = $event->getEntity();
        $world = $entity->getWorld();

        // Don't track players through this event (they use AddPlayerPacket)
        if ($entity instanceof Player) {
            return;
        }

        $runtimeId = $entity->getId();
        $position = $entity->getLocation();
        $bbox = $entity->getBoundingBox();

        // Calculate dimensions from bounding box
        $width = $bbox->getXLength();
        $height = $bbox->getYLength();

        // Add to all nearby players' entity trackers
        foreach ($world->getPlayers() as $player) {
            $oomphPlayer = $this->playerManager->getOomphPlayer($player);
            if ($oomphPlayer === null) {
                continue;
            }

            $entityTracker = $oomphPlayer->getCombatComponent()->getEntityTracker();
            $entityTracker->addEntity(
                runtimeId: $runtimeId,
                position: $position,
                width: $width,
                height: $height,
                isPlayer: false
            );
        }
    }

    /**
     * Handle entity despawn - remove from all trackers
     * @priority MONITOR
     */
    public function onEntityDespawn(EntityDespawnEvent $event): void {
        $entity = $event->getEntity();
        $runtimeId = $entity->getId();

        // Remove from all online players' trackers
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $oomphPlayer = $this->playerManager->getOomphPlayer($player);
            if ($oomphPlayer === null) {
                continue;
            }

            $entityTracker = $oomphPlayer->getCombatComponent()->getEntityTracker();
            $entityTracker->removeEntity($runtimeId);
        }
    }

    /**
     * Handle entity motion - update velocity in trackers
     * @priority MONITOR
     */
    public function onEntityMotion(EntityMotionEvent $event): void {
        $entity = $event->getEntity();
        $runtimeId = $entity->getId();
        $velocity = $event->getVector();

        // Update velocity in all nearby players' trackers
        $world = $entity->getWorld();
        foreach ($world->getPlayers() as $player) {
            $oomphPlayer = $this->playerManager->getOomphPlayer($player);
            if ($oomphPlayer === null) {
                continue;
            }

            $entityTracker = $oomphPlayer->getCombatComponent()->getEntityTracker();
            if ($entityTracker->hasEntity($runtimeId)) {
                $entityTracker->updateVelocity($runtimeId, $velocity);
            }
        }
    }

    /**
     * Intercept outgoing packets to track entity positions from server.
     * This is necessary because entity positions are sent via packets, not events.
     * @priority MONITOR
     */
    public function onDataPacketSend(DataPacketSendEvent $event): void {
        $packets = $event->getPackets();
        $receivers = $event->getTargets();

        foreach ($packets as $packet) {
            // Track AddPlayerPacket
            if ($packet instanceof AddPlayerPacket) {
                $this->handleAddPlayer($packet, $receivers);
            }
            // Track AddActorPacket
            elseif ($packet instanceof AddActorPacket) {
                $this->handleAddActor($packet, $receivers);
            }
            // Track MoveActorAbsolutePacket
            elseif ($packet instanceof MoveActorAbsolutePacket) {
                $this->handleMoveActor($packet, $receivers);
            }
            // Track SetActorMotionPacket
            elseif ($packet instanceof SetActorMotionPacket) {
                $this->handleSetActorMotion($packet, $receivers);
            }
            // Track RemoveActorPacket
            elseif ($packet instanceof RemoveActorPacket) {
                $this->handleRemoveActor($packet, $receivers);
            }
        }
    }

    /**
     * Handle AddPlayerPacket - another player spawned
     */
    private function handleAddPlayer(AddPlayerPacket $packet, array $receivers): void {
        $runtimeId = $packet->actorRuntimeId;
        $position = $packet->position;

        $serverTick = Server::getInstance()->getTick();

        foreach ($receivers as $receiver) {
            $player = $receiver->getPlayer();
            if ($player === null) {
                continue;
            }

            $oomphPlayer = $this->playerManager->getOomphPlayer($player);
            if ($oomphPlayer === null) {
                continue;
            }

            $entityTracker = $oomphPlayer->getCombatComponent()->getEntityTracker();
            $entityTracker->addEntity(
                runtimeId: $runtimeId,
                position: $position,
                width: EntityDimensions::PLAYER_WIDTH,
                height: EntityDimensions::PLAYER_HEIGHT,
                isPlayer: true
            );
        }
    }

    /**
     * Handle AddActorPacket - entity spawned
     */
    private function handleAddActor(AddActorPacket $packet, array $receivers): void {
        $runtimeId = $packet->actorRuntimeId;
        $position = $packet->position;
        $entityType = $packet->type;

        // Get dimensions for this entity type
        [$width, $height] = EntityDimensions::getDimensions($entityType);

        $serverTick = Server::getInstance()->getTick();

        foreach ($receivers as $receiver) {
            $player = $receiver->getPlayer();
            if ($player === null) {
                continue;
            }

            $oomphPlayer = $this->playerManager->getOomphPlayer($player);
            if ($oomphPlayer === null) {
                continue;
            }

            $entityTracker = $oomphPlayer->getCombatComponent()->getEntityTracker();
            $entityTracker->addEntity(
                runtimeId: $runtimeId,
                position: $position,
                width: $width,
                height: $height,
                isPlayer: false
            );
        }
    }

    /**
     * Handle MoveActorAbsolutePacket - entity moved
     */
    private function handleMoveActor(MoveActorAbsolutePacket $packet, array $receivers): void {
        $runtimeId = $packet->actorRuntimeId;
        $position = $packet->position;

        $serverTick = Server::getInstance()->getTick();
        $wasTeleport = ($packet->flags & MoveActorAbsolutePacket::FLAG_TELEPORT) !== 0;

        foreach ($receivers as $receiver) {
            $player = $receiver->getPlayer();
            if ($player === null) {
                continue;
            }

            $oomphPlayer = $this->playerManager->getOomphPlayer($player);
            if ($oomphPlayer === null) {
                continue;
            }

            $entityTracker = $oomphPlayer->getCombatComponent()->getEntityTracker();
            if ($entityTracker->hasEntity($runtimeId)) {
                $entityTracker->updateEntity($runtimeId, $position, $serverTick, $wasTeleport);
            }
        }
    }

    /**
     * Handle SetActorMotionPacket - entity velocity changed
     */
    private function handleSetActorMotion(SetActorMotionPacket $packet, array $receivers): void {
        $runtimeId = $packet->actorRuntimeId;
        $velocity = $packet->motion;

        foreach ($receivers as $receiver) {
            $player = $receiver->getPlayer();
            if ($player === null) {
                continue;
            }

            $oomphPlayer = $this->playerManager->getOomphPlayer($player);
            if ($oomphPlayer === null) {
                continue;
            }

            $entityTracker = $oomphPlayer->getCombatComponent()->getEntityTracker();
            if ($entityTracker->hasEntity($runtimeId)) {
                $entityTracker->updateVelocity($runtimeId, $velocity);
            }
        }
    }

    /**
     * Handle RemoveActorPacket - entity removed
     */
    private function handleRemoveActor(RemoveActorPacket $packet, array $receivers): void {
        $runtimeId = $packet->actorUniqueId;

        foreach ($receivers as $receiver) {
            $player = $receiver->getPlayer();
            if ($player === null) {
                continue;
            }

            $oomphPlayer = $this->playerManager->getOomphPlayer($player);
            if ($oomphPlayer === null) {
                continue;
            }

            $entityTracker = $oomphPlayer->getCombatComponent()->getEntityTracker();
            $entityTracker->removeEntity($runtimeId);
        }
    }
}
