<?php

declare(strict_types = 1);

namespace BlockHorizons\BlockSniper\listeners;

use BlockHorizons\BlockSniper\brush\BrushManager;
use BlockHorizons\BlockSniper\events\BrushUseEvent;
use BlockHorizons\BlockSniper\Loader;
use BlockHorizons\BlockSniper\ui\WindowHandler;
use BlockHorizons\BlockSniper\undo\Undo;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;

class BrushListener implements Listener {

	/** @var Loader */
	private $loader = null;
	/** @var array */
	private $cancelWindow = [];

	public function __construct(Loader $loader) {
		$this->loader = $loader;
	}

	/**
	 * @param PlayerInteractEvent $event
	 *
	 * @return bool
	 */
	public function brush(PlayerInteractEvent $event): bool {
		$player = $event->getPlayer();
		if($player->getInventory()->getItemInHand()->getId() === (int) $this->getLoader()->getSettings()->getBrushItem()) {
			if($player->hasPermission("blocksniper.command.brush")) {
				$this->getLoader()->getBrushManager()->createBrush($player);

				$brush = BrushManager::get($player);
				$shape = $brush->getShape();
				$type = $brush->getType();

				$this->getLoader()->getServer()->getPluginManager()->callEvent($event = new BrushUseEvent($this->getLoader(), $player, $shape, $type));
				if($event->isCancelled()) {
					return false;
				}

				if($brush->getSize() >= $this->getLoader()->getSettings()->getMinimumAsynchronousSize() && $type->canExecuteAsynchronously()) {
					$shape->editAsynchronously($type);
				} else {
					$type->setBlocksInside($shape->getBlocksInside());
					$undoBlocks = $type->fillShape();
					$this->getLoader()->getRevertStorer()->saveRevert(new Undo($undoBlocks), $player);
				}
				$this->decrementBrush($player);
				$event->setCancelled();
				return true;
			}
		}
		return false;
	}

	/**
	 * @param PlayerItemHeldEvent $event
	 */
	public function onItemHeld(PlayerItemHeldEvent $event): bool {
		$player = $event->getPlayer();
		if($event->getItem()->getId() === $this->getLoader()->getSettings()->getBrushItem()) {
			if($player->hasPermission("blocksniper.command.brush")) {
				if(time() - $this->cancelWindow[$player->getLowerCaseName()] < 2) {
					return false;
				}
				$this->getLoader()->getBrushManager()->createBrush($player);

				$windowHandler = new WindowHandler();
				$packet = new ModalFormRequestPacket();
				$packet->formId = $windowHandler->getWindowIdFor(WindowHandler::WINDOW_MAIN_MENU);
				$packet->formData = $windowHandler->getWindowJson(WindowHandler::WINDOW_MAIN_MENU, $this->getLoader(), $player);
				$player->dataPacket($packet);
				return true;
			}
		}
		return false;
	}

	/**
	 * @param PlayerJoinEvent $event
	 *
	 * @return bool
	 */
	public function onJoin(PlayerJoinEvent $event): bool {
		$this->cancelWindow[$event->getPlayer()->getLowerCaseName()] = time();
		return true;
	}

	/**
	 * @return Loader
	 */
	public function getLoader(): Loader {
		return $this->loader;
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function decrementBrush(Player $player): bool {
		if(BrushManager::get($player)->isDecrementing()) {
			if(BrushManager::get($player)->getSize() <= 1) {
				if($this->getLoader()->getSettings()->resetDecrementBrush() !== false) {
					BrushManager::get($player)->setSize(BrushManager::get($player)->resetSize);
					$player->sendPopup(TF::GREEN . "Brush reset to original size.");
					return true;
				}
				return false;
			}
			BrushManager::get($player)->setSize(BrushManager::get($player)->getSize() - 1);
			return true;
		}
		return false;
	}
}
