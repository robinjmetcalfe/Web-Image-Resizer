<?php
/**
 * SDImageManager - Image manipulation class for PHP5
 * Requires GD2 library
 * 
 * Author: Robin Metcalfe
 * Website: http://www.solarisedesign.co.uk
 * 
 * License: Free to use and distribute for non-commercial usage. For commercial use, please contact at robin@solarisedesign.co.uk
 * 
 */
require_once("class.config.php");
require_once("class.log.php");

class SDImageManager {

	//declare public
	var $config;
	var $log;

	//declare private
	private $_root;
	private $_base;
	
	private $_new_width;
	private $_new_height;
	private $_imagefile; 
	private $_cachefile;
	
	private $_captionHeight = 0;	//used to determine where the watermark should go. Stores the height of the caption box if it's been generated
	
	//just for resource management purposes, set an upper limit on image size
	private $_max_width = 1600;
	private $_max_height = 1200;
	
	//max image size = 1MB, can be changed in config
	private $_max_filesize = 1048576;
	
	//is this a local image, or one referenced by "http://"?
	private $_isLocalImage;
	
	private $_doResize = true; //do we need to resize? Used if someone doesn't include the size parameter, and we're just processing/adding caption/watermark
	
	private $_ext; //the file xtension
	private $_mimetype; //the file mimetype
	
	private $_tmp;
	
	private $_debug = true;
	
	private $_mimetypes = array(
		"image/jpeg" => "jpg",
		"image/pjpeg" => "jpg",
		"image/jpg" => "jpg",
		"image/pjpg" => "jpg",
		"image/gif" => "gif",	
		"image/pgif" => "gif",
		"image/png" => "png",
		"image/ppng" => "png",
		"image/pbmp" => "bmp",
		"image/bmp" => "bmp"		
	);
	
	/**
	 * SDImageManager::SDImageManager()
	 * 
	 * @return
	 */
	function SDImageManager(){
		$this->config = new SDConfig;
		//set the base directory we're operating from
		$this->_base = dirname(__FILE__);
		$this->_set_root();
		$this->_tmp = $this->_base."/".$this->config->get("tmp_path");
		if(!file_exists($this->_tmp) || filetype($this->_tmp)!="dir"){
			$this->_imageError("Could not initialise tmp directory\n".$this->_tmp);
		}
		if(!is_writable($this->_tmp)){
			$this->_imageError("Temporary cache directory is not writable");
		}
	}
	
	//public methods
	/**
	 * SDImageManager::printDetails()
	 * 
	 * @return
	 */
	function printDetails(){
		$this->_debug($this->_cachefile);
		$this->_debug($this->_ext);
		$this->_debug($this->_mimetype);
		$this->_debug($this->_imagefile);
		$this->_debug($this->_new_width);
		$this->_debug($this->_new_height);
	}
	
	/**
	 * SDImageManager::clear_cache()
	 * USE WITH CAUTION
	 * @param integer $older
	 * @return
	 */
	function clear_cache($older = 0){
		//unlink all files within the tmp directoy
		if(chdir($this->_tmp)){
			foreach(glob("*") as $file){
				unlink($file);	
			}
		}
		
	}
	
	function get_static_link(){
		//todo: implement this. Allow for returning a static link rather than processing each time
		//is this possible?
	}
	
	/**
	 * SDImageManager::im_display_cache()
	 * 
	 * @param mixed $image
	 * @param string $size
	 * @param bool $html
	 * @return
	 */
	function im_display_cache(){
		//set up the variables from the $_GET parameters
		//sets image size and image file
		$this->_setVars();
		$this->_setLog();
		$this->log->step("Log setup and going...");
		//check the image extension and filetype. set mimetype and ext accordingly
		$this->_setExtension();
		$this->log->step("Extension checked and parsed");
		//set up the cache filename for this image/config and save string in cachefile
		$this->_setCacheFilename();
		$this->log->step("Filename generated now");
		//send the appropriate headers to display this image
		$this->_sendHeaders();
		$this->log->step("Headers sent");
		//(if not disabled) check the cache to see if this image has already been
		//generated. If so, output from the cache (file located at _cachefile) and exit...
		if($this->config->get("disable_cache") == 0){
			$this->log->step("Image output from cache in 3, 2, 1...");
			$this->_checkCache();
		}
		//...otherwise, create the image and save it to cache (save to file _cachefile)
		$this->log->step("Image generation in 3, 2, 1...");
		$this->_generateImage();
	}
		
	//private methods
	
	
	//add a caption to the image, supports multiple lines
	private function _applyCaption(&$image){
		if(isset($_GET['caption'])){
			$caption = $_GET['caption'];
			
			$w = $this->_new_width;
			$h = $this->_new_height;
			// Allocate colors
			$white = imagecolorallocatealpha($image, 255, 255, 255, $this->config->get("caption_alpha"));
			$grey = imagecolorallocate($image, 128, 128, 128);
			$black = imagecolorallocate($image, 0, 0, 0);
			
			// Load the TrueType Font
			$font = $this->_base."/".$this->config->get("caption_font");
			$font_size = $this->config->get("caption_fontsize");
			$line_height = $this->config->get("caption_lineheight");
			$padding =  $this->config->get("caption_padding");
			$words = split(" ", $caption);

			$line_width = 0;
			$line = 0;
			$new_caption = array();
			$new_caption_str = "";

			foreach($words as $word){
				if(!isset($new_caption[$line]))
			 		$new_caption[$line] = "";
	 			
				$box = imagettfbbox($font_size, 0, $font, $new_caption[$line].$word." ");
				$width = $box[4] - $box[6];
				if($width > $this->_new_width - $padding*2){
					$line++;
					$new_caption[$line] = "";
				}
				$new_caption[$line].= $word." ";
			}
			$new_caption = array_reverse($new_caption);
			$this->_captionHeight = ($font_size+$line_height)*sizeof($new_caption)+$padding*2+$font_size/3.5-$line_height;
			imagefilledrectangle($image, 0, $h-$this->_captionHeight , $w-1, $h-1, $white);
			foreach($new_caption as $k => $text){
				imagettftext($image, $font_size, 0, $padding , $h - ($k*($font_size+$line_height)) - $font_size/3.5 - $padding, $black, $font, $text);
			}
		}		
	}
	
	private function _applyWatermark(&$image){
		//apply the watermark image specified in config to the image
		if($this->config->get("use_watermark") == true){
			$source = $this->config->get("watermark_source");
			if($source=="file"){
				$w_file = $this->config->get("watermark_file");
				if(file_exists($w_file)){
					//apply watermark to image resouce $image
					$file = $this->_getAbsPath($w_file);
					$size = getimagesize($file);
					$width = $size[0];
					$height = $size[1];
					$watermark = imagecreatefrompng($file);
					imagealphablending($image, true);
					$watermark_offset = 6;
					switch($this->config->watermark_position){
						case "center":
							$dst_x = $this->_new_width/2 - $width/2;
							$dst_y = $this->_new_height/2 - $height/2 - $this->_captionHeight/2;
							break;
						case "top-left":
							$dst_x = $watermark_offset;
							$dst_y = $watermark_offset;
							break;
						case "top-right":
							$dst_x = $this->_new_width - $width - $watermark_offset;
							$dst_y = $watermark_offset;
							break;
						case "bottom-left":
							$dst_x = $watermark_offset;
							$dst_y = $this->_new_height - $height - $this->_captionHeight - $watermark_offset;
							break;
						case "bottom-right":
							$dst_x = $this->_new_width - $width - $watermark_offset;
							$dst_y = $this->_new_height - $height - $this->_captionHeight - $watermark_offset;
							break;
						default:
						
							break;
					}
					
					if(!imagecopyresampled($image, $watermark, $dst_x, $dst_y, 0, 0, $width, $height, $width, $height)) {
						$this->_imageError("Could not apply watermark");
					}
						
					imagealphablending($image, false);
				}
			} else {
				//nothing yet, eventually allow watermarks to be defined as text
				$this->_imageError("text based watermarks not yet implemented");
			}
		}
	}
	
	/* apply all the effects to the image */
	private function _applyEffects(&$image){
		$this->_applyCaption($image);
		$this->_applyWatermark($image);		
	}
	
	
	/**
	 * SDImageManager::_debug()
	 * 
	 * @param mixed $str
	 * @return
	 */
	private function _debug($str){
		if($this->_debug){
			echo "<pre>";
			var_dump($str);
			echo "</pre>";
		}
	}
	
	/**
	 * SDImageManager::_checkCache()
	 * 
	 * @return
	 */
	private function _checkCache(){

		//check if the file exists in the cache already. If so? Show info from that!
		if(file_exists($this->_cachefile)){
			echo file_get_contents($this->_cachefile);
			$this->log->step("Image output from cache - done");
			$this->_exit();			
		}
	}

	/**
	 * SDImageManager::_checkImage()
	 * 
	 * @return
	 */
	private function _checkImage(){
		//check the image for consistency and existance
		if($this->_new_width <= 0 || $this->_new_height <= 0){
			$this->_imageError("Image dimensions are outside valid range");
		}	
		
		if($this->_isLocalImage){
			if(!file_exists($this->_imagefile)){
				$this->_imageError("Image\n {$this->_imagefile} doesn't exist");	
			}
		} else {
			if (!fopen($this->_imagefile, "r")) {
				$this->_imageError("Image\n {$this->_imagefile} doesn't exist");
			}
		}
	}

	//Elvis has left the building...
	private function _exit(){
		//do the report thing, if logging is enabled
		$this->log->report();
		exit(0);	
	}
	
	
	/**
	 * SDImageManager::_streamData() 
	 * 
	 * Need to be able to determine filetype of remote images.
	 * getfilesize downloads the whole file from the remote server (not good)
	 * Here we just stream bytes from the file until we manage to determine the 
	 * filetype of the image
	 * 
	 */
	private function _getMimeFromStreamData(){
		//todo: implement this, to allow for remote file fetching & caching
		return;
		$handle = fopen($this->_imagefile, "rb") or $this->_imageError("Invalid file stream.");
    	$new_block = null;
    	if(!feof($handle)) {
    		$new_block = fread($handle, 32);
    		$block_size = unpack("H*", $new_block[$i] . $new_block[$i+1]);
			$block_size = hexdec($block_size[1]);
    		while(!feof($handle)) {
    			$i += $block_size;
       			$new_block .= fread($handle, $block_size);
				print_r($new_block);
   			}
   		}

	}
	
	/**
	 * SDImageManager::_imageError()
	 * 
	 * @param mixed $error
	 * @return
	 */
	private function _imageError($error){
		$w = isset($this->_new_width)?$this->_new_width:320;
		$h = isset($this->_new_height)?$this->_new_height:240;
		$image = imagecreatetruecolor($w, $h);
		// Allocate colors
		$white = imagecolorallocate($image, 255, 255, 255);
		$grey = imagecolorallocate($image, 128, 128, 128);
		$black = imagecolorallocate($image, 0, 0, 0);
		imagefilledrectangle($image, 0, 0, $w-1, $h-1, $white);
		// Load the PostScript Font
		$font = $this->_base."/font/arial.ttf";
		// Write the font to the image
		imagettftext($image, 9, 0, 11, 21, $grey, $font, "Error: ".$error);
		// Output and free memory
		header('Content-type: image/jpeg');
		imagejpeg($image);
		imagedestroy($image);		
		$this->_exit();
	}
	

	/**
	 * SDImageManager::_setExtension()
	 * 
	 * @return
	 */
	private function _setExtension(){
		//NEW THING _ Stream the first few bytes of the file to grab the relevant file data from remote files
		//as otherwise will have to download whole file from server first of all
		if($this->_isLocalImage){
			//could use exif_ function, faster, but doesn't always seem to exist
			$info = getimagesize($this->_imagefile);
			$mimetype = $info["mime"];
		} else {
			//$mimetype = $this->_getMimeFromStreamData();
			//just parse the extension thing...	
			$mimetype = "image/jpeg";
		}
		if(key_exists($mimetype, $this->_mimetypes)){
			$this->_ext = $this->_mimetypes[$mimetype];
			$this->_mimetype = $mimetype;
		} else {
			$this->_imageError("Unrecognised Filetype : ".$mimetype);	
		}
	}
	
	/**
	 * SDImageManager::_set_root()
	 * 
	 * @return
	 */
	private function _set_root(){
		//set the root
		$filename = $_SERVER['SCRIPT_FILENAME'];
		$path = pathinfo($filename);
		$this->_root = $path["dirname"];
	}
	
	/**
	 * SDImageManager::_generateImage()
	 * 
	 * @return
	 */
	private function _generateImage(){
		$image = @$this->_imageCreate($this->_imagefile);
		$size = @getimagesize($this->_imagefile);
		//get new size
		if($this->_doResize===true){
			
			//just for ease of typing, allocate variables to address space of height and width
			//we will need to adjust these accordingly if config.crop_images = false
			$new_w = &$this->_new_width;
			$new_h = &$this->_new_height;
			$crop = $this->config->get("crop_images");
			$crop_tofit = $this->config->get("crop_tofit");
			$crop_border = $this->config->get("crop_border");
			$old_w = $size[0];
			$old_h = $size[1];
	

			$orig_new_w = $this->_new_width;
			$orig_new_h = $this->_new_height;

			$src_x = $src_y = $dst_x = $dst_y = 0;
		
			//if we're cropping the images
			//if($crop){
			$new_ratio = $new_w/$new_h;
			$old_ratio = $old_w/$old_h;
			
			
		 	if($crop){
				if($new_ratio>$old_ratio) {
					//old image is taller and narrower than new image space
					//keep the new width the same
					//alter the height to match
					$ratio = $old_ratio/$new_ratio;
					$src_y = ($old_h - $old_h*$ratio)/2;
					$old_h = $old_w/$new_ratio;
				} else {
					//old image is wider and shorter than new image space
					//keep the new height the same
					//alter the width to match
					$ratio = $new_ratio/$old_ratio;
					$src_x = ($old_w - $old_w*$ratio)/2;
					$old_w = $old_h*$new_ratio;					
				}
				
				$dst_w = $new_w;
				$dst_h = $new_h;
				
			} else {
				//don't crop. Adjust new_w and new_h accordingly
				if($new_ratio>$old_ratio){
					$new_w = $new_h * $old_ratio;					
				} else {
					$new_h = $new_w / $old_ratio;
				}
				
				//add white space around the image so it fits in dimensions
				if($crop_tofit){
					$dst_w = $new_w - $crop_border*2;
					$dst_h = $new_h - $crop_border*2;
					$new_w = $orig_new_w;
					$new_h = $orig_new_h;
					$dst_x = ($new_w - $dst_w) / 2;
					$dst_y = ($new_h - $dst_h) / 2;
				} else {
					$dst_w = $new_w;
					$dst_h = $new_h;	
				}
							
			}
			
			if($this->config->get("image_scaletofit") == false){
				if($new_w > $old_w){
					$ratio = $old_w/$new_w;
					$new_w = $old_w;
					$new_h = $new_h * $ratio;
				}
				
				if($new_h > $old_w){
					$ratio = $old_h/$new_h;
					$new_h = $old_h;
					$new_w = $new_w * $ratio;
				}
			}

			$image_dest = @imagecreatetruecolor($new_w, $new_h);
			imagefill($image_dest, 0, 0, 0xFFFFFF);
			@imagecopyresampled($image_dest, $image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $old_w, $old_h);
		} else {
			$image_dest = $image;	
		}
		//apply filters and effects
		$this->_applyEffects($image_dest);
		//if cache is enabled
		$this->_imageOutputByType($image_dest, $this->_cachefile);
		$this->_imageOutputByType($image_dest);

		@imagedestroy($image_dest);
		$this->log->step("Image generated");
	}


	private function _getAbsPath($file){
		$path = "";
		if(preg_match("#http(s)?://#", $file)){
			//check for domain name in $file;
			$host = $_SERVER['HTTP_HOST'];
			$url = parse_url($file);
			if(preg_match("#".$url['host']."#i", $host)){
				$path = $_SERVER['DOCUMENT_ROOT'].$url['path'];
				$this->_isLocalImage = false;
			} else {
				$this->_imageError("Cannot use remote files (yet)");	
			}
		} else {
			$this->_isLocalImage = true;
			$path = $this->_root."/".$file;
		}
		return $path;
	}
	
	
	private function _imageCreate($filename){
		$result = false;
		switch($this->_ext){
			case "jpg":
				$result = @imagecreatefromjpeg($filename);
				break;
			case "gif":
				$result = @imagecreatefromgif($filename);
				break;
			case "png":
				$result = @imagecreatefrompng($filename);
				break;
		}
		if($result===false){
			$this->_imageError("Could not create image resource");	
		}
		return $result;
	}

	private function _imageOutputByType(&$image, $file = null, $quality = 90, $filters = 0){
		$result = false;
		switch($this->_ext){
			case "jpg":
				$quality = $this->config->get("quality_jpeg");
				$result = @imagejpeg($image, $file, 100);
				break;
			case "gif":
				$result = @imagegif($image, $file);
				break;
			case "png":
				$quality = $this->config->get("quality_png");
				$result = @imagepng($image, $file, $quality, $filters);
				break;
			default:
				break;
		}
		if($result===false){
			$this->_imageError("Could not process image output");	
		}
		return $result;
	}
	
	/**
	 * SDImageManager::_setCacheFilename()
	 * 
	 * @return
	 */
	private function _setCacheFilename(){
		//determine the filename used to cache this file, based on the md5 of the file, and it's size
		//also include the config hash, to make each image unique to the specific configuration setup
		$this->_checkImage();
		$gethash = "";
		foreach($_GET as $g){
			//make a hash from the $_GET vars too, so we know if we need to update the cache
			$gethash .= $g;
		}
		$gethash = md5($gethash);		
		$this->_cachefile = $this->_tmp."/".
			$this->config->config_hash."_".
			$gethash."_".
			filesize($this->_imagefile).
			".".$this->_ext;

	}
	
	/**
	 * SDImageManager::_setAbsImageFile()
	 * 
	 * @param mixed $image
	 * @return
	 */
	private function _setAbsImageFile($image){
		//get the absolute system path to this image
		//check for "http://" at beginning of filename...
		
		//TO DO : Check to see if this remote URL matches the server
		//the script is running on. If yes, convert to an absolute local URL
		$this->_imagefile = $this->_getAbsPath($image);
		if(!file_exists($this->_imagefile)){
			$this->_imageError("Cannot locate image file {$this->_imagefile}");	
		}
	}
	
	
	/**
	 * SDImageManager::_sendHeaders()
	 * 
	 * @return
	 */
	private function _sendHeaders(){
		if(!headers_sent()){
			header("Content-Type: image/jpeg");
		}
	}
	
	
	/**
	 * SDImageManager::_setImageDimensions()
	 * 
	 * @param mixed $size
	 * @return
	 */
	private function _setImageDimensions($size){
		//parse size based on input text 123x456
		$size = explode("x", $size);
		if($size[0]==""){
			//get image dimensions from existing file, no need to resize
			$size = getimagesize($this->_imagefile);
			$this->_new_height = $size[1];
			$this->_new_width = $size[0];
			$this->_doResize = false;
		} else {
			if((int)$size[0] <= 0 || (int)$size[1] <= 0)
				$this->_imageError("Image dimensions are wrong"); 
			$this->_new_height = (int)$size[1]>$this->_max_height?$this->_max_height:(int)$size[1];
			$this->_new_width = (int)$size[0]>$this->_max_width?$this->_max_width:(int)$size[0];
		}
	}
	
	private function _setLog(){
		$this->log = new SDLog($this->_root."/tmp/log.txt");

	}
	
	private function _setVars(){
		$image = isset($_GET['image'])?$_GET['image']:false;
		if(!$image){
			$this->_imageError("Please use showimage.php?image=xyz.jpg");	
		}

		$size = isset($_GET['size'])?$_GET['size']:"";
		$this->_setAbsImageFile($image);
		$this->_setImageDimensions($size);
	}
	
}

?>