<?php

declare(strict_types=1);

namespace OguzhanUmutlu\TNTRun\arena;

use pocketmine\block\Block;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\entity\Attribute;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\Player;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use OguzhanUmutlu\TNTRun\event\PlayerArenaWinEvent;
use pocketmine\math\Vector3;
use OguzhanUmutlu\TNTRun\TNTRun;
use pocketmine\event\entity\EntityDamageEvent;

class Arena implements Listener {

    const MSG_MESSAGE = 0;
    const MSG_TIP = 1;
    const MSG_POPUP = 2;
    const MSG_TITLE = 3;

    const PHASE_LOBBY = 0;
    const PHASE_GAME = 1;
    const PHASE_RESTART = 2;
    public $plugin;
    public $scheduler;
    public $mapReset;
    public $phase = 0;
    public $data = [];
    public $setup = false;
    public $players = [];
    public $toRespawn = [];
    public $level = null;
    public function lazyLang(string $str) {
        return $this->plugin->messages->getNested($str);
    }
    public function __construct(TNTRun $plugin, array $arenaFileData) {
        $this->plugin = $plugin;
        $this->data = $arenaFileData;
        $this->setup = !$this->enable(\false);
        $this->name = $arenaFileData["name"];
        $this->boostcu = [];
        $this->bloklar = [];
        $this->plugin->getScheduler()->scheduleRepeatingTask($this->scheduler = new ArenaScheduler($this), 20);
        #$this->plugin->getScheduler()->scheduleRepeatingTask($this->schedulerA = new TNTTask($this), 3);
        if($this->setup) {
            if(empty($this->data)) {
                $this->createBasicData();
            }
        }
        else {
            $this->loadArena();
        }
    }
    public function joinToArena(Player $player) {
        if(!$this->data["enabled"]) {
            $player->sendMessage($this->lazyLang("arenaInSetup"));
            return;
        }
        if(count($this->players) >= $this->data["slots"]) {
            $player->sendMessage($this->lazyLang("arenaIsFull"));
            return;
        }
        if($this->inGame($player)) {
            $player->sendMessage($this->lazyLang("alreadyInGame"));
            return;
        }
        $selected = false;
        for($lS = 1; $lS <= $this->data["slots"]; $lS++) {
            if(!$selected) {
              $gitt = Vector3::fromString($this->data["spawn"]);
              $dunya = $this->level;
              $player->teleport(new Position($gitt->getX(),$gitt->getY()+1,$gitt->getZ(), $this->level));
              $a = $this->players;
              array_push($a,$player);
              $this->players = $a;
              $selected = true;
            }
        }

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();

        $player->setGamemode($player::ADVENTURE);
        $player->setHealth(20);
        $player->setFood(20);

        $this->broadcastMessage(str_replace(["{player}", "{isFullColor}", "{countPlayers}", "{slots}"], [$player->getName(), (count($this->players) >= $this->data["slots"] ? "§c" : "§a"), count($this->players), $this->data["slots"]], $this->lazyLang("joinMessage")));
    }
    public function disconnectPlayer(Player $player, string $quitMsg = "", bool $death = false) {
        $players = $this->players;
        switch ($this->phase) {
            case Arena::PHASE_LOBBY:
                $index = -1;
                foreach ($players as $i => $p) {
                    if ($p->getId() == $player->getId()) {
                        $index = $i;
                    }
                }
                if ($index != -1) {
                    unset($players[$index]);
                }
                break;
            default:
                unset($players[$player->getName()]);
                break;
        }
        $this->players = $players;

        $player->removeAllEffects();

        $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());

        $player->setHealth(20);
        $player->setFood(20);

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();



        if(!$death) {
            $this->broadcastMessage(str_replace(["{player}", "{isFullColor}", "{countPlayers}", "{slots}"], [$player->getName(), (count($this->players) >= $this->data["slots"] ? "§c" : "§a"), count($this->players), $this->data["slots"]], $this->lazyLang("leftMessage")));
        }

        if($quitMsg != "") {
            $player->sendMessage("§e> $quitMsg");
        }
    }

    public function startGame() {
        $players = [];
        foreach ($this->players as $player) {
            $players[$player->getName()] = $player;
            $player->setGamemode(2);
            $player->getInventory()->clearAll();
            $it = new Item(288);
            $it->setCustomName($this->lazyLang("boostItemName"));
            $it->setLore([$this->lazyLang("boostItemLore")]);
            $player->getInventory()->addItem($it);
        }
        foreach($this->players as $p) {
            $p->sendMessage($this->lazyLang("gameStarted"));
        }
        $this->players = $players;
        $this->phase = 1;
    }

    public function startRestart() {
        $player = null;
        foreach ($this->players as $p) {
            $player = $p;
        }
        $this->phase = self::PHASE_RESTART;
        if($player === null || (!$player instanceof Player) || (!$player->isOnline())) {
            return;
        }
        $player->addTitle($this->lazyLang("youWonTitle"));
        if($this->plugin->config->getNested("winAction.enabled")) {
            $this->plugin->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace(["{player}", "{map}"], [$player->getName(), $this->data["name"]], $this->plugin->config->getNested("winAction.command")));
        }
        $this->plugin->getServer()->getPluginManager()->callEvent(new PlayerArenaWinEvent($this->plugin, $player, $this));
    }
    public function inGame(Player $player): bool {
        switch ($this->phase) {
            case self::PHASE_LOBBY:
                $inGame = false;
                foreach ($this->players as $players) {
                    if($players->getId() == $player->getId()) {
                        $inGame = true;
                    }
                }
                return $inGame;
            default:
                return isset($this->players[$player->getName()]);
        }
    }
    public function broadcastMessage(string $message, int $id = 0, string $subMessage = "") {
        foreach ($this->players as $player) {
            switch ($id) {
                case self::MSG_MESSAGE:
                    $player->sendMessage($message);
                    break;
                case self::MSG_TIP:
                    $player->sendTip($message);
                    break;
                case self::MSG_POPUP:
                    $player->sendPopup($message);
                    break;
                case self::MSG_TITLE:
                    $player->addTitle($message, $subMessage);
                    break;
            }
        }
    }
    public function checkEnd(): bool {
        return count($this->players) <= 1;
    }
    public function onDamage(EntityDamageEvent $e) {
      $entity = $e->getEntity();
      if($entity instanceof Player) {
        if($this->inGame($entity)) {
          $e->setCancelled(true);
        }
      }
    }
    public function onBreak(BlockBreakEvent $e) {
      $player = $e->getPlayer();
      if($this->inGame($player)) {
        $e->setCancelled(true);
      }
    }
    public function onMove(PlayerMoveEvent $event) {
      $event->getPlayer()->getPosition()->getLevel()->setTime(6000);
      $event->getPlayer()->getPosition()->getLevel()->stopTime();
      $player = $event->getPlayer();
      if($this->inGame($player) && $this->phase == self::PHASE_GAME) {
          $this->blokSil($player->asVector3());
          $this->blokSil($player->asVector3()->add(0, -1));

          # Thanks for ethaniccc for helping this code
          $AABB = $player->getBoundingBox()->expandedCopy(0, 1, 0);
          $AABB->maxY = $player->getY();
          $blocks = $player->getLevel()->getCollisionBlocks($AABB);
          # Thanks for ethaniccc for helping this code

          foreach($blocks as $block){
              $this->blokSil($block->asVector3());
          }
          if($player->getPosition()->getY() < 1) {
              $player->setHealth(20);
              $player->setFood(20);
              $player->removeAllEffects();
              $this->disconnectPlayer($player, "", true);
              $this->broadcastMessage(str_replace(["{player}", "{countPlayers}", "{slots}"], [$player->getName(), count($this->players), $this->data["slots"]], $this->lazyLang("deathMessage")));
          }
      }
        if($this->phase != self::PHASE_LOBBY) return;
        $player = $event->getPlayer();

        if($this->inGame($player)) {
            $index = null;
            foreach ($this->players as $i => $p) {
                if($p->getId() == $player->getId()) {
                    $index = $i;
                }
            }


        }
    }



    public function blokSil(Vector3 $konum) {
        $this->plugin->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(
            function(int $currentTick) use ($konum): void{
                $this->level->setBlock($konum, new Block(0));
            }
        ), 7);
    }
    public function onExhaust(PlayerExhaustEvent $event) {
        $player = $event->getPlayer();

        if(!$player instanceof Player) return;

        if($this->inGame($player) && $this->phase == self::PHASE_LOBBY) {
            $event->setCancelled(true);
        }
    }
    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if($this->inGame($player) && $player->getInventory()->getItemInHand() && $player->getInventory()->getItemInHand()->getLore() && $player->getInventory()->getItemInHand()->getLore()[0] == "§r§eYou can use me to boost yourself.") {
            $player->setMotion(new Vector3(0, floatval($this->plugin->config->getNested("boostItem.boost")), 0));
            if(!isset($this->boostcu[$player->getName()])) {
            $this->boostcu[$player->getName()] = 1;
          } else {
            $this->boostcu[$player->getName()] = $this->boostcu[$player->getName()]+1;
          }
          if($this->boostcu[$player->getName()] > intval($this->plugin->config->getNested("boostItem.max"))-1) {
            $it = new Item(288);
            $it->setCustomName($this->lazyLang("boostItemName"));
            $it->setLore([$this->lazyLang("boostItemLore")]);
            $player->getInventory()->removeItem($it);
            $player->sendMessage($this->lazyLang("boostBroken"));
          } else {
            $player->sendMessage(str_replace("{boostLeft}", (intval($this->plugin->config->getNested("boostItem.max"))-$this->boostcu[$player->getName()]), $this->lazyLang("boostLeft")));
          }
        }
        if($this->inGame($player) && $event->getBlock()->getId() == Block::CHEST && $this->phase == self::PHASE_LOBBY) {
            $event->setCancelled(true);
            return;
        }

        if(!$block->getLevel()->getTile($block) instanceof Tile) {
            return;
        }

        $signPos = Position::fromObject(Vector3::fromString($this->data["joinsign"][0]), $this->plugin->getServer()->getLevelByName($this->data["joinsign"][1]));

        if((!$signPos->equals($block)) || $signPos->getLevel()->getId() != $block->getLevel()->getId()) {
            return;
        }
        if($this->phase == self::PHASE_GAME) {
            $player->sendMessage($this->lazyLang("gameAlreadyStarted"));
            return;
        }
        if($this->phase == self::PHASE_RESTART) {
            $player->sendMessage($this->lazyLang("arenaRestarting"));
            return;
        }

        if($this->setup) {
            return;
        }
        $this->joinToArena($player);
    }
    public function onDrop(PlayerDropItemEvent $e) {
      $player = $e->getPlayer();
      if($this->inGame($player)) {
        $e->setCancelled();
      }
    }
    public function onDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();
        if(!$this->inGame($player)) return;
        $this->toRespawn[$player->getName()] = $player;
        $this->disconnectPlayer($player, "", true);
        # "§a> {} died. §7[".count($this->players)."/{$this->data["slots"]}]"
        $this->broadcastMessage(str_replace(["{player}", "{countPlayers}", "{slots}"], [$player->getName(), count($this->players), $this->data["slots"]], $this->lazyLang("deathMessage")));
        $event->setDeathMessage("");
        $player->getInventory()->dropContents($this->level,new Vector3($player->getPosition()->getX(),$player->getPosition()->getY(),$player->getPosition()->getZ()));

    }
    public function onRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        if(!$this->inGame($player)) {
          $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
        }
    }
    public function onQuit(PlayerQuitEvent $event) {
        if($this->inGame($event->getPlayer())) {
            $this->disconnectPlayer($event->getPlayer());
        }
    }
    public function onLevelChange(EntityLevelChangeEvent $event) {
        $player = $event->getEntity();
        if(!$player instanceof Player) return;
        if($this->inGame($player)) {
            $this->disconnectPlayer($player, $this->lazyLang("leftSuccess"));
        }
    }
    public function loadArena(bool $restart = false) {
        if(!$this->data["enabled"]) {
            $this->plugin->getLogger()->error("Enabling arena failed: Arena closed!");
            return;
        }
        if(!$this->mapReset instanceof MapReset) {
            $this->mapReset = new MapReset($this);
        }
        if(!$restart) {
            $this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
        } else {
            $this->scheduler->reloadTimer();
            $this->level = $this->mapReset->loadMap($this->data["level"]);
        }
        if(!$this->level instanceof Level) {
            $level = $this->mapReset->loadMap($this->data["level"]);
            if(!$level instanceof Level) {
                $this->plugin->getLogger()->error("Arena world not found. (".$this->data["name"].")");
                $this->setup = true;
                return;
            }
            $this->level = $level;
        }
        $this->phase = static::PHASE_LOBBY;
        $this->players = [];
    }
    public function enable(bool $loadArena = true): bool {
        if(empty($this->data)) {
            return false;
        }
        if($this->data["level"] == null) {
            return false;
        }
        if(!$this->plugin->getServer()->isLevelGenerated($this->data["level"])) {
            return false;
        }
        if(!is_int($this->data["slots"])) {
            return false;
        }
        if(!$this->data["spawn"]) {
            return false;
        }
        if(!is_array($this->data["joinsign"])) {
            return false;
        }
        if(count($this->data["joinsign"]) !== 2) {
            return false;
        }
        $this->data["enabled"] = true;
        $this->setup = false;
        if($loadArena) $this->loadArena();
        return true;
    }
    private function createBasicData() {
        $this->data = [
            "level" => null,
            "slots" => 12,
            "spawn" => "",
            "enabled" => false,
            "joinsign" => []
        ];
    }
    public function __destruct() {
        unset($this->scheduler);
        unset($this->schedulerA);
    }
}
