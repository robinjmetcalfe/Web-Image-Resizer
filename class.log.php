<?php
class SDLog {
	
	var $start_time = 0;
	var $marks = array();
	var $log_file = null;
	var $last_time = 0;
	
	function SDLog($file = null){
		$this->start_time = microtime(true);
		$this->log_file = $file;
		register_shutdown_function(array($this, "report"));
	}
	
	function __destruct(){


	}
	
	function step($label = ""){
		$this->marks[] = array("label" => $label, "time" => sprintf("[+%2.7fs :: %2.7fs]", (microtime(true) - $this->start_time) - $this->last_time, microtime(true) - $this->start_time));
		$this->last_time = microtime(true) - $this->start_time;
	}
	
	function report(){
		ob_start();
		foreach($this->marks as $m){
			echo $m["time"]." ".$m["label"]."\n";
		}
		$contents = ob_get_contents();
		ob_end_clean();
		if($this->log_file){
			if($fp = fopen($this->log_file, "w")){
				fwrite($fp, $contents); 	
				fclose($fp);
			} else {
				echo "Cannot write to file";	
			}
		} else {
			return $contents;
		}
	}
}


?>