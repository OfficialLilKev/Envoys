<?php

declare(strict_types=1);

namespace bajan\Envoys\utils;

use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\utils\SingletonTrait;
use bajan\Envoys\Envoys;

class RewardManager
{
    use SingletonTrait;

    private array $rewards;
    private static string $dataFolder = '';

    public function __construct()
    {
        if (self::$dataFolder === '') {
            throw new \RuntimeException('Data folder has not been set.');
        }
        $config = new Config(self::$dataFolder . "rewards.yml", Config::YAML);
        $this->rewards = $config->get("rewards", []);
    }

    public static function initialize(string $dataFolder): void
    {
        self::$dataFolder = $dataFolder;
        self::setInstance(new self());
    }

    public function giveReward(Player $player): void
    {
        $rewardData = $this->rewards[array_rand($this->rewards)];

        $item = StringToItemParser::getInstance()->parse($rewardData['id']);
        if ($item === null) {
            return;
        }

        $item->setCount($rewardData['amount'] ?? 1);

        if (isset($rewardData['custom_name'])) {
            $item->setCustomName(TextFormat::colorize($rewardData['custom_name']));
        }

        if (isset($rewardData['lore'])) {
            $lore = array_map(fn($line) => TextFormat::colorize($line), $rewardData['lore']);
            $item->setLore($lore);
        }

        if (isset($rewardData['enchantments'])) {
            foreach ($rewardData['enchantments'] as $enchantData) {
                [$enchantName, $level] = explode(":", $enchantData);
                $enchantment = StringToEnchantmentParser::getInstance()->parse($enchantName);
                if ($enchantment !== null) {
                    $item->addEnchantment(new EnchantmentInstance($enchantment, (int) $level));
                }
            }
        }

        $player->getInventory()->addItem($item);
        $player->sendMessage(TextFormat::GOLD . "You have received a reward!");
    }
}
