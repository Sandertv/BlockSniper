<?php

namespace Sandertv\BlockSniper;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use Sandertv\BlockSniper\brush\Brush;
use Sandertv\BlockSniper\cloning\CloneStorer;
use Sandertv\BlockSniper\commands\BlockSniperCommand;
use Sandertv\BlockSniper\commands\BrushCommand;
use Sandertv\BlockSniper\commands\cloning\CloneCommand;
use Sandertv\BlockSniper\commands\cloning\PasteCommand;
use Sandertv\BlockSniper\commands\UndoCommand;
use Sandertv\BlockSniper\listeners\EventListener;
use Sandertv\BlockSniper\tasks\UndoDiminishTask;
use Sandertv\BlockSniper\data\ConfigData;

class Loader extends PluginBase {
	
	const VERSION = "1.2.1";
	const API_TARGET = "2.0.0 - 3.0.0-ALPHA3";
	
	public $undoStore;
	public $cloneStore;
	public $settings;
	public $brush;
	
	public $availableLanguages = [
		"en",
		"nl",
		"de",
		"fr",
		"fa",
		"ru",
		"zh_tw"
	];
	public $language;
	
	public function onEnable() {
		$this->reloadAll();
		
		$this->registerCommands();
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
	}
	
	public function reloadAll() {
		$this->settings = new ConfigData($this);
		
		$this->brush = new Brush($this);
		$this->undoStore = new UndoStorer($this);
		$this->cloneStore = new CloneStorer($this);
		if(!is_dir($this->getDataFolder())) {
			mkdir($this->getDataFolder());
		}
		if(!is_dir($this->getDataFolder() . "templates/")) {
			mkdir($this->getDataFolder() . "templates/");
		}
		if(!is_dir($this->getDataFolder() . "languages/")) {
			mkdir($this->getDataFolder() . "languages/");
		}
		
		$this->saveResource("settings.yml");
		$this->settings->collectSettings();
		
		if(!$this->setupLanguageFile()) {
			$this->getLogger()->info(TF::AQUA . "[BlockSniper] No valid language selected, English has been auto-selected.\n" . TF::AQUA . "Please setup a language by using /blocksniper language <lang>.");
		} else {
			$this->getLogger()->info(TF::AQUA . "[BlockSniper] Language selected: " . TF::GREEN . $this->getSettings()->get("Message-Language"));
		}
	}
	
	/**
	 * @return bool
	 */
	public function setupLanguageFile(): bool {
		if(!file_exists($this->getDataFolder() . "language.yml")) {
			foreach($this->availableLanguages as $language) {
				if($this->getSettings()->get("Message-Language") === $language) {
					$this->saveResource("languages/" . $language . ".yml");
					$this->language = new Config($this->getDataFolder() . "languages/" . $language . ".yml", Config::YAML);
					return true;
				}
			}
		}
		$this->saveResource("languages/en.yml");
		$this->language = new Config($this->getDataFolder() . "languages/en.yml", Config::YAML);
		return false;
	}
	
	/**
	 * @return Config
	 */
	public function getSettings(): Config {
		return $this->settings;
	}
	
	public function registerCommands() {
		$blockSniperCommands = [
			"blocksniper" => new BlockSniperCommand($this),
			"brush" => new BrushCommand($this),
			"undo" => new UndoCommand($this),
			"clone" => new CloneCommand($this),
			"paste" => new PasteCommand($this)
		];
		foreach($blockSniperCommands as $name => $class) {
			$this->getServer()->getCommandMap()->register($name, $class);
		}
	}
	
	public function onDisable() {
		$this->getLogger()->info(TF::RED . "BlockSniper has been disabled.");
		$this->getUndoStore()->resetUndoStorage();
	}
	
	/**
	 * @return UndoStorer
	 */
	public function getUndoStore(): UndoStorer {
		return $this->undoStore;
	}
	
	/**
	 * @param string $message
	 *
	 * @return string
	 */
	public function getTranslation(string $message) {
		return (string)$this->language->getNested($message);
	}
	
	public function scheduleTasks() {
		$this->getServer()->getScheduler()->scheduleDelayedTask(new UndoDiminishTask($this), 2400);
	}
	
	/**
	 * @return CloneStorer
	 */
	public function getCloneStore(): CloneStorer {
		return $this->cloneStore;
	}
}
