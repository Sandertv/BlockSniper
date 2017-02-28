<?php

namespace Sandertv\BlockSniper\data;

use Sandertv\BlockSniper\Loader;
use pocketmine\utils\Config;

class TranslationData {
	
	public function __construct(Loader $plugin) {
		$this->plugin = $plugin;
		
		$this->collectTranslations();
	}
	
	/**
	 * @return Loader
	 */
	public function getOwner(): Loader {
		return $this->plugin;
	}
	
	public function collectTranslations() {
		// TODO: Find a good way to collect correct language.
		/*
		commands:
			errors:
				no-permission: "You do not have permission to do that."
                console-use: "This command can't be executed by the console."
                radius-not-numeric: "The radius should be numeric."
                radius-too-big: "That radius is too big. Please enter a smaller one."
                no-target-found: "No target block could be found."
                no-valid-block: "Block not found, please enter a valid block name/ID."
                shape-not-found: "Shape or type not found."
                no-modifications: "No modifications were found to undo."
                paste-not-found: "That paste type could not be found."
                clone-not-found: "That clone type could not be found."
                name-not-set: "No valid name has been given."
                template-not-existing: "A template with that name does not exist."
            succeed:
                default: "Successfully launched the shape at the location looked at."
                undo: "Successfully undid the last modification."
                language: "Language has been changed successfully."
                paste: "Pasted the clone successfully."
                clone: "Area has been cloned and copied successfully."
		brushwand:
            disable: "Brushwand has been disabled."
            enable: "Brushwand has been enabled."
		*/
	}
	
	public function get() {
		
	}
}