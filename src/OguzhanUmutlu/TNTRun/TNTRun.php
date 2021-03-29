<?php

declare(strict_types=1);

namespace OguzhanUmutlu\TNTRun;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;
use OguzhanUmutlu\TNTRun\arena\Arena;
use OguzhanUmutlu\TNTRun\arena\MapReset;
use OguzhanUmutlu\TNTRun\commands\TNTRunCommand;
use OguzhanUmutlu\TNTRun\math\Vector3;
use OguzhanUmutlu\TNTRun\provider\YamlDataProvider;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\{Server,Player};
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\FormAPI;

class TNTRun extends PluginBase implements Listener {
    public $dataProvider;
    public $commands = [];
    public $arenas = [];
    public $setters = [];
    public $setupData = [];
    public $config;
    public $arenalar;
    public $messages;

    public function onEnable() {
        $this->saveDefaultConfig();
        $this->saveResource("messages.yml");
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, array());
        $this->messages = new Config($this->getDataFolder()."messages.yml", Config::YAML, array());
        $this->arenalar = new Config($this->getDataFolder()."arenas.yml", Config::YAML, ["arenas" => []]);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->dataProvider = new YamlDataProvider($this);
        $this->getServer()->getCommandMap()->register("TNTRun", $this->commands[] = new TNTRunCommand($this));
        if(!class_exists(FormAPI::class)) {
            $this->getLogger()->warning("FormAPI is not installed, plugin disabling.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }
    public function onDisable() {
        $this->dataProvider->saveArenas();
    }
    public function onCommand(CommandSender $player, Command $command, string $label, array $args): bool {
        if($command->getName() == "tntrunleave") {
              $bulun = null;
              foreach($this->arenas as $x) {
                  foreach($x->players as $xx) {
                      if($xx->getName() == $player->getName()) {
                          $bulun = $x;
                      }
                  }
              }
              if(!$bulun) {
                  $player->sendMessage($this->messages->getNested("notInGame"));
                  return true;
              }
              $bulun->disconnectPlayer($player,"",false);
              $player->sendMessage($this->messages->getNested("leftSuccess"));
              $player->setHealth(20);
              $player->setFood(20);
              $player->removeAllEffects();
              $player->teleport($this->getServer()->getDefaultLevel()->getSpawnLocation());
        } else if($command->getName() == "tntrunjoin") {
              if(count($args) != 1) {
                  $player->sendMessage($this->messages->getNested("tntRunJoinUsage"));
                  return true;
              }
              $secili = null;
              foreach($this->arenas as $arena) {
                  if($arena->name == $args[0]) {
                      $secili = $arena;
                  }
              }
              if(!$secili) {
                  $player->sendMessage($this->messages->getNested("mapNotFound"));
                  return true;
              }
              $secili->joinToArena($player);
        } else if($command->getName() == "tntrunmenu") {
          $form = new SimpleForm(function (Player $player, int $data = null) {
            $result = $data;
            if($result == null) {
              return true;
            }
            if($result == 0) {
              $player->sendMessage($this->messages->getNested("closedMenu"));
              return true;
            }
            if($result > 0 && isset($this->arenas[$result-1])) {
              $secili = $this->arenas[$result-1];
              $secili->joinToArena($player);
            } else {
                $player->sendMessage($this->messages->getNested("mapNotFound"));
            }
            return true;
          });
          $form->setTitle($this->messages->getNested("title"));
          $form->setContent($this->messages->getNested("content"));
          $form->addButton($this->messages->getNested("exit"));
          foreach($this->arenas as $arena) {
            $form->addButton(str_replace(["{arena}", "{isFullColor}", "{countPlayers}", "{slots}"], [$arena->data["name"], (count($arena->players) >= $arena->data["slots"] ? "§c" : "§a"), count($arena->players), $arena->data["slots"]], $this->messages->getNested("arenaFormat")));
          }
          $form->sendToPlayer($player);
        }
        return true;
    }
    public function onChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $message = $event->getMessage();
        $arena = null;
        if(isset($this->setters[$player->getName()])) {
            $arena = $this->setters[$player->getName()];
        }
        $args = explode(" ", $event->getMessage());
        if(!$arena) {
          return;
        }
        $event->setCancelled(true);
        switch ($args[0]) {
            case "help":
                $player->sendMessage("§a> TNTRun Setup Help Menu:\n".
                "§7help : You are here\n" .
                "§7slots : Sets max players of arena\n".
                "§7world : Sets arena's world\n".
                "§7spawnpoint : Sets spawn of arena\n".
                "§7joinsign : Sets join sign of arena\n".
                "§7save : Saves arena\n".
                "§7enable : Enables arena");
                break;
            case "slots":
                if(!isset($args[1])) {
                    $player->sendMessage("§c> Usage: §7slots <int: slots>");
                    break;
                }
                if(!is_numeric($args[1])) {
                    $player->sendMessage("§c> You need to enter an integer!");
                    break;
                }
                $arena->data["slots"] = (int)$args[1];
                $player->sendMessage("§a> Slots set to $args[1]!");
                break;
            case "world":
                if(!isset($args[1])) {
                    $player->sendMessage("§cUsage: §7world <worldName>");
                    break;
                }
                if(!$this->getServer()->isLevelGenerated($args[1])) {
                    $player->sendMessage("§c> $args[1] named world not found!");
                    break;
                }
                $player->sendMessage("§a> Arena's world set to $args[1]!");
                $arena->data["level"] = $args[1];
                break;
            case "spawnpoint":
                $arena->data["spawn"] = (new Vector3($player->getX(), $player->getY(), $player->getZ()))->__toString();
                $player->sendMessage("§a> Spawn point set to:\n§a> X: " . (string)round($player->getX()) . " Y: " . (string)round($player->getY()) . " Z: " . (string)round($player->getZ()));
                break;
            case "joinsign":
                $player->sendMessage("§a> Break the join sign!");
                $this->setupData[$player->getName()] = 0;
                break;
            case "save":
                if(!$arena->level instanceof Level) {
                    $levelName = $arena->data["level"];
                    if(!is_string($levelName) || !$this->getServer()->isLevelGenerated($levelName)) {
                        errorMessage:
                        $player->sendMessage("§c> Save failed: world not found.");
                        if($arena->setup) {
                            $player->sendMessage("§6> Arena already enabled.");
                        }
                        return;
                    }
                    if(!$this->getServer()->isLevelLoaded($levelName)) {
                        $this->getServer()->loadLevel($levelName);
                    }

                    try {
                        if(!$arena->mapReset instanceof MapReset) {
                            goto errorMessage;
                        }
                        $arena->mapReset->saveMap($this->getServer()->getLevelByName($levelName));
                        $player->sendMessage("§a> World saved!");
                    }
                    catch (\Exception $exception) {
                        goto errorMessage;
                    }
                    break;
                } else {
                    $player->sendMessage("§c> World already saved.");
                }
                break;
            case "enable":
                if(!$arena->setup) {
                    $player->sendMessage("§6> Arena already enabled!");
                    break;
                }

                if(!$arena->enable(false)) {
                    $player->sendMessage("§c> Enabling arena failed, something is wrong!");
                    break;
                }

                if($this->getServer()->isLevelGenerated($arena->data["level"])) {
                    if(!$this->getServer()->isLevelLoaded($arena->data["level"]))
                        $this->getServer()->loadLevel($arena->data["level"]);
                    if(!$arena->mapReset instanceof MapReset)
                        $arena->mapReset = new MapReset($arena);
                    $arena->mapReset->saveMap($this->getServer()->getLevelByName($arena->data["level"]));
                }
                $drm = false;
                foreach($this->arenalar->getNested("arenas") as $x) {
                    if($x["name"] == $arena->data["name"]) {
                        $drm = true;
                    }
                }
                if($drm == true) {
                    $eski = [];
                    foreach($this->arenalar->getNested("arenas") as $x) {
                        if($x["name"] != $arena->name) {
                            array_push($eski, $x);
                        }
                    }
                    array_push($eski, $arena->data);
                    $this->arenalar->setNested("arenas", $eski);
                } else {
                    $eski = $this->arenalar->getNested("arenas");
                    array_push($eski, $arena->data);
                    $this->arenalar->setNested("arenas", $eski);
                }
                $this->arenalar->save();
                $this->arenalar->reload();
                $arena->loadArena(false);
                $player->sendMessage("§a> Arena enabled!");
                break;
            case "done":
                unset($this->setters[$player->getName()]);
                if(isset($this->setupData[$player->getName()])) {
                    unset($this->setupData[$player->getName()]);
                }
                $player->sendMessage("§a> You left from setup mode!");
                break;
            default:
                $player->sendMessage("§6> You are in setup mode.\n".
                    "§7- Use §lhelp §r§7to see commands\n"  .
                    "§7- or use §ldone §r§7to leave from setup mode");
                break;
        }
    }
    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if(isset($this->setupData[$player->getName()])) {
            switch ($this->setupData[$player->getName()]) {
                case 0:
                    $this->setters[$player->getName()]->data["joinsign"] = [(new Vector3($block->getX(), $block->getY(), $block->getZ()))->__toString(), $block->getLevel()->getFolderName()];
                    $player->sendMessage("§a> Join sign set!");
                    unset($this->setupData[$player->getName()]);
                    $event->setCancelled(\true);
                    break;
            }
        }
    }
}
