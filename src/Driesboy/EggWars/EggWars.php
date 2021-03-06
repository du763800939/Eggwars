<?php

namespace Driesboy\EggWars;

use Driesboy\EggWars\Commands\HubCommand;
use Driesboy\EggWars\Commands\EggWarsCommand;
use Driesboy\EggWars\Task\Game;
use Driesboy\EggWars\Task\SignManager;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\AddEntityPacket;

class EggWars extends PluginBase{

  public $players = array();

  public $status = array();

  public $StartTime = array();

  public $EndTime = array();

  private static $ins;

  public $egg = array();

  public $sb = '§6EggWars> ';

  public $tyazi = '§8§l» §r§6Egg §fWars §l§8«';

  public $m = array();

  public $mo = array();

  public function onEnable(){
    @mkdir($this->getDataFolder());
    @mkdir($this->getDataFolder()."Arenas/");
    @mkdir($this->getDataFolder()."Back-Up/");
    self::$ins = $this;
    $this->saveDefaultConfig();
    $this->saveResource("shop.yml");
    $this->AnotherPrepare();
    $this->PrepareArenas();
  }

  public static function getInstance(){
    return self::$ins;
  }

  public function AnotherPrepare(){
    Server::getInstance()->getPluginManager()->registerEvents(new EventListener(), $this);
    Server::getInstance()->getScheduler()->scheduleRepeatingTask(new SignManager($this), 20);
    Server::getInstance()->getScheduler()->scheduleRepeatingTask(new Game($this), 20);
    Server::getInstance()->getCommandMap()->register("ew", new EggWarsCommand());
    Server::getInstance()->getCommandMap()->register("hub", new HubCommand());
  }

  public function PrepareArenas(){
    foreach($this->Arenas() as $arena){
      if($this->ArenaReady($arena)){
        $this->ArenaRefresh($arena);
      }
    }
  }

  public function ArenaPlayer($arena){
    return $this->players[$arena];
  }

  public function resetPlayer(Player $player){
    $player->setNameTag($player->getName());
    $player->getInventory()->clearAll();
    $player->setHealth(20);
    $player->setFood(20);
    $player->removeAllEffects();
  }

  public function RemoveArenaPlayer($arena, $player){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $status = $this->status[$arena];
    if($status === "Lobby"){
      $this->ArenaMessage($arena, "§6$player left the game ". count($this->ArenaPlayer($arena)) . "/" .$ac->get("Team") * $ac->get("PlayersPerTeam"));
    }
    $players = $this->players[$arena];
    if(@in_array($player, $players)){
      $this->resetPlayer($player);
      $player->setGamemode($this->getServer()->getDefaultGamemode());
      $player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
      unset($players[array_search($player, $players)]);
    }
  }

  public function AddArenaPlayer($arena, $player){
    $players = $this->players[$arena];
    if(!$this->IsInArena($player)){
      $this->resetPlayer($player);
      array_push($players, $player);
    }
  }

  public function Arenas(){
    $Arenas = array();
    $d = opendir($this->getDataFolder()."Arenas");
    while($file = readdir($d)){
      if($file != "." && $file != ".."){
        $arena = str_replace(".yml", "", $file);
        if($this->ArenaReady($arena)){
          $Arenas[] = $arena;
        }
      }
    }
    return $Arenas;
  }

  public function Teams(){
    $teams = array(
      "WHITE" => "§f",
      "ORANGE" => "§6",
      "LIGHT-BLUE" => "§b",
      "YELLOW" => "§e",
      "LIME" => "§a",
      "PINK" => "§d",
      "GRAY" => "§8",
      "LIGHT-GRAY" => "§7",
      "CYAN" => "§3",
      "PURPLE" => "§5",
      "BLUE" => "§9",
      "BROWN" => "§6",
      "GREEN" => "§2",
      "RED" => "§c",
      "BLACK" => "§0"
    );
    return $teams;
  }

  public function TeamSearcher(){
    $tyc = array(
      "WHITE" => 0,
      "ORANGE" => 1,
      "LIGHT-BLUE" => 3,
      "YELLOW" => 4,
      "LIME" => 5,
      "PINK" => 6,
      "GRAY" => 7,
      "LIGHT-GRAY" => 8,
      "CYAN" => 9,
      "PURPLE" => 10,
      "BLUE" => 11,
      "BROWN" => 12,
      "GREEN" => 13,
      "RED" => 14,
      "BLACK" => 15
    );
    return $tyc;
  }


  public function ArenaControl($arena){
    if(file_exists($this->getDataFolder()."Arenas/$arena.yml")){
      return true;
    }else{
      return false;
    }
  }

  public function ArenaReady($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    if($ac->get("World")){
      if(file_exists($this->getDataFolder()."Back-Up/".$ac->get("World")."/")){
        return true;
      }else{
        return false;
      }
    }else{
      return false;
    }
  }

  public function IsInArena($player){
    $Arenas = $this->Arenas();
    $a = null;
    foreach ($Arenas as $arena){
      $players = $this->players[$arena];
      if(in_array($player, $players)){
        $a = $arena;
        break;
      }
    }
    if($a != null){
      return $a;
    }else{
      return false;
    }
  }

  public function ArenaStatus($arena){
    return $this->status[$arena];
  }

  public function ArenaCreate($arena, $team, $tbo, Player $p){
    if(!$this->ArenaControl($arena)){
      if($team <= 8) {
        if($tbo <= 8) {
          $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
          $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
          $this->status[$arena] = "Lobby";
          $this->StartTime[$arena] = (int) $cfg->get("StartTime"));
          $this->EndTime[$arena] = (int) $cfg->get("EndTime"));
          $ac->set("Team", (int) $team);
          $ac->set("PlayersPerTeam", (int) $tbo);
          $ac->save();
          $this->players[$arena] = array();
          $p->sendMessage($this->sb."§a$arena was successfully built!");
        }else{
          $p->sendMessage("§8» §cThe number of players per team should be 8 or less.");
        }
      }else{
        $p->sendMessage("§8» §cTeam number should be 8 or less.");
      }
    }else{
      $p->sendMessage("§8» §c$arena already exists!");
    }
  }

  public function ArenaTeams($arena){
    if($this->ArenaControl($arena)){
      $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
      $teams = array();
      foreach ($this->Teams() as $team => $color){
        if(!empty($ac->getNested($team.".Y"))){
          $teams[] = $team;
        }
      }
      return $teams;
    }else{
      return false;
    }
  }

  public function ArenaSet($arena, $team, Player $p){
    if($this->ArenaControl($arena)){
      $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
      if(!empty($this->Teams()[$team])){
        if(count($this->ArenaTeams($arena)) === $ac->get("Team")){
          if($ac->getNested("$team.X")){
            $ac->setNested("$team.X", $p->getFloorX());
            $ac->setNested("$team.Y", $p->getFloorY());
            $ac->setNested("$team.Z", $p->getFloorZ());
            $ac->save();
            $p->sendMessage("§8» §a$team has been successfully updated!");
          }else{
            $p->sendMessage("§8» §cAll the teams are settled, you can only change the teams!");
          }
        }else{
          $ac->setNested("$team.X", (int) $p->getFloorX());
          $ac->setNested("$team.Y", (int) $p->getFloorY());
          $ac->setNested("$team.Z", (int) $p->getFloorZ());
          $ac->save();
          $p->sendMessage($this->Teams()[$team]."$team 's spawn successfully placed!");
        }
      }else{
        $team = null;
        foreach ($this->Teams() as $team => $color){
          $team .= $color.$team." ";
        }
        $p->sendMessage("§8» §fTeams you can use: \n$team");
      }
    }else{
      $p->sendMessage("§8» §cThere is no such arena.");
    }
  }

  public function copy($source, $target){
    $directory = opendir($source);
    @mkdir($target);
    while (false !== ($file = readdir($directory))){
      if ($file != "." && $file != "..") {
        if (is_dir($source.'/'.$file)) {
          $this->copy($source.'/'.$file, $target.'/'.$file);
        } else {
          copy($source.'/'.$file, $target.'/'.$file);
        }
      }
    }
    closedir($directory);
  }

  public function MapReset($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml");
    $World = $ac->get("World");
    $level = Server::getInstance()->getLevelByName($World);
    if($level instanceof Level){
      Server::getInstance()->unloadLevel($level);
    }
    $this->copy($this->getDataFolder()."Back-Up/".$World, $this->getServer()->getDataPath()."worlds/".$World);
    Server::getInstance()->loadLevel($World);
  }

  public function ItemId(Player $p, $id){
    $items = 0;
    for($i=0; $i<36; $i++){
      $item = $p->getInventory()->getItem($i);
      if($item->getId() === $id){
        $items += $item->getCount();
      }
    }
    return $items;
  }

  public function Status($arena){
    $status = array();
    $plus = "§8[§a+§8]";
    $minus = "§8[§c-§8]";
    foreach($this->ArenaTeams($arena) as $at){
      if(!@in_array($at, $this->egg[$arena])){
        $status[] = $this->Teams()[$at].$at.$plus." ";
      }else{
        $status[] = $this->Teams()[$at].$at." ".$minus." ";
      }
    }
    return $status;
  }

  public function ArenaMessage($arena, $message){
    $players = $this->ArenaPlayer($arena);
    foreach($players as $player){
      $p = $this->getServer()->getPlayer($player);
      if($p instanceof Player){
        $p->sendMessage($message);
      }
    }
  }

  public function PlayerTeamColor(Player $p){
    $teamColor = substr($p->getNameTag(), 0, 3);
    if(strstr($teamColor, "§")){
      $Key = array_search($teamColor, $this->Teams());
      return $Key;
    }else{
      return false;
    }
  }

  public function AvailableTeams($arena){
    $players = $this->ArenaPlayer($arena);
    $teamNumber = 0;
    $cfg = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $musaitTeam = array();
    foreach($this->ArenaTeams($arena) as $team){
      foreach($players as $player){
        $p = $this->getServer()->getPlayer($player);
        if($p instanceof Player){
          if($this->PlayerTeamColor($p) === $team){
            $teamNumber++;
          }
        }
      }

      if($teamNumber < $cfg->get("PlayersPerTeam")){
        $musaitTeam[] = $team;
      }
      $teamNumber = 0;
    }

    return $musaitTeam;
  }

  public function AvailableRastTeam($arena){
    $mt = $this->AvailableTeams($arena);
    $mixed = array_rand($mt);
    return $this->Teams()[$mt[$mixed]];
  }

  public function TeamSellector($arena, Player $p){
    foreach($this->ArenaTeams($arena) as $at){
      $meta = $this->TeamSearcher()[$at];
      $color = $this->Teams()[$at];
      $item = Item::get(35);
      $item->setDamage($meta);
      $item->setCustomName("§r§8» ".$color.$at."§8 «");
      $p->getInventory()->addItem($item);
    }
    $p->getInventory()->sendContents($p);
  }

  public function EggSkin($arena, $team){
    if(empty($this->egg[$arena])){
      return false;
    }else{
      if(@in_array($team, $this->egg[$arena])){
        return true;
      }else{
        return false;
      }
    }
  }

  public function ArenaRefresh($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);
    $Lobby = Server::getInstance()->getLevelByName($ac->getNested("Lobby.World"));
    if(!$Lobby instanceof Level){
      Server::getInstance()->loadLevel($ac->getNested("Lobby.World"));
    }
    $this->status[$arena] = "Lobby";
    $this->StartTime[$arena] = (int) $cfg->get("StartTime");
    $this->EndTime[$arena] = (int) $cfg->get("EndTime");
    $this->players[$arena] = array();
    unset($this->egg[$arena]);
    $this->MapReset($arena);
  }

  public function OneTeamRemained($arena){
    $players = $this->ArenaPlayer($arena);
    $teams = array();
    foreach ($players as $pl){
      $p = Server::getInstance()->getPlayer($pl);
      if($p instanceof Player){
        $team = $this->PlayerTeamColor($p);
        if(!in_array($team, $teams)){
          $teams[] = $team;
        }
      }
    }
    if(count($teams) === 1){
      return true;
    }else{
      return false;
    }
  }

  public function EmptyShop(Player $p){
    $p->getLevel()->setBlock(new Vector3($p->getFloorX(), $p->getFloorY() - 4, $p->getFloorZ()), Block::get(Block::CHEST));
    $nbt = new CompoundTag("", [
      new ListTag("Items", []),
      new StringTag("id", Tile::CHEST),
      new IntTag("x", $p->getFloorX()),
      new IntTag("y", $p->getFloorY() - 4),
      new IntTag("z", $p->getFloorZ()),
      new StringTag("CustomName", "§6EggWars Shop")
    ]);
    $nbt->Items->setTagType(NBT::TAG_Compound);
    $tile = Tile::createTile("Chest", $p->getLevel(), $nbt);
    if($tile instanceof Chest) {
      $config = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
      $shop = $config->get("shop");
      $tile->setName("§6EggWars Shop");
      $tile->getInventory()->clearAll();
      for ($i = 0; $i < count($shop); $i+=2) {
        $slot = $i / 2;
        $tile->getInventory()->setItem($slot, Item::get($shop[$i], 0, 1));
      }
      $tile->getInventory()->setItem($tile->getInventory()->getSize()-1, Item::get(Item::WOOL, 14, 1));
      $p->addWindow($tile->getInventory());
    }
  }

  public static function CreateLightning($x, $y, $z, $level){
    $pk = new AddEntityPacket();
    $pk->metadata = array();
    $pk->type = 93;
    $pk->eid = Entity::$entityCount++;
  	$pk->entityRuntimeId = Entity::$entityCount++;
    $pk->speedX = 0;
    $pk->speedY = 0;
    $pk->speedZ = 0;
    $pk->x = $x;
    $pk->y = $y;
    $pk->z = $z;
    foreach($level->getPlayers() as $pl){
      $pl->dataPacket($pk);
    }
  }
}
