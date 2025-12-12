<?php

declare(strict_types=1);

namespace Oomph\entity;

/**
 * Static class containing entity hitbox dimensions.
 * Used for accurate collision detection and reach validation.
 */
class EntityDimensions {

    // Player dimensions
    public const PLAYER_WIDTH = 0.6;
    public const PLAYER_HEIGHT = 1.8;

    // Hostile mobs
    public const ZOMBIE_WIDTH = 0.6;
    public const ZOMBIE_HEIGHT = 1.95;
    public const ZOMBIE_PIGMAN_WIDTH = 0.6;
    public const ZOMBIE_PIGMAN_HEIGHT = 1.95;
    public const HUSK_WIDTH = 0.6;
    public const HUSK_HEIGHT = 1.95;
    public const DROWNED_WIDTH = 0.6;
    public const DROWNED_HEIGHT = 1.95;

    public const SKELETON_WIDTH = 0.6;
    public const SKELETON_HEIGHT = 1.99;
    public const WITHER_SKELETON_WIDTH = 0.7;
    public const WITHER_SKELETON_HEIGHT = 2.4;
    public const STRAY_WIDTH = 0.6;
    public const STRAY_HEIGHT = 1.99;

    public const CREEPER_WIDTH = 0.6;
    public const CREEPER_HEIGHT = 1.7;

    public const SPIDER_WIDTH = 1.4;
    public const SPIDER_HEIGHT = 0.9;
    public const CAVE_SPIDER_WIDTH = 0.7;
    public const CAVE_SPIDER_HEIGHT = 0.5;

    public const ENDERMAN_WIDTH = 0.6;
    public const ENDERMAN_HEIGHT = 2.9;

    public const BLAZE_WIDTH = 0.6;
    public const BLAZE_HEIGHT = 1.8;

    public const WITCH_WIDTH = 0.6;
    public const WITCH_HEIGHT = 1.95;

    public const SILVERFISH_WIDTH = 0.4;
    public const SILVERFISH_HEIGHT = 0.3;

    public const ENDERMITE_WIDTH = 0.4;
    public const ENDERMITE_HEIGHT = 0.3;

    public const GUARDIAN_WIDTH = 0.85;
    public const GUARDIAN_HEIGHT = 0.85;
    public const ELDER_GUARDIAN_WIDTH = 1.9975;
    public const ELDER_GUARDIAN_HEIGHT = 1.9975;

    public const SHULKER_WIDTH = 1.0;
    public const SHULKER_HEIGHT = 1.0;

    public const VEX_WIDTH = 0.4;
    public const VEX_HEIGHT = 0.8;

    public const VINDICATOR_WIDTH = 0.6;
    public const VINDICATOR_HEIGHT = 1.95;
    public const EVOKER_WIDTH = 0.6;
    public const EVOKER_HEIGHT = 1.95;
    public const PILLAGER_WIDTH = 0.6;
    public const PILLAGER_HEIGHT = 1.95;
    public const RAVAGER_WIDTH = 1.95;
    public const RAVAGER_HEIGHT = 2.2;

    public const PHANTOM_WIDTH = 0.9;
    public const PHANTOM_HEIGHT = 0.5;

    public const PIGLIN_WIDTH = 0.6;
    public const PIGLIN_HEIGHT = 1.95;
    public const PIGLIN_BRUTE_WIDTH = 0.6;
    public const PIGLIN_BRUTE_HEIGHT = 1.95;
    public const HOGLIN_WIDTH = 1.3965;
    public const HOGLIN_HEIGHT = 1.4;
    public const ZOGLIN_WIDTH = 1.3965;
    public const ZOGLIN_HEIGHT = 1.4;

    public const WARDEN_WIDTH = 0.9;
    public const WARDEN_HEIGHT = 2.9;

    // Passive/neutral mobs
    public const PIG_WIDTH = 0.9;
    public const PIG_HEIGHT = 0.9;
    public const COW_WIDTH = 0.9;
    public const COW_HEIGHT = 1.4;
    public const SHEEP_WIDTH = 0.9;
    public const SHEEP_HEIGHT = 1.3;
    public const CHICKEN_WIDTH = 0.4;
    public const CHICKEN_HEIGHT = 0.7;
    public const RABBIT_WIDTH = 0.4;
    public const RABBIT_HEIGHT = 0.5;
    public const WOLF_WIDTH = 0.6;
    public const WOLF_HEIGHT = 0.85;
    public const CAT_WIDTH = 0.6;
    public const CAT_HEIGHT = 0.7;
    public const OCELOT_WIDTH = 0.6;
    public const OCELOT_HEIGHT = 0.7;
    public const HORSE_WIDTH = 1.3965;
    public const HORSE_HEIGHT = 1.6;
    public const LLAMA_WIDTH = 0.9;
    public const LLAMA_HEIGHT = 1.87;
    public const POLAR_BEAR_WIDTH = 1.3;
    public const POLAR_BEAR_HEIGHT = 1.4;
    public const PANDA_WIDTH = 1.3;
    public const PANDA_HEIGHT = 1.25;
    public const FOX_WIDTH = 0.6;
    public const FOX_HEIGHT = 0.7;
    public const BEE_WIDTH = 0.7;
    public const BEE_HEIGHT = 0.6;
    public const GOAT_WIDTH = 0.9;
    public const GOAT_HEIGHT = 1.3;
    public const AXOLOTL_WIDTH = 0.75;
    public const AXOLOTL_HEIGHT = 0.42;
    public const GLOW_SQUID_WIDTH = 0.8;
    public const GLOW_SQUID_HEIGHT = 0.8;
    public const FROG_WIDTH = 0.5;
    public const FROG_HEIGHT = 0.5;
    public const ALLAY_WIDTH = 0.35;
    public const ALLAY_HEIGHT = 0.6;
    public const CAMEL_WIDTH = 1.7;
    public const CAMEL_HEIGHT = 2.375;
    public const SNIFFER_WIDTH = 1.9;
    public const SNIFFER_HEIGHT = 1.75;

    // Bosses
    public const ENDER_DRAGON_WIDTH = 16.0;
    public const ENDER_DRAGON_HEIGHT = 8.0;
    public const WITHER_WIDTH = 0.9;
    public const WITHER_HEIGHT = 3.5;

    // Projectiles
    public const ARROW_WIDTH = 0.5;
    public const ARROW_HEIGHT = 0.5;
    public const FIREBALL_WIDTH = 1.0;
    public const FIREBALL_HEIGHT = 1.0;
    public const SMALL_FIREBALL_WIDTH = 0.3125;
    public const SMALL_FIREBALL_HEIGHT = 0.3125;

    /**
     * Get entity dimensions by type name.
     * Returns [width, height] or defaults to player dimensions if unknown.
     */
    public static function getDimensions(string $entityType): array {
        return match (strtolower($entityType)) {
            // Players
            'player', 'human' => [self::PLAYER_WIDTH, self::PLAYER_HEIGHT],

            // Hostile mobs
            'zombie' => [self::ZOMBIE_WIDTH, self::ZOMBIE_HEIGHT],
            'zombie_pigman', 'zombified_piglin' => [self::ZOMBIE_PIGMAN_WIDTH, self::ZOMBIE_PIGMAN_HEIGHT],
            'husk' => [self::HUSK_WIDTH, self::HUSK_HEIGHT],
            'drowned' => [self::DROWNED_WIDTH, self::DROWNED_HEIGHT],
            'skeleton' => [self::SKELETON_WIDTH, self::SKELETON_HEIGHT],
            'wither_skeleton' => [self::WITHER_SKELETON_WIDTH, self::WITHER_SKELETON_HEIGHT],
            'stray' => [self::STRAY_WIDTH, self::STRAY_HEIGHT],
            'creeper' => [self::CREEPER_WIDTH, self::CREEPER_HEIGHT],
            'spider' => [self::SPIDER_WIDTH, self::SPIDER_HEIGHT],
            'cave_spider' => [self::CAVE_SPIDER_WIDTH, self::CAVE_SPIDER_HEIGHT],
            'enderman' => [self::ENDERMAN_WIDTH, self::ENDERMAN_HEIGHT],
            'blaze' => [self::BLAZE_WIDTH, self::BLAZE_HEIGHT],
            'witch' => [self::WITCH_WIDTH, self::WITCH_HEIGHT],
            'silverfish' => [self::SILVERFISH_WIDTH, self::SILVERFISH_HEIGHT],
            'endermite' => [self::ENDERMITE_WIDTH, self::ENDERMITE_HEIGHT],
            'guardian' => [self::GUARDIAN_WIDTH, self::GUARDIAN_HEIGHT],
            'elder_guardian' => [self::ELDER_GUARDIAN_WIDTH, self::ELDER_GUARDIAN_HEIGHT],
            'shulker' => [self::SHULKER_WIDTH, self::SHULKER_HEIGHT],
            'vex' => [self::VEX_WIDTH, self::VEX_HEIGHT],
            'vindicator' => [self::VINDICATOR_WIDTH, self::VINDICATOR_HEIGHT],
            'evoker' => [self::EVOKER_WIDTH, self::EVOKER_HEIGHT],
            'pillager' => [self::PILLAGER_WIDTH, self::PILLAGER_HEIGHT],
            'ravager' => [self::RAVAGER_WIDTH, self::RAVAGER_HEIGHT],
            'phantom' => [self::PHANTOM_WIDTH, self::PHANTOM_HEIGHT],
            'piglin' => [self::PIGLIN_WIDTH, self::PIGLIN_HEIGHT],
            'piglin_brute' => [self::PIGLIN_BRUTE_WIDTH, self::PIGLIN_BRUTE_HEIGHT],
            'hoglin' => [self::HOGLIN_WIDTH, self::HOGLIN_HEIGHT],
            'zoglin' => [self::ZOGLIN_WIDTH, self::ZOGLIN_HEIGHT],
            'warden' => [self::WARDEN_WIDTH, self::WARDEN_HEIGHT],

            // Passive/neutral mobs
            'pig' => [self::PIG_WIDTH, self::PIG_HEIGHT],
            'cow' => [self::COW_WIDTH, self::COW_HEIGHT],
            'sheep' => [self::SHEEP_WIDTH, self::SHEEP_HEIGHT],
            'chicken' => [self::CHICKEN_WIDTH, self::CHICKEN_HEIGHT],
            'rabbit' => [self::RABBIT_WIDTH, self::RABBIT_HEIGHT],
            'wolf' => [self::WOLF_WIDTH, self::WOLF_HEIGHT],
            'cat' => [self::CAT_WIDTH, self::CAT_HEIGHT],
            'ocelot' => [self::OCELOT_WIDTH, self::OCELOT_HEIGHT],
            'horse' => [self::HORSE_WIDTH, self::HORSE_HEIGHT],
            'llama' => [self::LLAMA_WIDTH, self::LLAMA_HEIGHT],
            'polar_bear' => [self::POLAR_BEAR_WIDTH, self::POLAR_BEAR_HEIGHT],
            'panda' => [self::PANDA_WIDTH, self::PANDA_HEIGHT],
            'fox' => [self::FOX_WIDTH, self::FOX_HEIGHT],
            'bee' => [self::BEE_WIDTH, self::BEE_HEIGHT],
            'goat' => [self::GOAT_WIDTH, self::GOAT_HEIGHT],
            'axolotl' => [self::AXOLOTL_WIDTH, self::AXOLOTL_HEIGHT],
            'glow_squid' => [self::GLOW_SQUID_WIDTH, self::GLOW_SQUID_HEIGHT],
            'frog' => [self::FROG_WIDTH, self::FROG_HEIGHT],
            'allay' => [self::ALLAY_WIDTH, self::ALLAY_HEIGHT],
            'camel' => [self::CAMEL_WIDTH, self::CAMEL_HEIGHT],
            'sniffer' => [self::SNIFFER_WIDTH, self::SNIFFER_HEIGHT],

            // Bosses
            'ender_dragon' => [self::ENDER_DRAGON_WIDTH, self::ENDER_DRAGON_HEIGHT],
            'wither' => [self::WITHER_WIDTH, self::WITHER_HEIGHT],

            // Projectiles
            'arrow' => [self::ARROW_WIDTH, self::ARROW_HEIGHT],
            'fireball' => [self::FIREBALL_WIDTH, self::FIREBALL_HEIGHT],
            'small_fireball' => [self::SMALL_FIREBALL_WIDTH, self::SMALL_FIREBALL_HEIGHT],

            // Default to player dimensions
            default => [self::PLAYER_WIDTH, self::PLAYER_HEIGHT],
        };
    }

    /**
     * Get entity width by type name.
     */
    public static function getWidth(string $entityType): float {
        return self::getDimensions($entityType)[0];
    }

    /**
     * Get entity height by type name.
     */
    public static function getHeight(string $entityType): float {
        return self::getDimensions($entityType)[1];
    }
}
