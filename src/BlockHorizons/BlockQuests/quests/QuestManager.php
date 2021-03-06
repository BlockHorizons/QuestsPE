<?php

declare(strict_types = 1);

namespace BlockHorizons\BlockQuests\quests;

use BlockHorizons\BlockQuests\BlockQuests;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;

class QuestManager {

	/** @var BlockQuests */
	private $plugin;
	/** @var Quest[] */
	private $quests = [];

	public function __construct(BlockQuests $plugin) {
		$this->plugin = $plugin;
	}

	/**
	 * @return Quest[]
	 */
	public function getQuestList(): array {
		$quests = [];
		foreach(scandir($this->plugin->getDataFolder() . "quests/") as $quest) {
			$path = $this->plugin->getDataFolder() . "quests/" . $quest;
			if($quest === "." || $quest === "..") {
				continue;
			}
			$questId = explode(".", $quest)[0];
			if(!is_file($path) || !is_numeric($questId)) {
				continue;
			}
			$data = yaml_parse_file($path);
			if((new QuestValidator($data))->getResult() === false) {
				$this->plugin->getLogger()->debug("Invalid quest content file '" . $quest . "' found. Skipping...");
				continue;
			}
			$quests[] = $this->getQuestById((int) $questId);
		}
		return $quests;
	}

	/**
	 * @param int $id
	 *
	 * @return Quest
	 */
	public function getQuestById(int $id): Quest {
		if(!isset($this->quests[$id])) {
			$this->quests[$id] = $this->plugin->getQuestStorage()->fetch($id);
		}
		return $this->quests[$id];
	}

	/**
	 * @param Quest  $quest
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function startQuest(Quest $quest, Player $player): bool {
		$message = $this->getPlugin()->getBlockQuestsConfig()->getQuestStartingFormat();
		$processedMessage = str_replace("{STARTING_MESSAGE}", $quest->getStartingMessage() . TF::GREEN . TF::BOLD, str_replace("{QUEST_NAME}", TF::RESET . TF::AQUA . $quest->getQuestName(), str_replace("{QUEST_DESCRIPTION}", TF::GRAY . $quest->getQuestDescription(), $message)));
		$player->sendMessage(TF::GREEN . $processedMessage);
		$this->getPlugin()->getPlayerDatabase()->startQuest($player, $quest->getId());
		return true;
	}

	/**
	 * @return BlockQuests
	 */
	public function getPlugin(): BlockQuests {
		return $this->plugin;
	}

	/**
	 * @param Quest  $quest
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function finishQuest(Quest $quest, Player $player): bool {
		$player->sendMessage(TF::GREEN . $quest->getFinishingMessage());
		$this->getPlugin()->getPlayerDatabase()->finishQuest($player, $quest->getId());
		foreach($quest->getRewardCommands() as $command) {
			$this->getPlugin()->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace("{PLAYER}", $player->getName(), $command));
		}
		return true;
	}

	/**
	 * @param int $id
	 *
	 * @return bool
	 */
	public function questExists(int $id): bool {
		return $this->getPlugin()->getQuestStorage()->exists($id);
	}

	/**
	 * @param int $id
	 *
	 * @return bool
	 */
	public function deleteQuest(int $id): bool {
		unset($this->quests[$id]);
		if($this->getPlugin()->getQuestStorage()->exists($id)) {
			$this->getPlugin()->getQuestStorage()->delete($id);
			return true;
		}
		return false;
	}
}