<?php
class SDConfig {

	var $instance = null;

	var $valid = array(
		"tmp_path" => "text,tmp/",
		"quality_jpeg" => "range,0,100,75",
		"quality_png" => "range,0,9,7",
		"use_watermark" => "bool",
		"crop_images" => "bool",
		"crop_tofit" => "bool",
		"crop_border" => "range,0,500,15",
		"image_scaletofit" => "bool",
		"watermark_source" => "options,file,text,file",
		"watermark_text" => "text,Copyright 2009",
		"watermark_font_size" => "range,4,72,12",
		"watermark_font" => "text,font/Amethysta-Regular.ttf",
		"watermark_file" => "text,watermark.png",
		"watermark_position" => "options,top-left,top-right,center,bottom-left,bottom-right,top-right",
		"caption_alpha" => "range,0,100,20",
		"caption_fontsize" => "range,4,72,12",
		"caption_font" => "text,font/Amethysta-Regular.ttf",
		"caption_padding" => "range,0,50,5",
		"caption_align" => "options,left,right,center,center",
		"caption_lineheight", "range,0,50,5",
		"caption_fontcolor", "hex,0x000000",
		"caption_bgcolor", "hex,0xffffff"
	);
	//create a hash value of all configuration values. Used to let the cache know that details have changed
	var $config_hash = "";

	function SDConfig(){
		//import the verification file too
		if(file_exists(dirname(__FILE__)."/config.ini")) {
			foreach(parse_ini_file(dirname(__FILE__)."/config.ini") as $k=>$v){
				$var = "$k";
				$this->$var = $v;
				$this->config_hash.=var_export($v, true);
			}
		} else {
			die("Cannot load configuration");	
		}
		
		//overwrite any config values with $_GET variables direct from URL
		foreach($_GET as $k => $v){
			if(key_exists($k, $this->valid)){
				$v = strtolower($v);
				if($v=="1" || $v=="yes" || $v=="true") {$v = true;}
				else if($v=="0" || $v=="no" || $v=="false") {$v = false;}
				$this->$k = $v;
			}
		}
	
		//verify our values, and fix them if necessary
		$this->verify();
		//generate a hash of config values to store in cache filename
		//(so we know when a change has occurred in the config and can update
		//cache accordingly)

		$this->config_hash = md5($this->config_hash);
	}
	
	function getFont($font){
		if(key_exists($font, $this->fonts)){
			return $this->fonts[$font];	
		} else {
			return $this->fonts["Amethysta-Regular"];
		}
	}
	
	//do internal variable verification
	function verify(){
		//todo: implement checking to make sure values are in range
		/*foreach($this->valid as $k => $v){
			$split = split(",", $v);
			$value = &$this->$k;
			$default = null;
			$valid = false;
			switch($split[0]){
				case "range":
					$default = $split[3];
					$value = (int)$value;
					$valid = filter_var($value, FILTER_VALIDATE_INT, array("options" => array("min_range" => $split[1], "max_range" => $split[2])));
					$valid = $valid===false?false:true;
					break;
				case "options":
					$default = array_pop($split);
					foreach($split as $k => $s){
						if($k>0){
							if(strtolower($value)==$s)
								$valid = true;
						}
					}
					break;
				case "text":
					if(strlen($value)>1)
						$valid = true;
					$default = $split[1];
					break;
				case "hex":
					//this isn't right. Fix it!
					$valid = filter_var($value, FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/\\0\x[0-9a-fA-F]{6}/")));
					$default = $split[1];
					break;
				case "bool":
					$default = false;
					if( ($value==false) || ($value==true) )
						$valid = true;
					break;
					
				default:
				
					break;					
			}
	
			if(!$valid){
				$value = $default;	
			}

		}*/

	}
	
	function get($key = null){
		return $this->$key;	
	}	
}
?>