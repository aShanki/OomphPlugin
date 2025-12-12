<?php

declare(strict_types=1);

namespace Oomph\player;

use pocketmine\player\Player;

/**
 * Manages OomphPlayer instances
 * Maps Player UUID => OomphPlayer
 */
class PlayerManager {

    /** @var array<string, OomphPlayer> Maps UUID => OomphPlayer */
    private array $players = [];

    public function __construct() {
    }

    /**
     * Get an OomphPlayer by Player instance
     */
    public function get(Player $player): ?OomphPlayer {
        $uuid = $player->getUniqueId()->toString();
        return $this->players[$uuid] ?? null;
    }

    /**
     * Create a new OomphPlayer for the given player
     */
    public function create(Player $player): OomphPlayer {
        $uuid = $player->getUniqueId()->toString();
        $oomphPlayer = new OomphPlayer($player);
        $this->players[$uuid] = $oomphPlayer;
        return $oomphPlayer;
    }

    /**
     * Remove an OomphPlayer
     */
    public function remove(Player $player): void {
        $uuid = $player->getUniqueId()->toString();
        unset($this->players[$uuid]);
    }

    /**
     * Get all OomphPlayers
     * @return array<string, OomphPlayer>
     */
    public function getAll(): array {
        return $this->players;
    }

    /**
     * Check if a player has an OomphPlayer instance
     */
    public function has(Player $player): bool {
        $uuid = $player->getUniqueId()->toString();
        return isset($this->players[$uuid]);
    }

    /**
     * Get or create an OomphPlayer
     */
    public function getOrCreate(Player $player): OomphPlayer {
        $oomphPlayer = $this->get($player);
        if ($oomphPlayer === null) {
            $oomphPlayer = $this->create($player);
        }
        return $oomphPlayer;
    }

    /**
     * Alias for get() - for backwards compatibility
     */
    public function getOomphPlayer(Player $player): ?OomphPlayer {
        return $this->get($player);
    }
}
