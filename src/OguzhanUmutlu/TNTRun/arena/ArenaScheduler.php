<?php
declare(strict_types=1);
namespace OguzhanUmutlu\TNTRun\arena;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\scheduler\Task;
use pocketmine\tile\Sign;
use OguzhanUmutlu\TNTRun\math\Time;
use OguzhanUmutlu\TNTRun\math\Vector3;
class ArenaScheduler extends Task {
    protected $plugin;
    public $startTime = 40;
    public $gameTime = 20 * 60;
    public $restartTime = 10;
    public $restartData = [];
    public function __construct(Arena $plugin) {
        $this->plugin = $plugin;
    }
    public function onRun(int $currentTick) {
        $this->reloadSign();
        if($this->plugin->setup) return;
        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= 2) {
                    $this->plugin->broadcastMessage(str_replace("{startsAt}", Time::calculateTime($this->startTime), $this->plugin->plugin->messages->getNested("gameStartsTip")), Arena::MSG_TIP);
                    $this->startTime--;
                    if($this->startTime == 0) {
                        $this->plugin->startGame();
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound(new AnvilUseSound($player->asVector3()));
                        }
                    }
                    else {
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound(new ClickSound($player->asVector3()));
                        }
                    }
                }
                else {
                    $this->plugin->broadcastMessage($this->plugin->plugin->messages->getNested("morePlayers"), Arena::MSG_TIP);
                    $this->startTime = 40;
                }
                break;
            case Arena::PHASE_GAME:
                $this->plugin->broadcastMessage(str_replace(["{playerCount}", "{endsAt}"], [count($this->plugin->players), Time::calculateTime($this->gameTime)], $this->plugin->plugin->messages->getNested("gameTip")), Arena::MSG_TIP);
                if($this->plugin->checkEnd()) $this->plugin->startRestart();
                $this->gameTime--;
                break;
            case Arena::PHASE_RESTART:
                $this->plugin->broadcastMessage(str_replace("{restartsAt}", $this->restartTime, $this->plugin->plugin->messages->getNested("restartTip")), Arena::MSG_TIP);
                $this->restartTime--;
                switch ($this->restartTime) {
                    case 0:
                        foreach ($this->plugin->players as $player) {
                            $player->teleport($this->plugin->plugin->getServer()->getDefaultLevel()->getSpawnLocation());

                            $player->getInventory()->clearAll();
                            $player->getArmorInventory()->clearAll();
                            $player->getCursorInventory()->clearAll();

                            $player->setFood(20);
                            $player->setHealth(20);

                            $player->setGamemode($this->plugin->plugin->getServer()->getDefaultGamemode());
                        }
                        $this->plugin->loadArena(true);
                        $this->reloadTimer();
                        break;
                }
                break;
        }
    }
    public function reloadSign() {
        if(!is_array($this->plugin->data["joinsign"]) || empty($this->plugin->data["joinsign"])) return;
        $signPos = Position::fromObject(Vector3::fromString($this->plugin->data["joinsign"][0]), $this->plugin->plugin->getServer()->getLevelByName($this->plugin->data["joinsign"][1]));
        if(!$signPos->getLevel() instanceof Level || is_null($this->plugin->level)) return;
        $signText = [
            "§6§lTNTRun",
            "§9[ §b? / ? §9]",
            "§6".($this->plugin->plugin->messages->getNested("Setup")),
            "§6".($this->plugin->plugin->messages->getNested("Wait"))
        ];
        if($signPos->getLevel()->getTile($signPos) === null) return;
        if($this->plugin->setup || $this->plugin->level === null) {
            $sign = $signPos->getLevel()->getTile($signPos);
            $sign->setText($signText[0], $signText[1], $signText[2], $signText[3]);
            return;
        }
        $signText[1] = "§9[ §b" . count($this->plugin->players) . " / " . $this->plugin->data["slots"] . " §9]";
        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= $this->plugin->data["slots"]) {
                    $signText[2] = "§6".($this->plugin->plugin->messages->getNested("Full"));
                    $signText[3] = "§8".(str_replace("{map}", $this->plugin->data["name"], ($this->plugin->plugin->messages->getNested("Map"))));
                }
                else {
                    $signText[2] = "§a".($this->plugin->plugin->messages->getNested("Join"));
                    $signText[3] = "§8".(str_replace("{map}", $this->plugin->data["name"], ($this->plugin->plugin->messages->getNested("Map"))));
                }
                break;
            case Arena::PHASE_GAME:
                $signText[2] = "§5".($this->plugin->plugin->messages->getNested("Started"));
                $signText[3] = "§8".(str_replace("{map}", $this->plugin->data["name"], ($this->plugin->plugin->messages->getNested("Map"))));
                break;
            case Arena::PHASE_RESTART:
                $signText[2] = "§c".($this->plugin->plugin->messages->getNested("Restarting"));
                $signText[3] = "§8".(str_replace("{map}", $this->plugin->data["name"], ($this->plugin->plugin->messages->getNested("Map"))));
                break;
        }
        $sign = $signPos->getLevel()->getTile($signPos);
        if($sign instanceof Sign)
            $sign->setText($signText[0], $signText[1], $signText[2], $signText[3]);
    }
    public function reloadTimer() {
        $this->startTime = 30;
        $this->gameTime = 20 * 60;
        $this->restartTime = 10;
    }
}
