<?php
namespace Library\ShapeDB;

class ShapeFile extends ShapeBase {
	private $file_name;
	private $fp;
	//Used to fasten up the search between records;
	private $dbf_filename = null;
	//Starting position is 100 for the records
	private $fpos = 100;

	private $error_message = "";
	private $show_errors   = self::SHOW_ERRORS;

	private $shp_bounding_box = array();
	private $shp_type         = 0;

	private $records;
	
	
	function __construct($file_name,$options){

		$this->options = $options;

		$this->file_name = $file_name;
		//_d("Opening [$file_name]");
		if(!is_readable($file_name)){
			return $this->setError( sprintf(self::ERROR_FILE_NOT_FOUND, $file_name) );
		}

		$this->fp = fopen($this->file_name, "rb");

		$this->_fetchShpBasicConfiguration();

		//Set the dbf filename
		$this->dbf_filename = $this->processDBFFileName($this->file_name);

	}


	public function getError(){
		return $this->error_message;
	}


	function __destruct()
	{
		$this->closeFile();
	}

	// Data fetchers
	private function _fetchShpBasicConfiguration(){
		//_d("Reading basic information");
		fseek($this->fp, 32, SEEK_SET);
		$this->shp_type = $this->readAndUnpack("i", fread($this->fp, 4));
		//_d("SHP type detected: ".$this->shp_type);

		$this->shp_bounding_box = $this->readBoundingBox($this->fp);
		////_d("SHP bounding box detected: miX=".$this->shp_bounding_box["xmin"]." miY=".$this->shp_bounding_box["ymin"]." maX=".$this->shp_bounding_box["xmax"]." maY=".$this->shp_bounding_box["ymax"]);
	}



	public function getNext(){
		if (!feof($this->fp)) {
			fseek($this->fp, $this->fpos);
			$shp_record = new ShapeRecord($this->fp, $this->dbf_filename,$this->options);
			if($shp_record->getError() != ""){
				return false;
			}
			$this->fpos = $shp_record->getNextRecordPosition();
			return $shp_record;
		}
		return false;
	}

	/*Alpha, not working
	 public function _resetFileReading(){
	rewind($this->fp);
	$this->fpos = 0;

	$this->_fetchShpBasicConfiguration();
	}*/

	/* Takes too much memory
	 function _fetchRecords(){
	fseek($this->fp, 100);
	while(!feof($this->fp)){
	$shp_record = new ShapeRecord($this->fp, $this->file_name);
	if($shp_record->error_message != ""){
	return false;
	}
	$this->records[] = $shp_record;
	}
	}
	*/

	//Not Used
	/*	private function getDBFHeader(){
		$dbf_data = array();
	if(is_readable($dbf_data)){
	$dbf = dbase_open($this->dbf_filename , 1);
	// solo en PHP5 $dbf_data = dbase_get_header_info($dbf);
	echo dbase_get_header_info($dbf);
	}
	}
	*/

	// General functions
	private function setError($error){
		$this->error_message = $error;
		if($this->show_errors){
			echo $error."\n";
		}
		return false;
	}

	private function closeFile(){
		if($this->fp){
			fclose($this->fp);
		}
	}


}