<?php

declare(strict_types=1);

namespace bajan\Envoys\utils;

use bajan\Envoys\Envoys;
use bajan\Envoys\utils\RewardManager;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\Position;
use pocketmine\world\World;

class EnvoyManager {

    private Envoys $plugin;
    private array $spawnLocations;
    private int $despawnTimer;
    private int $minEnvoy;
    private int $maxEnvoy;
    private string $dataFile;

    private array $activeEnvoys = [];
    private array $lavaParticleTasks = [];

    public function __construct(Envoys $plugin, array $spawnLocations, int $despawnTimer, int $minEnvoy, int $maxEnvoy) {
        $this->plugin = $plugin;
        $this->spawnLocations = $spawnLocations;
        $this->despawnTimer = $despawnTimer;
        $this->minEnvoy = $minEnvoy;
        $this->maxEnvoy = $maxEnvoy;
        $this->dataFile = $plugin->getDataFolder() . "envoy_data.json";
        $this->loadEnvoyData();
    }

    public function spawnEnvoys(): int {
        $spawned = 0;
        $total = mt_rand($this->minEnvoy, $this->maxEnvoy);
        $maxRetries = 10;

        for ($i = 0; $i < $total; $i++) {
            foreach ($this->spawnLocations as $worldName => $bounds) {
                $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
                if ($world === null) continue;

                $retries = 0;
                while ($retries < $maxRetries) {
                    $x = mt_rand($bounds["x-min"], $bounds["x-max"]);
                    $y = mt_rand($bounds["y-min"], $bounds["y-max"]);
                    $z = mt_rand($bounds["z-min"], $bounds["z-max"]);
                    $position = new Position($x, $y, $z, $world);

                    if (!$world->isChunkLoaded($x >> 4, $z >> 4)) break;

                    if ($world->getBlockAt($x, $y, $z)->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
                        $this->spawnEnvoy($world, $position);
                        $spawned++;
                        break;
                    } else {
                        $retries++;
                    }
                }
            }
        }

        return $spawned;
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

        $this->plugin->getServer()->broadcastMessage(TextFormat::GREEN .
            "📦 An envoy has spawned at §e" . $position->getFloorX() . ", " . $position->getFloorY() . ", " . $position->getFloorZ() .
            " §ain world §b" . $world->getFolderName());

        $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () use ($envoy): void {
            if (!isset($this->activeEnvoys[$envoy['tag']])) return;

            $this->activeEnvoys[$envoy['tag']]['timeLeft']--;

            if ($this->activeEnvoys[$envoy['tag']]['timeLeft'] <= 0) {
                $this->removeEnvoy($envoy['position']->getWorld(), $envoy['position'], $envoy['tag']);
            } else {
                $this->updateEnvoyFloatingText(
                    $this->activeEnvoys[$envoy['tag']]['position'],
                    $this->activeEnvoys[$envoy['tag']]['timeLeft'],
                    $envoy['tag']
                );
            }
        }), 20);
    }

    public function claimEnvoy(Player $player, World $world, Position $position): void {
        foreach ($this->activeEnvoys as $key => $envoy) {
            if ($envoy['world'] === $world->getFolderName() && $envoy['position']->equals($position)) {
                $this->removeEnvoy($world, $position, $envoy['tag']);
                unset($this->activeEnvoys[$key]);

                $player->sendMessage(TextFormat::GOLD . "🎁 You claimed an envoy at §e{$position->getFloorX()}, {$position->getFloorY()}, {$position->getFloorZ()}§6!");
                EnvoyFloatingText::windParticle($position);
                RewardManager::getInstance()->giveReward($player);
                break;
            }
        }
    }

    public function removeEnvoy(World $world, Position $position, string $tag): void {
        $world->setBlock($position, VanillaBlocks::AIR());
        EnvoyFloatingText::remove($tag);
        $this->stopLavaParticleTask($tag);
        unset($this->activeEnvoys[$tag]);
    }

    private function updateEnvoyFloatingText(Position $position, int $timeLeft, string $tag): void {
        $formatted = $this->formatTime($timeLeft);
        EnvoyFloatingText::create($position, "§l§bEnvoy\n§fTap me!\n\nDespawning in §e{$formatted}§f!", $tag);
    }

    private function formatTime(int $seconds): string {
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        return ($m > 0 ? "{$m}m " : "") . "{$s}s";
    }

    private function startLavaParticleTask(Position $position, string $tag): void {
        $this->lavaParticleTasks[$tag] = $this->plugin->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function () use ($position, $tag): void {
                if (isset($this->activeEnvoys[$tag])) {
                    $position->getWorld()->addParticle(
                        new Vector3($position->x + 0.5, $position->y + 1, $position->z + 0.5),
                        new LavaParticle()
                    );
                }
            }),
            20
        );
    }

    private function stopLavaParticleTask(string $tag): void {
        if (isset($this->lavaParticleTasks[$tag])) {
            $this->lavaParticleTasks[$tag]->cancel();
            unset($this->lavaParticleTasks[$tag]);
        }
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

    public function isEnvoyPosition(World $world, Position $position): bool {
        foreach ($this->activeEnvoys as $envoy) {
            if ($envoy['world'] === $world->getFolderName() && $envoy['position']->equals($position)) {
                return true;
            }
        }
        return false;
    }
}
