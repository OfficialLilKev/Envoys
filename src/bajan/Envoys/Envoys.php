<?php

namespace bajan\envoys;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Chest;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat as TF;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\tile\Tile;
use pocketmine\tile\Chest as TileChest;
use pocketmine\inventory\ChestInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\block\Block;

class Envoys extends PluginBase implements Listener {

    /** @var array */
    private $activeCrates = [];

    /** @var RewardManager */
    private $rewardManager;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->rewardManager = new RewardManager($this);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            $this->spawnEnvoys();
        }), $this->getConfig()->get("envoy-spawn-interval", 60) * 20);
    }

    public function spawnEnvoys(): void {
        $cfg = $this->getConfig();

        $locations = $cfg->get("envoy-spawn-locations", []);
        $min = $cfg->get("min_envoy", 1);
        $max = $cfg->get("max_envoy", 3);

        $amount = mt_rand($min, $max);

        $count = 0;
        foreach ($locations as $name => $loc) {
            if ($count >= $amount) break;

            $x = mt_rand($loc["x-min"], $loc["x-max"]);
            $y = mt_rand($loc["y-min"], $loc["y-max"]);
            $z = mt_rand($loc["z-min"], $loc["z-max"]);
            $level = $this->getServer()->getDefaultLevel();
            $pos = new Position($x, $y, $z, $level);

            $level->loadChunk($x >> 4, $z >> 4); // ensure chunk is loaded

            $level->setBlock($pos, Block::get(Block::CHEST));
            $nbt = Tile::createNBT($pos)->setTag(new StringTag("CustomName", "📦 Tap Me!"));
            $tile = Tile::createTile(Tile::CHEST, $level, $nbt);
            if ($tile instanceof TileChest) {
                $tile->setName("Envoy Crate");
            }

            $this->activeCrates[] = $pos;
            $count++;
        }

        $this->getServer()->broadcastMessage(TF::GOLD . "📦 $count envoy crates have been deployed!");
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        foreach ($this->activeCrates as $i => $pos) {
            if ($block->x === $pos->getX() && $block->y === $pos->getY() && $block->z === $pos->getZ()) {
                $event->setCancelled(true); // cancel chest opening
                unset($this->activeCrates[$i]);
                $block->getLevel()->setBlock($pos, Block::get(Block::AIR));
                $this->rewardManager->giveReward($player);
                $player->sendMessage(TF::GREEN . "🎁 You opened an envoy crate!");
                break;
            }
        }
    }

    public function getRewardManager(): RewardManager {
        return $this->rewardManager;
    }
}
