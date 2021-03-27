<?php

declare(strict_types=1);

namespace OguzhanUmutlu\TNTRun\provider;

use pocketmine\level\Level;
use pocketmine\utils\Config;
use OguzhanUmutlu\TNTRun\arena\Arena;
use OguzhanUmutlu\TNTRun\TNTRun;

class YamlDataProvider {
    private $plugin;
    public function __construct(TNTRun $plugin) {
        $this->plugin = $plugin;
        $this->init();
        $this->loadArenas();
    }

    public function init() {
        if(!is_dir($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }
        if(!is_dir($this->getDataFolder() . "saves")) {
            @mkdir($this->getDataFolder() . "saves");
        }
    }

    public function loadArenas() {
        foreach ($this->plugin->arenalar->getNested("arenas") as $arena) {
            $a = $this->plugin->arenas;
            array_push($a, new Arena($this->plugin, $arena));
            $this->plugin->arenas = $a;
        }
    }

    public function saveArenas() {
        foreach ($this->plugin->arenas as $fileName => $arena) {
            if($arena->level instanceof Level) {
                foreach ($arena->players as $player) {
                    $player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
                }
                $arena->mapReset->loadMap($arena->level->getFolderName(), true);
            }
        }
    }

    private function getDataFolder(): string {
        return $this->plugin->getDataFolder();
    }
}
