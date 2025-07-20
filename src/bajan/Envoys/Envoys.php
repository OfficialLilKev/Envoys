<?php

declare(strict_types=1);

namespace bajan\Envoys;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\utils\TextFormat;
use bajan\envoys\utils\EnvoyManager;
use bajan\envoys\utils\EnvoyFloatingText;
use bajan\envoys\utils\RewardManager;

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

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->scheduleEnvoySpawnTask();
        RewardManager::initialize($this->getDataFolder());

        $this->getServer()->broadcastMessage($this->getMessage("envoy-starting"));
    }

    private function scheduleEnvoySpawnTask(): void {
        $totalTime = $this->interval;
        $intervals = [
            3600, 1800, 900, 600, 300, 60, 30, 15, 10, 5, 4, 3, 2, 1
        ];

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () use (&$totalTime, $intervals): void {
            if (in_array($totalTime, $intervals, true)) {
                $this->getServer()->broadcastMessage($this->getMessage("envoy-starting"));
            }

            if ($totalTime <= 0) {
                $count = $this->envoyManager->spawnEnvoys();
                $this->getServer()->broadcastMessage($this->getMessage("envoy-spawned", ["count" => (string) $count]));
                $totalTime = $this->interval;
            }

            $totalTime--;
        }), 20);
    }

    public function getMessage(string $key, array $replacements = []): string {
        $msg = $this->messages[$key] ?? $key;
        foreach ($replacements as $search => $replace) {
            $msg = str_replace("%$search%", $replace, $msg);
        }
        return TextFormat::colorize($msg);
    }
}
