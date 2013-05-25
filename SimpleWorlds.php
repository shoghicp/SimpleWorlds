<?php

/*
__PocketMine Plugin__
name=SimpleWorlds
description=Simple world loader & generator
version=0.1
author=shoghicp
class=SimpleWorlds
apiversion=7
*/

/* 
Small Changelog
===============

0.1
- Initial release

0.2
- Unload support

*/



class SimpleWorlds implements Plugin{
	private $api, $config;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->console->register("simpleworlds", "load/unload <level> OR /simpleworlds generate <seed> <generator> <level>", array($this, "command"));
		$this->api->console->alias("sw", "simpleworlds");
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
				$output .= "Usage: /$cmd load <level> OR /simpleworlds generate <seed> <generator> <level>\n";
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