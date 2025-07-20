<?php

declare(strict_types=1);

namespace bajan\Envoys\utils;

use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\particle\HugeExplodeParticle;
use bajan\Envoys\particle\WindExplosionParticle;
use pocketmine\world\Position;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;

class EnvoyFloatingText
{
    private static array $floatingTexts = [];

    public static function create(Position $position, string $text, string $tag): void
    {
        if (isset(self::$floatingTexts[$tag])) {
            self::remove($tag);
        }

        $floatingText = new FloatingTextParticle($text);
        self::$floatingTexts[$tag] = [
            'position' => $position,
            'floatingText' => $floatingText
        ];
        $position->getWorld()->addParticle(
            new Vector3($position->x + 0.5, $position->y + 1, $position->z + 0.5),
            $floatingText,
            $position->getWorld()->getPlayers()
        );
    }

    public static function remove(string $tag): void
    {
        if (!isset(self::$floatingTexts[$tag])) {
            return;
        }

        $floatingTextData = self::$floatingTexts[$tag];
        $position = $floatingTextData['position'];
        $floatingText = $floatingTextData['floatingText'];
        $floatingText->setInvisible();
        $position->getWorld()->addParticle($position, $floatingText, $position->getWorld()->getPlayers());

        unset(self::$floatingTexts[$tag]);
    }

    public static function update(string $tag, string $text): void
    {
        if (!isset(self::$floatingTexts[$tag])) {
            return;
        }

        $floatingTextData = self::$floatingTexts[$tag];
        $position = $floatingTextData['position'];
        $floatingText = $floatingTextData['floatingText'];
        $floatingText->setText($text);
        $position->getWorld()->addParticle($position, $floatingText, $position->getWorld()->getPlayers());

        self::$floatingTexts[$tag]['floatingText'] = $floatingText;
    }

    public static function hugeExplodeParticle(Position $position): void
    {
        $particle = new HugeExplodeParticle();
        $position->getWorld()->addParticle(new Vector3($position->x + 0.5, $position->y + 1, $position->z + 0.5), $particle, $position->getWorld()->getPlayers());
    }

    public static function windParticle(Position $position): void
    {
        $particle = new WindExplosionParticle();
        $position->getWorld()->addParticle(new Vector3($position->x + 0.5, $position->y + 1, $position->z + 0.5), $particle, $position->getWorld()->getPlayers());
    }

    public static function saveToJson(string $filePath): void
    {
        $data = [];
        foreach (self::$floatingTexts as $tag => $info) {
            $data[$tag] = [
                'position' => [
                    'world' => $info['position']->getWorld()->getFolderName(),
                    'x' => $info['position']->x,
                    'y' => $info['position']->y,
                    'z' => $info['position']->z
                ],
                'text' => $info['floatingText']->getText()
            ];
        }
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function loadFromJson(string $filePath, $server): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $data = json_decode(file_get_contents($filePath), true);
        foreach ($data as $tag => $info) {
            $world = $server->getWorldManager()->getWorldByName($info['position']['world']);
            if ($world !== null) {
                $position = new Position($info['position']['x'], $info['position']['y'], $info['position']['z'], $world);
                self::create($position, $info['text'], $tag);
            }
        }
    }
}
