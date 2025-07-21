<?php

declare(strict_types=1);

namespace bajan\Envoys;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use bajan\Envoys\utils\EnvoyManager;
use bajan\Envoys\utils\EnvoyFloatingText;
use bajan\Envoys\utils\RewardManager;
use bajan\Envoys\listeners\EnvoyListener;

class Envoys extends PluginBase implements Listener {

    private int $interval = 300;
    private int $despawnTimer = 120;
    private int $minEnvoy = 1;
    private int $maxEnvoy = 10;
    private EnvoyManager $envoyManager;
    private array $messages = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->saveResource("rewards.yml");
        $this->saveResource("messages.yml");

        $this->messages = yaml_parse_file($this->getDataFolder() . "messages.yml");

        $this->interval = $this->getConfig()->get("envoy-spawn-interval");
        $this->despawnTimer = $this->getConfig()->get("despawn-timer");
        $this->minEnvoy = $this->getConfig()->get("min_envoy");
        $this->maxEnvoy = $this->getConfig()->get("max_envoy");
        $spawnLocations = $this->getConfig()->get("envoy-spawn-locations", []);
        $this->envoyManager = new EnvoyManager($this, $spawnLocations, $this->despawnTimer, $this->minEnvoy, $this->maxEnvoy);

        $this->getServer()->getPluginManager()->registerEvents(new EnvoyListener($this->envoyManager), $this);

        $this->scheduleEnvoySpawnTask();

        RewardManager::initialize($this->getDataFolder());

        $this->getServer()->broadcastMessage($this->getMessage("envoy-starting"));
    }

    private function scheduleEnvoySpawnTask(): void {
        $totalTime = $this->interval;
        $countdownTimes = [30, 10, 5, 4, 3, 2, 1]; // seconds at which to broadcast countdown

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () use (&$totalTime, $countdownTimes): void {
            if (in_array($totalTime, $countdownTimes, true)) {
                // Broadcast countdown message with time left
                $this->getServer()->broadcastMessage(str_replace("%time%", (string)$totalTime, $this->getMessage("envoy-countdown")));
            }

            if ($totalTime <= 0) {
                $count = $this->envoyManager->spawnEnvoys();
                $this->getServer()->broadcastMessage($this->getMessage("envoy-spawned", ["count" => (string)$count]));
                $totalTime = $this->interval; // Reset countdown
            }

            $totalTime--;
        }), 20); // Run every 20 ticks = 1 second
    }

    public function getMessage(string $key, array $replacements = []): string {
        $msg = $this->messages[$key] ?? $key;
        foreach ($replacements as $search => $replace) {
            $msg = str_replace("%$search%", $replace, $msg);
        }
        return TextFormat::colorize($msg);
    }
}
