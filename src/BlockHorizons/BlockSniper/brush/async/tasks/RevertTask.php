<?php

declare(strict_types = 1);

namespace BlockHorizons\BlockSniper\brush\async\tasks;

use BlockHorizons\BlockSniper\brush\BaseType;
use BlockHorizons\BlockSniper\Loader;
use BlockHorizons\BlockSniper\undo\Redo;
use BlockHorizons\BlockSniper\undo\Revert;
use BlockHorizons\BlockSniper\undo\Undo;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Server;

class RevertTask extends AsyncBlockSniperTask {

	/** @var int */
	protected $taskType = self::TYPE_REVERT;
	/** @var string */
	private $revert = "";

	public function __construct(Revert $revert) {
		$revert->secureAsyncBlocks();
		$this->revert = serialize($revert);
	}

	public function onRun() {
		/** @var Undo|Redo $revert */
		$revert = unserialize($this->revert);
		$chunks = $revert->getTouchedChunks();
		$revert->setManager(BaseType::establishChunkManager($chunks));

		$detached = $revert->getDetached();
		$revert->restore($this);
		$this->setResult([
			"chunks" => serialize($chunks),
			"revert" => serialize($detached)
		]);
	}

	/**
	 * @param Server $server
	 *
	 * @return bool
	 */
	public function onCompletion(Server $server): bool {
		/** @var Loader $loader */
		$loader = $server->getPluginManager()->getPlugin("BlockSniper");
		if($loader === null) {
			return false;
		}
		if(!$loader->isEnabled()) {
			return false;
		}
		$result = $this->getResult();
		/** @var Revert $revert */
		$revert = unserialize($result["revert"]);
		if(!($player = $server->getPlayer($revert->getPlayerName()))) {
			return false;
		}
		/** @var Chunk[] $chunks */
		$chunks = unserialize($result["chunks"]);
		$levelId = $player->getLevel()->getId();
		$level = $server->getLevel($levelId);
		if($level instanceof Level) {
			foreach($chunks as $hash => $chunk) {
				$x = $z = 0;
				Level::getXZ($hash, $x, $z);
				$level->setChunk($x, $z, $chunk);
			}
		}
		$loader->getRevertStorer()->saveRevert($revert, $player);
		return true;
	}


	/**
	 * @param Server $server
	 * @param mixed  $progress
	 *
	 * @return bool
	 */
	public function onProgressUpdate(Server $server, $progress): bool {
		$loader = $server->getPluginManager()->getPlugin("BlockSniper");
		if($loader instanceof Loader) {
			if($loader->isEnabled()) {
				$loader->getLogger()->debug($progress);
				return true;
			}
		}
		$this->setGarbage();
		return false;
	}
}