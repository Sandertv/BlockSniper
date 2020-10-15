<?php

declare(strict_types=1);

namespace BlockHorizons\BlockSniper\brush;

use BlockHorizons\BlockSniper\brush\async\BlockSniperChunkManager;
use BlockHorizons\BlockSniper\exception\InvalidItemException;
use Generator;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkManager;
use pocketmine\world\World;
use function array_rand;

/**
 * Class Type implements the basic behaviour of a brush type. It holds methods which are primarily used within classes
 * extending Type. Type implements the behaviour of filling an area depending on the area a user is allowed to brush in.
 */
abstract class Type{

	/** @var BrushProperties */
	protected $properties;
	/** @var Generator */
	protected $blocks = [];
	/** @var Block[] */
	protected $brushBlocks = [];
	/** @var Vector3 */
	protected $target;

	/** @var BlockSniperChunkManager|World */
	protected $chunkManager = null;
	/** @var bool */
	protected $myPlotChecked = false;
	/** @var Vector2[][] */
	protected $plotPoints = [];

	/**
	 * Type constructor: Constructs a new Type using the BrushProperties passed. The Type was executed while
	 * having $target as target block. The $blocks passed may be null, but must later be supplied using setBlocksInside
	 * in order to operate.
	 *
	 * @param BrushProperties $properties
	 * @param Target          $target
	 * @param Generator|null  $blocks
	 */
	public function __construct(BrushProperties $properties, Target $target, Generator $blocks = null){
		$this->properties = $properties;
		$this->target = $target->asVector3();
		$this->blocks = $blocks;
		$this->chunkManager = $target->getChunkManager();
	}

	/**
	 * getTarget returns the target block of the Type.
	 *
	 * @return Vector3
	 */
	protected function getTarget() : Vector3{
		return $this->target;
	}

	/**
	 * canBeHollow defines if the Type uses the hollow property. Types such as the Regenerate Type do not use this, as
	 * they operate only on the target block and have no realisation of hollow.
	 *
	 * @return bool
	 */
	public function canBeHollow() : bool{
		return true;
	}

	/**
	 * usesSize defines if the Type uses the size property. Types such as the Regenerate Type do not use this, as they
	 * operate only on the target block.
	 *
	 * @return bool
	 */
	public function usesSize() : bool{
		return true;
	}

	/**
	 * usesBrushBlocks defines if the Type uses brush blocks. Types such as the Regenerate Type do not use this, and
	 * overwrite the method.
	 *
	 * @return bool
	 */
	public function usesBrushBlocks() : bool{
		return true;
	}

	/**
	 * canBeExecutedAsynchronously defines if the Type is able to be executed asynchronously. If not, the Type will
	 * always be executed synchronously, no matter the size of the shape.
	 *
	 * @return bool
	 */
	public function canBeExecutedAsynchronously() : bool{
		return true;
	}

	/**
	 * getName returns the name of the Type.
	 *
	 * @return string
	 */
	public abstract function getName() : string;

	/**
	 * fill fills the blocks set in the generator of the Type, and in turn returns a generator yielding the undo
	 * blocks of the Type.
	 *
	 * @return Generator
	 */
	protected abstract function fill() : Generator;

	/**
	 * fillShape fills the shape previously set in the constructor or using setBlocksInside() and edits the blocks
	 * supplied the way the Type defines. If $plotPoints is not empty, modifications will only be allowed to happen
	 * within these points.
	 *
	 * @param Vector2[][] $plotPoints
	 *
	 * @return Generator|null
	 */
	public final function fillShape(array $plotPoints = []) : ?Generator{
		$this->plotPoints = $plotPoints;
		try{
			$this->brushBlocks = $this->properties->getBrushBlocks();
		}catch(InvalidItemException $exception){
			$this->brushBlocks = [VanillaBlocks::AIR()];
		}

		if(!empty($plotPoints)){
			$this->myPlotChecked = true;
		}
		$isWorld = $this->chunkManager instanceof World;
		if((!$isWorld && $this->canBeExecutedAsynchronously()) || $isWorld){
			return $this->fill();
		}

		return null;
	}

	/**
	 * randomBrushBlock returns a random brush block set in the BrushProperties passed during construction of the
	 * Type.
	 *
	 * @return Block
	 */
	public function randomBrushBlock() : Block{
		return $this->brushBlocks[array_rand($this->brushBlocks)];
	}

	/**
	 * setBrushBlocks sets the brush blocks of the type.
	 *
	 * @param Block[] $blocks
	 */
	public function setBrushBlocks(array $blocks) : void{
		$this->brushBlocks = $blocks;
	}

	/**
	 * getBlock returns the block at a specific position, provided the chunk of that block is loaded into the
	 * ChunkManager.
	 *
	 * @param Vector3 $pos
	 *
	 * @return Block
	 */
	public function getBlock(Vector3 $pos) : Block{
		return $this->chunkManager->getBlockAt($pos->x, $pos->y, $pos->z);
	}

	/**
	 * side returns the neighbouring block of the block passed at the side passed, provided the ChunkManager of the
	 * Type is not null.
	 *
	 * @param Vector3 $block
	 * @param int     $side
	 *
	 * @return Block
	 */
	public function side(Vector3 $block, int $side) : Block{
		if($this->chunkManager instanceof World && $block instanceof Block){
			return $block->getSide($side);
		}

		return $this->chunkManager->getBlock($block)->getSide($side);
	}

	/**
	 * putBiome puts a biome ID at a specific position, provided it is within the plot areas defined when constructing
	 * the type (if any at all).
	 *
	 * @param Vector3 $pos
	 * @param int     $biomeId
	 */
	protected function putBiome(Vector3 $pos, int $biomeId) : void{
		$valid = !$this->myPlotChecked;
		if($this->myPlotChecked){
			foreach($this->plotPoints as $plotCorners){
				if($pos->x < $plotCorners[0]->x || $pos->z < $plotCorners[0]->z || $pos->x > $plotCorners[1]->x || $pos->z > $plotCorners[1]->z){
					continue;
				}
				$valid = true;
				break;
			}
		}
		if(!$valid){
			return;
		}
		$this->chunkManager->setBiomeId($pos->x, $pos->z, $biomeId);
	}

	/**
	 * putBlock puts a block at the given location in the ChunkManager of the Type, provided it is not null. The
	 * block is only set if it is within the plot boundaries set during construction of the Type.
	 *
	 * @param Vector3 $pos
	 * @param Block   $block
	 */
	public function putBlock(Vector3 $pos, Block $block) : void{
		$valid = !$this->myPlotChecked;
		if($pos->y < 0 || $pos->y >= World::Y_MAX){
			return;
		}
		if($this->myPlotChecked){
			foreach($this->plotPoints as $plotCorners){
				if($pos->x < $plotCorners[0]->x || $pos->z < $plotCorners[0]->y || $pos->x > $plotCorners[1]->x || $pos->z > $plotCorners[1]->y){
					continue;
				}
				$valid = true;
				break;
			}
		}
		if(!$valid){
			return;
		}
		$this->chunkManager->setBlockAt($pos->x, $pos->y, $pos->z, $block, false);
	}

	/**
	 * delete removes a block at the given location provided the ChunkManager of the Type is not null. This
	 * method is a wrapper around putBlock(air).
	 *
	 * @param Vector3 $pos
	 */
	public function delete(Vector3 $pos) : void{
		$this->putBlock($pos, VanillaBlocks::AIR());
	}

	/**
	 * setChunkManager sets the ChunkManager of the Type, and returns itself for fluency. A Type loses its
	 * ChunkManager when passed onto an AsyncTask, so it must be reset.
	 *
	 * @param ChunkManager $manager
	 *
	 * @return Type
	 */
	public function setChunkManager(ChunkManager $manager) : self{
		$this->chunkManager = $manager;

		return $this;
	}

	/**
	 * setBlocksInside sets the generator used to supply blocks to the Type to edit.
	 *
	 * @param Generator|null $blocks
	 *
	 * @return Type
	 */
	public function setBlocksInside(?Generator $blocks) : self{
		$this->blocks = $blocks;

		return $this;
	}

	/**
	 * __sleep returns only specific properties of Type that can be serialised when passed onto an AsyncTask.
	 *
	 * @return string[]
	 */
	public function __sleep(){
		$this->chunkManager = $this->blocks = $this->brushBlocks = null;
		$arr = [];
		foreach($this as $key => $value){
			$arr[] = $key;
		}

		return $arr;
	}
}
