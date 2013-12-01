<?php

/*
__PocketMine Plugin__
name=SimpleWorlds
description=Simple world loader, generator, exporter
version=0.3
author=PocketMine Team
class=SimpleWorlds
apiversion=7,8,9,10
*/

/* 
Small Changelog
===============

0.1
- Initial release

0.2
- Unload support

0.3
- Export levels to MCPE format

*/



class SimpleWorlds implements Plugin{
	public static $chunkHeader = "base64://7c1Td6thEIbhado0TdIUSWbbtm3btm3btm3btm3btu37L+z1nn6z1nU4z60BIhoFKZEPldECfTEJy7EHF/EMv+ENEE2KXDwbnpZnpwl6YhwWYwfO4hG+I8wmmhDZUBoN0BWjbOb9+exswUncw2e4AkXjIhOKow46YhhmYwOO4lagef89O8FBojGRDoVRA20xCNOxBgdxDa9hs4tGRSq7eT8/O1XQEv0wGSuwF5fwHH/gCxZNhtyogKboFWzeH8/OEuzEOTzGD4Q7RBMhO8qgIbphNBZgK045zPv32fkCd4hoPGRGCdRFJwzHHGzEMdzGBzicorGc5v307BRBTbTDYMzAWhzCdbxBoEs0GlKjAKqilcu835+dKViJfbiMF/gLv1s0OfKgIpqhNyZgKXa5zfvn2XmCn4gIFU2MHCiLRuiOMViIbTiNB/iKUI95P75HNAtKoh46YwTmYhOO4w4+IiRMNDYyoChqhZn327MzBDOxDodxA28RFC4aHWlQENXQGgMwFavCzfv72bmCl5AIUUUK5EUlNEcfTMQy7MYFPMWvCPN+ZKRoEuREOTRGD4zFImzHGTzEN3i8ogmQFaW85v367HTBSMzDZpzAXXyC0ycaBxlRDLXRAUMxy2feX8/OEdzEO9j9ojGQFoVQHW0wENOwGgdwFa/8Yp111llnnXXWWfe/9w8=";
	private $api, $config;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		if(substr(self::$chunkHeader, 0, 9) === "base64://"){
			self::$chunkHeader = gzinflate(base64_decode(substr(self::$chunkHeader, 9)));
		}
	}
	
	public function init(){
		$this->api->console->register("simpleworlds", "<command> [parameters...]", array($this, "command"));
		$this->api->console->alias("sw", "simpleworlds");
		$this->api->console->alias("swe", "simpleworlds export");
		$this->api->console->alias("swu", "simpleworlds unload");
		$this->api->console->alias("swl", "simpleworlds load");
		$this->api->console->alias("swg", "simpleworlds generate");
		$this->config = new Config($this->api->plugin->configPath($this)."config.yml", CONFIG_YAML, array(
			"default-generator" => "SuperflatGenerator",
			"autogenerate" => false,
			"autoload" => array(),
		));
		console("[SimpleWorlds] Loading levels...");
		foreach($this->config->get("autoload") as $level){
			$this->loadLevel($level);		
		}
	}
	
	public function command($cmd, $params, $issuer, $alias){
		$output = "";
		if($cmd === "simpleworlds"){
			if(count($params) < 2){
				$output .= "Usage: /$cmd <command> [parameters...]\n";
				$output .= "/$cmd load <levelName>: Loads a level on file.\n";
				$output .= "/$cmd unload <levelName>: Safely unload a level.\n";
				$output .= "/$cmd export <levelName>: Exports a loaded level to MCPE format.\n";
				$output .= "/$cmd generate <seed> <generatorName> <levelName>: Generates a new world usign a generator with an specific seed.\n";
				return $output;
			}

			$subcmd = strtolower(array_shift($params));
			switch($subcmd){
				case "unload":
					$level = $this->api->level->get(implode(" ", $params));
					if($level instanceof Level){
						if($this->api->level->unloadLevel($level) === true){
							$output .= "Level unloaded.\n";
							break;
						}
					}
					$output .= "Error unloading level.\n";
					break;
				case "load":
					if($this->loadLevel(implode(" ", $params)) === false){
						$output .= "Error loading level.\n";
					}else{
						$output .= "Level loaded.\n";
					}
					break;
				case "export":
					$level = $this->api->level->get(implode(" ", $params));
					if($level instanceof Level){
						if($this->exportLevel($level) === false){
							$output .= "Error exporting level.\n";
						}else{
							$output .= "Level correctly exported.\n";
						}
					}else{
						$output .= "Level not loaded.\n";
					}
					break;
				case "generate":
					$seed = intval(array_shift($params));
					$generator = $params[0] === "default" ? false:$params[0];
					array_shift($params);
					if($this->generateLevel(implode(" ", $params), $seed, $generator) === false){
						$output .= "Error generating level.\n";
					}else{
						$output .= "Level generated.\n";
					}
					break;
			}
		}
		return $output;
	}
	
	public function exportLevel(Level $level){
		@mkdir($this->api->plugin->configPath($this)."export/");
		$path = $this->api->plugin->configPath($this)."export/".$level->getName()."/";
		@mkdir($path);
		$chunks = fopen($path."chunks.dat", "w");
		fwrite($chunks, self::$chunkHeader);		
		for($Z = 0; $Z < 16; ++$Z){
			for($X = 0; $X < 16; ++$X){
				$chunk = "";
				$miniChunks = array();
				for($Y = 0; $Y < 8; ++$Y){
					$miniChunks[$Y] = $level->getMiniChunk($X, $Y, $Z);
				}
				$columns = array();
				for($x = 0; $x < 16; ++$x){
					for($z = 0; $z < 16; ++$z){
						$index = ($x << 4) + $z;
						$j = ($z << 4) + $x;
						$columns[$index] = array("", "", str_repeat("\x00", 64), str_repeat("\x00", 64));
						foreach($miniChunks as $raw){
							$columns[$index][0] .= substr($raw, $j << 5, 16); //Block IDs
							$columns[$index][1] .= substr($raw, ($j << 5) + 16, 8); //Block Metadata
						}
					}
				}
				for($i = 0; $i < 4; ++$i){
					for($x = 0; $x < 16; ++$x){
						for($z = 0; $z < 16; ++$z){
							$chunk .= $columns[$x << 4 + $z][$i];
						}
					}
				}
				unset($columns, $miniChunks);
				$chunk = str_pad($chunk, 86012, "\x00", STR_PAD_RIGHT);
				fwrite($chunks, Utils::writeLInt(strlen($chunk)) . $chunk);
			}
		}
	}

	public function loadLevel($name){
		if(($ret = $this->api->level->loadLevel($name)) === false and $this->config->get("autogenerate") == true){
			$this->api->level->generateLevel($name, false, $this->config->get("default-generator"));
			$ret = $this->api->level->loadLevel($name);
		}
		return $ret;
	}
	
	public function generateLevel($name, $seed, $generator = false){
		if($this->api->level->levelExists($name)){
			return false;
		}
		
		if($generator === false){
			$generator = $this->config->get("default-generator");
		}
		
		return $this->api->level->generateLevel($name, $seed, $generator);
	}

	
	public function __destruct(){

	}

	
}
