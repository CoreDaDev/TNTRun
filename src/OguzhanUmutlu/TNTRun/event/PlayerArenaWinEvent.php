<?php

declare(strict_types=1);

namespace OguzhanUmutlu\TNTRun\event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;
use OguzhanUmutlu\TNTRun\arena\Arena;
use OguzhanUmutlu\TNTRun\TNTRun;

class PlayerArenaWinEvent extends PluginEvent {

    public static $handlerList = \null;

    protected $player;

    protected $arena;

    public function __construct(TNTRun $plugin, Player $player, Arena $arena) {
        $this->player = $player;
        $this->arena = $arena;
        parent::__construct($plugin);
    }

    public function getPlayer(): Player {
        return $this->player;
    }

    public function getArena(): Arena {
        return $this->arena;
    }
}
