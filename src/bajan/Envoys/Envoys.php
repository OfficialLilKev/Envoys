<?php

declare(strict_types=1);

namespace bajan\Envoys;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;

class Envoys extends PluginBase implements Listener {

    private EnvoyManager $envoyManager;
    private int $interval;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->saveResource("rewards.yml");
        $this->saveResource("messages.yml");

        $this->interval = $this->getConfig()->get("envoy-spawn-interval", 300);

        $this->envoyManager = new EnvoyManager($this);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->scheduleEnvoySpawnTask();

        $this->getLogger()->info(TextFormat::GREEN . "Envoys plugin enabled!");
    }

    private function scheduleEnvoySpawnTask(): void {
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->envoyManager->spawnEnvoys();
        }), 20 * $this->interval);
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        // Only interact with chest blocks
        if ($block->getTypeId() === VanillaBlocks::CHEST()->getTypeId()) {
            $pos = $block->getPosition();

            $this->envoyManager->handleTap($player, $pos);
        }
    }
}
