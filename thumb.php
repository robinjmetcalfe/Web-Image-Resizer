<?php
ini_set("display_errors", 0);
error_reporting(E_NONE);	
ini_set("memory_limit", "128M");
require_once("class.image.php");
$image = new SDImageManager;
$image->im_display_cache();
?>