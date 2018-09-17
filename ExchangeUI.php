<?php
/**
 * @name ExchangeUI
 * @author alvin0319
 * @main ExchangeUI\ExchangeUI
 * @version 1.0.0
 * @api 4.0.0
 */

namespace ExchangeUI;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket; // 커스텀 UI 관련
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket; // 커스텀 UI 관련
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\item\Item;
//한글깨짐방지

class ExchangeUI extends PluginBase implements Listener {
	private $db;
	private $config;
	public $prefix = "§b§l[ §f교환§b ] §f";
	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->config = new Config($this->getDataFolder() . "Config.yml", Config::YAML);
		$this->db = $this->config->getAll();
		$this->cmd = new PluginCommand("교환", $this);
		$this->cmd->setDescription("교환 UI 를 엽니다");
		$this->getServer()->getCommandMap()->register("교환", $this->cmd);
	}
	public function sendUI(Player $player, $code, $data) {
		$pk = new ModalFormRequestPacket();
		$pk->formId = $code;
		$pk->formData = $data;
		$player->dataPacket($pk);
	}
	public function MainData() {
		$encode = [
		"type" => "form",
		"title" => "Exchange UI!",
		"content" => "원하시는 항목을 선택해주세요!",
		"buttons" => [
		[
		"text" => "나가기",
		],
		[
		"text" => "교환 UI 열기",
		],
		[
		"text" => "교환 추가\n(오피만 가능)",
		],
		[
		"text" => "교환 제거\n(오피만 가능)",
		],
		[
		"text" => "교환 목록"
		]
		]
		];
		return json_encode($encode);
	}
	public function Exchange() {
		$encode = [
		"type" => "custom_form",
		"title" => "Exchange!",
		"content" => [
		[
		"type" => "input",
		"text" => "교환할 교환 이름을 입력해주세요",
		]
		]
		];
		return json_encode($encode);
	}
	public function addExchange() {
		$encode = [
		"type" => "custom_form",
		"title" => "교환 추가",
		"content" => [
		[
		"type" => "input",
		"text" => "교환 이름",
		],
		[
		"type" => "input",
		"text" => "교환시 줄 아이템(ex: 46:0:1)\n아이템코드:데미지:갯수",
		],
		[
		"type" => "input",
		"text" => "교환시 필요한 아이템(ex: 46:0:1\n아이템코드:데미지:갯수)",
		]
		]
		];
		return json_encode($encode);
	}
	public function removeExchangeUI() {
		$encode = [
		"type" => "custom_form",
		"title" => "교환 제거",
		"content" => [
		[
		"type" => "input",
		"text" => "제거할 교환 이름을 입력해주세요",
		]
		]
		];
		return json_encode($encode);
	}
	public function onDataPacketRecieve(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		if ($packet instanceof ModalFormResponsePacket) {
			$id = $packet->formId;
			$val = json_decode($packet->formData, true);
			if ($id === 135792468) {
				if ($val === 0) {
					$player->sendMessage($this->prefix . "교환 UI 에서 나왔습니다");
				} else if ($val === 1) {
					$this->sendUI($player, 246813579, $this->Exchange());
				} else if ($val === 2) {
					if (! $player->isOp()) {
						$player->sendMessage($this->prefix . "권한이 없습니다");
						return;
					}
					$this->sendUI($player, 135797531, $this->addExchange());
				} else if ($val === 3) {
					if (! $player->isOp()) {
						$player->sendMessage($this->prefix . "권한이 부족합니다");
						return;
					}
					$this->sendUI($player, 12345, $this->removeExchangeUI());
				} else if ($val === 4) {
					$this->listExchange($player);
				}
			} else if ($id === 246813579) {
				if (! isset ($val[0])) {
					$player->sendMessage($this->prefix . "교환 이름을 입력해주세요");
					return;
				}
				$this->GetItem($player, $val[0]);
			} else if ($id === 135797531) {
				if (! isset ($val[0]) or ! isset ($val[1]) or ! isset ($val[2])) {
					$player->sendMessage($this->prefix . "이름과 교환시 받을 아이템, 필요한 아이템을 입력주세요");
					return;
				}
				$this->addExchangeItem($player, $val[0], $val[1], $val[2]);
			} else if ($id === 12345) {
				$this->removeExchange($player, $val[0]);
			}
		}
	}
	public function GetItem($player, $name) {
		if (! isset ($this->db[$name])) {
			$player->sendMessage($this->prefix . "그런 교환은 없습니다");
			return;
		}
		$a = explode(":", $this->db[$name]);
		if (! $player->getInventory()->contains(Item::get($a[0], $a[1], $a[2]))) {
			$player->sendMessage($this->prefix . "아이템이 부족합니다");
			return;
		}
		$player->getInventory()->removeItem(Item::get($a[0], $a[1], $a[2]));
		$player->getInventory()->addItem(Item::get($a[3], $a[4], $a[5]));
		$player->sendMessage($this->prefix . "교환했습니다");
	}
	public function addExchangeItem($player, $name, $remove, $add) {
		$this->db[$name] = $add . ":" . $remove;
		$player->sendMessage($this->prefix . $name . " 교환을 추가했습니다");
		$this->save();
	}
	public function onCommand(Commandsender $sender, Command $cmd, string $label, array $args) : bool{
		if ($cmd->getName() === "교환") {
			$this->sendUI($sender, 135792468, $this->MainData());
		}
		return true;
	}
	public function save() {
		$this->config->setAll($this->db);
		$this->config->save();
	}
	public function removeExchange($player, $name) {
		if (! isset ($this->db[$name])) {
			$player->sendMessage($this->prefix . "그런 교환은 없습니다");
			return;
		}
		unset($this->db[$name]);
		$player->sendMessage($this->prefix . $name . " 교환이 역사속으로 사라졌습니다....");
		$this->save();
	}
	public function listExchange($player) {
		foreach($this->db as $k => $value) {
			$a = explode(":", $value);
			$player->sendMessage($this->prefix . "{$k} 교환 {$a[0]}:{$a[1]} 아이템 {$a[2]} 개로 {$a[3]}:{$a[4]} 아이템 {$a[5]} 개를 교환 가능합니다");
		}
	}
}