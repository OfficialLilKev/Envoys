<?php

declare(strict_types=1);

namespace bajan\Envoys\utils;

use pocketmine\world\particle\LavaParticle;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use pocketmine\world\Position;
use pocketmine\utils\TextFormat;
use bajan\Envoys\utils\RewardManager;
use bajan\Envoys\Envoys;

class EnvoyManager {

public function spawnEnvoys(): int {
    $count = 0;
    $numberOfEnvoys = mt_rand($this->minEnvoy, $this->maxEnvoy);
    $maxRetries = 10;

    for ($i = 0; $i < $numberOfEnvoys; $i++) {
        foreach ($this->spawnLocations as $worldName => $bounds) {
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);

            if ($world === null) {
                continue;
            }

            $retries = 0;
            while ($retries < $maxRetries) {
                $x = mt_rand($bounds['x-min'], $bounds['x-max']);
                $y = mt_rand($bounds['y-min'], $bounds['y-max']);
                $z = mt_rand($bounds['z-min'], $bounds['z-max']);
                $position = new Position($x, $y, $z, $world);

                if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                    break;
                }

                if ($world->getBlockAt($x, $y, $z)->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
                    $this->spawnEnvoy($world, $position);
                    $count++;
                    break;
                } else {
                    $retries++;
                }
            }
        }
    }

    return $count;
}
    
    private Envoys $plugin;
    private array $spawnLocations;
    private int $despawnTimer;
    private int $minEnvoy;
    private int $maxEnvoy;
    private array $activeEnvoys = [];
    private string $dataFile;

    public function __construct(Envoys $plugin, array $spawnLocations, int $despawnTimer, int $minEnvoy, int $maxEnvoy) {
        $this->plugin = $plugin;
        $this->spawnLocations = $spawnLocations;
        $this->despawnTimer = $despawnTimer;
        $this->minEnvoy = $minEnvoy;
        $this->maxEnvoy = $maxEnvoy;
        $this->dataFile = $plugin->getDataFolder() . "envoy_data.json";
        $this->loadEnvoyData();
    }

    public function randomlySpawnEnvoys(): void {
        $numberOfEnvoys = mt_rand($this->minEnvoy, $this->maxEnvoy);
        $maxRetries = 10;

        for ($i = 0; $i < $numberOfEnvoys; $i++) {
            foreach ($this->spawnLocations as $worldName => $bounds) {
                $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);

                if ($world === null) {
                    continue;
                }

                $retries = 0;
                while ($retries < $maxRetries) {
                    $x = mt_rand($bounds['x-min'], $bounds['x-max']);
                    $y = mt_rand($bounds['y-min'], $bounds['y-max']);
                    $z = mt_rand($bounds['z-min'], $bounds['z-max']);
                    $position = new Position($x, $y, $z, $world);

                    if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                        $this->plugin->getLogger()->warning("Skipped envoy spawn at {$x}, {$y}, {$z} in {$worldName} because the chunk is not generated.");
                        break;
                    }

                    if ($world->getBlockAt($x, $y, $z)->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
                        $this->spawnEnvoy($world, $position);
                        break;
                    } else {
                        $retries++;
                    }
                }

                if ($retries >= $maxRetries) {
                    $this->plugin->getLogger()->warning("Failed to find an air block for envoy spawn after {$maxRetries} retries.");
                }
            }
        }
    }

    public function spawnEnvoy(World $world, Position $position): void {
        $world->setBlock($position, VanillaBlocks::CHEST());

        $tag = $world->getFolderName() . ":" . $position->x . "," . $position->y . "," . $position->z;
        $envoy = [
            'world' => $world->getFolderName(),
            'position' => $position,
            'tag' => $tag,
            'timeLeft' => $this->despawnTimer
        ];

        $this->activeEnvoys[$tag] = $envoy;
        $this->updateEnvoyFloatingText($position, $envoy['timeLeft'], $tag);
        $this->startLavaParticleTask($position, $tag);
        $this->plugin->getServer()->broadcastMessage(TextFormat::GREEN . "An envoy has spawned in " . $world->getFolderName() . " at " . $position->getFloorX() . ", " . $position->getFloorY() . ", " . $position->getFloorZ() . "!");

        $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () use ($envoy): void {
            foreach ($this->activeEnvoys as $key => &$activeEnvoy) {
                if ($activeEnvoy['tag'] === $envoy['tag']) {
                    if ($activeEnvoy['timeLeft'] > 0) {
                        $activeEnvoy['timeLeft']--;
                        $this->updateEnvoyFloatingText($activeEnvoy['position'], $activeEnvoy['timeLeft'], $activeEnvoy['tag']);
                    } else {
                        $this->removeEnvoy($activeEnvoy['position']->getWorld(), $activeEnvoy['position'], $activeEnvoy['tag']);
                        unset($this->activeEnvoys[$key]);
                    }
                    break;
                }
            }
        }), 20);
    }

     private function updateEnvoyFloatingText(Position $position, int $timeLeft, string $tag): void {
        $formattedTime = $this->formatTime($timeLeft);
    EnvoyFloatingText::create($position, "§l§bEnvoy\nTap me!\n\nDespawning in §e" . $formattedTime . "§f!", $tag);
    }

    private function formatTime(int $timeLeft): string {
        $minutes = intdiv($timeLeft, 60);
        $seconds = $timeLeft % 60;
        return ($minutes > 0 ? $minutes . "m" : "") . ($seconds > 0 ? $seconds . "s" : "");
    }

    public function isEnvoyPosition(World $world, Position $position): bool {
        foreach ($this->activeEnvoys as $envoy) {
            if ($envoy['world'] === $world->getFolderName() && $envoy['position']->equals($position)) {
                return true;
            }
        }
        return false;
    }

    public function removeEnvoy(World $world, Position $position, string $tag): void {
        $world->setBlock($position, VanillaBlocks::AIR());

        EnvoyFloatingText::remove($tag);
        $this->stopLavaParticleTask($tag);

        foreach ($this->activeEnvoys as $key => $envoy) {
            if ($envoy['world'] === $world->getFolderName() && $envoy['position']->equals($position)) {
                unset($this->activeEnvoys[$key]);
                break;
            }
        }
    }

public function claimEnvoy(Player $player, World $world, Position $position): void {
    foreach ($this->activeEnvoys as $key => $envoy) {
        if ($envoy['world'] === $world->getFolderName() && $envoy['position']->equals($position)) {
            $this->removeEnvoy($world, $position, $envoy['tag']);
            unset($this->activeEnvoys[$key]);
            break;
        }
    }
    $player->sendMessage(TextFormat::GOLD . "You have claimed the envoy at " . $position->getFloorX() . ", " . $position->getFloorY() . ", " . $position->getFloorZ() . "!");
    EnvoyFloatingText::windParticle($position);
    RewardManager::getInstance()->giveReward($player);
}


    public function getActiveEnvoys(): array {
        return $this->activeEnvoys;
    }

    public function saveEnvoyData(): void {
        EnvoyFloatingText::saveToJson($this->dataFile);
    }

    private function loadEnvoyData(): void {
        EnvoyFloatingText::loadFromJson($this->dataFile, $this->plugin->getServer());
    }

    private function startLavaParticleTask(Position $position, string $tag): void {
        $task = new ClosureTask(function () use ($position, $tag): void {
            $world = $position->getWorld();
            if (isset($this->activeEnvoys[$tag])) {
                $particle = new LavaParticle();
                $world->addParticle(new Vector3($position->x + 0.5, $position->y + 1, $position->z + 0.5), $particle, $world->getPlayers());
            }
        });

        $this->lavaParticleTasks[$tag] = $this->plugin->getScheduler()->scheduleRepeatingTask($task, 20);
    }

    private function stopLavaParticleTask(string $tag): void {
        if (isset($this->lavaParticleTasks[$tag])) {
            $this->lavaParticleTasks[$tag]->cancel();
            unset($this->lavaParticleTasks[$tag]);
        }
    }
}
