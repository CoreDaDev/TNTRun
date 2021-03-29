<?php

declare(strict_types=1);

namespace OguzhanUmutlu\TNTRun\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use OguzhanUmutlu\TNTRun\arena\Arena;
use OguzhanUmutlu\TNTRun\TNTRun;

class TNTRunCommand extends Command implements PluginIdentifiableCommand {
    public function __construct(TNTRun $plugin) {
        $this->plugin = $plugin;
        parent::__construct("tntrun", "TNTRun commands", \null, ["tr"]);
        $this->setPermission("tntrun.cmd");
    }
    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!isset($args[0])) {
            $sender->sendMessage("§c> Usage: §7/tr help");
            return;
        }
        switch ($args[0]) {
            case "help":
                $sender->sendMessage("§a> TNTRun commands:\n" .
                    "§7/tr help : You are here now\n".
                    "§7/tr create : Creates arena\n".
                    "§7/tr delete : Deletes arena\n".
                    "§7/tr setup : Setups arena\n".
                    "§7/tr arenas : Lists arenas\n".
                    "§7/tr forcestart : Starts arena");

                break;
            case "create":
                if(!$sender->hasPermission("tr.cmd.create")) {
                    $sender->sendMessage("§c> You don't have permission!");
                    break;
                }
                if(!isset($args[1])) {
                    $sender->sendMessage("§cUsage: §7/tr create <arenaName>");
                    break;
                }
                if(isset($this->plugin->arenas[$args[1]])) {
                    $sender->sendMessage("§c> $args[1] named arena already created!");
                    break;
                }
                $this->plugin->arenas[$args[1]] = new Arena($this->plugin, ["name" => $args[1],"level" => null,"slots" => null, "joinsign" => null,"spawn" => ""]);
                $sender->sendMessage("§a> $args[1] named arena created!");
                break;
            case "delete":
                if(!$sender->hasPermission("tr.cmd")) {
                    $sender->sendMessage("§c> You don't have permission!");
                    break;
                }
                if(!isset($args[1])) {
                    $sender->sendMessage("§cUsage: §7/tr delete <arenaName>");
                    break;
                }
                $index = "nope";
                foreach($this->plugin->arenas as $ar) {
                    if($ar->name == $args[1]) {
                        $index = array_search($ar, $this->plugin->arenas);
                    }
                }
                if(!isset($this->plugin->arenas[$index])) {
                    $sender->sendMessage("§c> $args[1] named arena not found!");
                    break;
                }
                $arena = $this->plugin->arenas[$index];

                foreach ($arena->players as $player) {
                    $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
                }

                if(is_file($file = $this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $args[1] . ".yml")) unlink($file);
                unset($this->plugin->arenas[$index]);
                $es = $this->plugin->arenalar->getNested("arenas");
                unset($es[$index]);
                $this->plugin->arenalar->setNested("arenas", $es);
                $this->plugin->arenalar->save();
                $this->plugin->arenalar->reload();
                $sender->sendMessage("§a> Arena deleted!");
                break;
            case "setup":
                if(!$sender->hasPermission("tr.cmd.set")) {
                    $sender->sendMessage("§c> You don't have permission!");
                    break;
                }
                if(!$sender instanceof Player) {
                    $sender->sendMessage("§c> Use this command in game!");
                    break;
                }
                if(!isset($args[1])) {
                    $sender->sendMessage("§cUsage: §7/tr setup <arenaName>");
                    break;
                }
                if(isset($this->plugin->setters[$sender->getName()])) {
                    $sender->sendMessage("§c> You are already in setup mode");
                    break;
                }
                $index = "nope";
                foreach($this->plugin->arenas as $ar) {
                    if($ar->name == $args[1]) {
                        $index = array_search($ar, $this->plugin->arenas);
                    }
                }
                if(!isset($this->plugin->arenas[$index])) {
                    $sender->sendMessage("§c> $args[1] named arena not found!");
                    break;
                }
                $this->plugin->arenas[$index]->setup = true;
                $sender->sendMessage("§6> You are in setup mode.\n".
                    "§7- Use §lhelp §r§7to see commands\n"  .
                    "§7- or use §ldone §r§7to leave from setup mode");
                $this->plugin->setters[$sender->getName()] = $this->plugin->arenas[$index];
                break;
            case "arenas":
                if(!$sender->hasPermission("tr.cmd.arenas")) {
                    $sender->sendMessage("§cYou don't have permission!");
                    break;
                }
                if(count($this->plugin->arenas) === 0) {
                    $sender->sendMessage("§6> There is no arena.");
                    break;
                }
                $list = "§7> Arenas:\n";
                foreach ($this->plugin->arenas as $arena) {
                    if($arena->setup) {
                        $list .= "§7- ".$arena->name." : §cclosed\n";
                    }
                    else {
                        $list .= "§7- ".$arena->name." : §aenabled\n";
                    }
                }
                $sender->sendMessage($list);
                break;
            case "forcestart":
                if(!$sender->hasPermission("tr.cmd.arenas")) {
                    $sender->sendMessage("§c> You don't have permission!");
                    break;
                }
                if(count($args) != 2) {
                    $sender->sendMessage("§c> Usage: /tr forcestart <arenaName>");
                    break;
                }
                $secili = null;
                foreach($this->plugin->arenas as $arena) {
                    if($arena->name == $args[1]) {
                        $secili = $arena;
                    }
                }
                if(!$secili) {
                    $sender->sendMessage("§c> Arena not found.");
                    break;
                }
                $secili->startGame();
                break;
            default:
                if(!$sender->hasPermission("tr.cmd.help")) {
                    $sender->sendMessage("§c> You don't have permission!!");
                    break;
                }
                $sender->sendMessage("§c> Usage: §7/tr help");
                break;
        }

    }
    public function getPlugin(): Plugin {
        return $this->plugin;
    }

}
