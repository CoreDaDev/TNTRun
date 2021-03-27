<?php
declare(strict_types=1);
namespace OguzhanUmutlu\TNTRun\arena;
use pocketmine\level\Level;

class MapReset {
    public function __construct(Arena $plugin) {
        $this->plugin = $plugin;
    }
    public function saveMap(Level $level) {
        $level->save(true);
        $levelPath = $this->plugin->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $level->getFolderName();
        $zipPath = $this->plugin->plugin->getDataFolder() . "saves" . DIRECTORY_SEPARATOR . $level->getFolderName() . ".zip";
        $zip = new \ZipArchive();
        if(is_file($zipPath)) {
            unlink($zipPath);
        }
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(realpath($levelPath)), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $file) {
            if($file->isFile()) {
                $filePath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename();
                $localPath = substr($filePath, strlen($this->plugin->plugin->getServer()->getDataPath() . "worlds"));
                $zip->addFile($filePath, $localPath);
            }
        }
        $zip->close();
    }
    public function loadMap(string $folderName, bool $justSave = false): ?Level {
        if(!$this->plugin->plugin->getServer()->isLevelGenerated($folderName)) {
            return null;
        }
        if($this->plugin->plugin->getServer()->isLevelLoaded($folderName)) {
            $this->plugin->plugin->getServer()->getLevelByName($folderName)->unload(true);
        }
        $zipPath = $this->plugin->plugin->getDataFolder() . "saves" . DIRECTORY_SEPARATOR . $folderName . ".zip";
        if(!file_exists($zipPath)) {
            $this->plugin->plugin->getServer()->getLogger()->error("($folderName) world failed while being saved. Try to save in setup mode.");
            return null;
        }
        $zipArchive = new \ZipArchive();
        $zipArchive->open($zipPath);
        $zipArchive->extractTo($this->plugin->plugin->getServer()->getDataPath() . "worlds");
        $zipArchive->close();
        if($justSave) {
            return null;
        }
        $this->plugin->plugin->getServer()->loadLevel($folderName);
        return $this->plugin->plugin->getServer()->getLevelByName($folderName);
    }
}
