<?php

namespace Sandertv\BlockSniper\cloning;

use Sandertv\BlockSniper\Loader;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\block\Block;

class CloneStorer {
	
	public $copyStore = [];
	public $originalCenter = null;
	public $target = null;
	
	public function __construct(Loader $owner) {
		$this->owner = $owner;
	}
	
	/**
	 * @return Loader
	 */
	public function getOwner(): Loader {
		return $this->owner;
	}
	
	public function setTargetBlock(Vector3 $target) {
		$this->target = $target;
	}
	
	public function getTargetBlock(): Vector3 {
		return $this->target;
	}
	
	// Required for math to copy-paste it on the location looked at.
	public function setOriginalCenter(Vector3 $center) {
		$this->originalCenter = $center;
	}
	
	public function getOriginalCenter(): Vector3 {
		return $this->originalCenter;
	}
	
	/**
	 * @param array $blocks
	 */
	public function saveCopy(array $blocks) {
		$i = 0;
		$this->unsetCopy();
		foreach($blocks as $block) {
			$this->copyStore[$block->getId() . ":" . $block->getDamage() . "(" . $i . ")"] = [
				"x" => $block->x - $this->getOriginalCenter()->x,
				"y" => $block->y - $this->getOriginalCenter()->y,
				"z" => $block->z - $this->getOriginalCenter()->z,
				"level" => $block->level->getName()
			];
			$i++;
		}
		unset($i);
	}
	
	public function pasteCopy() {
		foreach($this->copyStore as $key => $block) {
			$Id = explode("(", $key);
			$blockId = $Id[0];
			$meta = explode(":", $blockId);
			$meta = $meta[1];
			$x = $block["x"];
			$y = $block["y"] + 1;
			$z = $block["z"];
			$finalBlock = Item::get($blockId)->getBlock();
			$finalBlock->setDamage((int) $meta !== null ? $meta : 0);
			
			// Start pasting the copy...
			$blockPos = new Vector3($x + $this->getTargetBlock()->x, $y + $this->getTargetBlock()->y, $z + $this->getTargetBlock()->z);
			$this->getOwner()->getServer()->getLevelByName($block["level"])->setBlock($blockPos, Block::get((int) $blockId, (int) $meta), false, false);
		}
	}
	
	public function unsetCopy() {
		foreach($this->copyStore as $blocks) {
			unset($blocks);
		}
	}
	
	public function resetCopyStorage() {
		$this->copyStore = [];
		$this->originalCenter = null;
		$this->target = null;
	}
	
	/**
	 * @return bool
	 */
	public function copyStoreExists() {
		if(!is_array($this->copyStore) || empty($this->copyStore)) {
			return false;
		}
		return true;
	}
	
	/**
	 * @return int
	 */
	public function getCopyBlockAmount() {
		return count($this->copyStore);
	}
	
	/*
	 * Templates
	 */
	
	/**
	 * @param string  $templateName
	 * @param array   $blocks
	 * @param Vector3 $targetBlock
	 *
	 * @return bool
	 */
	public function saveTemplate(string $templateName, array $blocks, Vector3 $targetBlock): bool {
		$template = [];
		$i = 0;
		foreach($blocks as $block) {
			$template[$block->getId() . ":" . $block->getDamage() . "(" . $i . ")"] = [
				"x" => $block->x - $targetBlock->x,
				"y" => $block->y - $targetBlock->y,
				"z" => $block->z - $targetBlock->z
			];
			$i++;
		}
		unset($i);
		file_put_contents($this->getOwner()->getDataFolder() . "templates/" . $templateName . ".yml", serialize($template));
		return true;
	}
	
	/**
	 * @param string $templateName
	 * @param Block  $targetBlock
	 *
	 * @return bool
	 */
	public function pasteTemplate(string $templateName, Block $targetBlock): bool {
		$data = file_get_contents($this->getOwner()->getDataFolder() . "templates/" . $templateName . ".yml");
		$content = unserialize($data);
		
		foreach($content as $key => $block) {
			$Id = explode("(", $key);
			$blockId = $Id[0];
			$meta = explode(":", $blockId);
			$meta = $meta[1];
			$x = $block["x"];
			$y = $block["y"] + 1;
			$z = $block["z"];
			$finalBlock = Item::get($blockId)->getBlock();
			$finalBlock->setDamage((int) $meta !== null ? $meta : 0);
			
			$blockPos = new Vector3($x + $targetBlock->x, $y + $targetBlock->y, $z + $targetBlock->z);
			$targetBlock->getLevel()->setBlock($blockPos, Block::get((int) $blockId, (int) $meta), false, false);
		}
		return true;
	}
	
	public function templateExists(string $templateName): bool {
		if(is_file($this->getOwner()->getDataFolder() . "templates/" . $templateName . ".yml")) {
			return true;
		}
		return false;
	}
}