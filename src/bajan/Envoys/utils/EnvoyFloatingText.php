<?php

declare(strict_types=1);

namespace bajan\Envoys\utils;

use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;

class EnvoyFloatingText {

    /** @var array<string, Entity> */
    private static array $floatingTexts = [];

    public static function addFloatingText(Position $pos, string $text): void {
        $key = self::getKey($pos);

        if (isset(self::$floatingTexts[$key])) {
            self::removeFloatingText($pos);
        }

        // Assuming you have an implementation of floating text entity
        // This is pseudocode: Replace with your floating text entity creation logic
        $world = $pos->getWorld();
        $floatingText = new FloatingTextEntity($pos->add(0.5, 1.5, 0.5), $text); // example offset
        $world->addEntity($floatingText);

        self::$floatingTexts[$key] = $floatingText;
    }

    public static function removeFloatingText(Position $pos): void {
        $key = self::getKey($pos);

        if (isset(self::$floatingTexts[$key])) {
            $entity = self::$floatingTexts[$key];
            $entity->flagForDespawn();
            unset(self::$floatingTexts[$key]);
        }
    }

    private static function getKey(Position $pos): string {
        return $pos->getWorld()->getFolderName() . ":" . $pos->x . "," . $pos->y . "," . $pos->z;
    }
}
