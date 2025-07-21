<?php

declare(strict_types=1);

namespace bajan\Envoys\listeners;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\Position;
use pocketmine\utils\TextFormat;
use bajan\Envoys\utils\EnvoyManager;

class EnvoyListener implements Listener {

    private EnvoyManager $envoyManager;

    public function __construct(EnvoyManager $envoyManager) {
        $this->envoyManager = $envoyManager;
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $block = $event->getBlock();
        $player = $event->getPlayer();
        $world = $player->getWorld();

        // Check if block is a chest
        if ($block->getTypeId() !== VanillaBlocks::CHEST()->getTypeId()) {
            return; // Not an envoy chest, ignore
        }

        $pos = new Position($block->getPosition()->getX(), $block->getPosition()->getY(), $block->getPosition()->getZ(), $world);

        // Check if this position is an active envoy
        if (!$this->envoyManager->isEnvoyPosition($world, $pos)) {
            return; // Not an envoy chest
        }

        // Cancel normal chest opening
        $event->cancel();

        // Claim the envoy: remove chest, give reward, remove floating text & particles
        $this->envoyManager->claimEnvoy($player, $world, $pos);
    }
}
