<?php

declare(strict_types=1);

namespace bajan\Envoys\utils;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;

class RewardManager {

    private static ?RewardManager $instance = null;
    private Config $rewardsConfig;

    private function __construct(string $dataFolder) {
        $this->rewardsConfig = new Config($dataFolder . "rewards.yml", Config::YAML);
    }

    public static function initialize(string $dataFolder): void {
        if (self::$instance === null) {
            self::$instance = new RewardManager($dataFolder);
        }
    }

    public static function getInstance(): RewardManager {
        if (self::$instance === null) {
            throw new \Exception("RewardManager not initialized");
        }
        return self::$instance;
    }

    public static function giveRandomReward(Player $player): void {
        $instance = self::getInstance();
        $rewards = $instance->rewardsConfig->getAll();

        if (empty($rewards)) {
            $player->sendMessage(TF::RED . "No rewards configured.");
            return;
        }

        $rewardKeys = array_keys($rewards);
        $chosenKey = $rewardKeys[array_rand($rewardKeys)];
        $rewardData = $rewards[$chosenKey];

        // Assuming rewards.yml defines type and amount
        // Example reward: diamonds: {type: "item", id: "minecraft:diamond", amount: 5}
        // or money: {type: "money", amount: 100}

        switch ($rewardData["type"] ?? "") {
            case "money":
                $amount = (int)($rewardData["amount"] ?? 0);
                // Add your money handling here
                $player->sendMessage(TF::GOLD . "You received $" . $amount . "!");
                // TODO: Integrate with your economy plugin here
                break;

            case "item":
                // For simplicity, skipping item parsing here, add if needed
                $player->sendMessage(TF::GOLD . "You received an item reward!");
                break;

            default:
                $player->sendMessage(TF::YELLOW . "You received a reward!");
                break;
        }
    }
}
