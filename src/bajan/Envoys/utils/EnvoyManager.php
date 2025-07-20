<?php

declare(strict_types=1);

namespace bajan\Envoys;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\scheduler\ClosureTask;

use bajan\Envoys\utils\RewardManager;
use bajan\Envoys\utils\EnvoyFloatingText;

class EnvoyManager {

    /** @var Envoys */
    private $plugin;

    /** @var Position[] */
    private array $availableSpots = [];

    /** @var Position[] */
    private array $activeEnvoys = [];

    public function __construct(Envoys $plugin) {
        $this->plugin = $plugin;
        $this->loadEnvoyLocations();
    }

    private function loadEnvoyLocations(): void {
        $cfg = $this->plugin->getConfig()->get("envoy-spawn-locations", []);
        foreach ($cfg as $name => $posData) {
            if (
                isset($posData["x"], $posData["y"], $posData["z"], $posData["world"])
            ) {
                $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($posData["world"]);
                if ($world === null) {
                    $this->plugin->getLogger()->warning("World '{$posData["world"]}' not loaded. Skipping envoy spot '$name'.");
                    continue;
                }
                $position = new Position((float)$posData["x"], (float)$posData["y"], (float)$posData["z"], $world);
                $this->availableSpots[] = $position;
            }
        }
    }

    public function spawnEnvoys(): void {
        $min = $this->plugin->getConfig()->get("min_envoy", 1);
        $max = $this->plugin->getConfig()->get("max_envoy", 5);
        $toSpawn = mt_rand($min, $max);

        $spawned = 0;
        $usedIndices = [];

        for ($i = 0; $i < $toSpawn && count($usedIndices) < count($this->availableSpots); $i++) {
            do {
                $index = array_rand($this->availableSpots);
            } while (in_array($index, $usedIndices));

            $usedIndices[] = $index;
            $position = $this->availableSpots[$index];
            $world = $position->getWorld();

            // Load the chunk if not already
            if (!$world->isChunkLoaded((int) $position->x >> 4, (int) $position->z >> 4)) {
                $world->loadChunk((int) $position->x >> 4, (int) $position->z >> 4);
            }

            // Place the envoy chest
            $world->setBlock($position, VanillaBlocks::CHEST());
            $this->activeEnvoys[] = $position;

            // Add floating text
            EnvoyFloatingText::addFloatingText($position, TF::GOLD . "📦 Tap Me!");

            $spawned++;
        }

        $this->plugin->getLogger()->info("📦 $spawned envoy crates have been deployed!");

        // Schedule despawn
        $timer = (int)$this->plugin->getConfig()->get("despawn-timer", 30);
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() {
            $this->despawnEnvoys();
        }), 20 * $timer);
    }

    public function handleTap(Player $player, Position $pos): void {
        foreach ($this->activeEnvoys as $index => $envoyPos) {
            if ($envoyPos->distanceSquared($pos) < 1) {
                RewardManager::giveRandomReward($player);

                $pos->getWorld()->setBlock($pos, VanillaBlocks::AIR());
                EnvoyFloatingText::removeFloatingText($pos);
                unset($this->activeEnvoys[$index]);
                return;
            }
        }
    }

    public function despawnEnvoys(): void {
        foreach ($this->activeEnvoys as $pos) {
            $pos->getWorld()->setBlock($pos, VanillaBlocks::AIR());
            EnvoyFloatingText::removeFloatingText($pos);
        }
        $this->activeEnvoys = [];
        $this->plugin->getLogger()->info("❌ All envoys have despawned.");
    }
}
