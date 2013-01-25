<?php
namespace Library\ShapeDB;

/**
 * ShapeRecord
 *
 */
class ShapeRecord extends ShapeBase {
	private $fp;
	private $fpos = null;

	private $dbf = null;

	private $record_number     = null;
	private $content_length    = null;
	private $record_shape_type = null;

	private $error_message     = "";

	private $shp_data = array();
	private $dbf_data = array();

	private $file_name = "";

	private $record_class = array(  0 => "RecordNull",
		1 => "RecordPoint",
		8 => "RecordMultiPoint",
		3 => "RecordPolyLine",
		5 => "RecordPolygon",
		13 => "RecordMultiPointZ",
		11 => "RecordPointZ");

	function __construct(&$fp, $file_name,$options){
		$this->fp = $fp;
		$this->fpos = ftell($fp);
		$this->options = $options;

		//_d("Shape record created at byte ".ftell($fp));

		if (feof($fp)) {
			echo "end ";
			exit;
		}
		$this->_readHeader();

		$this->file_name = $file_name;

	}

	public function getNextRecordPosition(){
		$nextRecordPosition = $this->fpos + ((4 + $this->content_length )* 2);
		return $nextRecordPosition;
	}

	private function _readHeader(){
		$this->record_number     = $this->readAndUnpack("N", fread($this->fp, 4));
		$this->content_length    = $this->readAndUnpack("N", fread($this->fp, 4));
		$this->record_shape_type = $this->readAndUnpack("i", fread($this->fp, 4));

		//_d("Shape Record ID=".$this->record_number." ContentLength=".$this->content_length." RecordShapeType=".$this->record_shape_type."\nEnding byte ".ftell($this->fp)."\n");
	}

	private function getRecordClass(){
		if(!isset($this->record_class[$this->record_shape_type])){
			//_d("Unable to find record class ($this->record_shape_type) [".getArray($this->record_class)."]");
			return $this->setError( sprintf(self::INEXISTENT_RECORD_CLASS, $this->record_shape_type) );
		}
		//_d("Returning record class ($this->record_shape_type) ".$this->record_class[$this->record_shape_type]);
		return $this->record_class[$this->record_shape_type];
	}
	
	public function getType() {
		return $this->record_shape_type;
	}
	
	public function getTypeName() {
		return $this->record_class[$this->record_shape_type];
	}

	private function setError($error){
		$this->error_message = $error;
		return false;
	}

	public function getError(){
		return $this->error_message;
	}

	public function getShpData(){
		$function_name = array(&$this, "read".$this->getRecordClass());
		//_d("Calling reading function [$function_name] starting at byte ".ftell($fp));

		if(is_callable($function_name)){
			$this->shp_data = call_user_func_array($function_name, array(&$this->fp, &$this->options));
		} else {
			$this->setError( sprintf(self::INEXISTENT_FUNCTION, $function_name) );
		}

		return $this->shp_data;
	}

	public function getDbfData(){

		$this->_fetchDBFInformation();

		return $this->dbf_data;
	}

	private function _openDBFFile($check_writeable = false){
		$check_function = $check_writeable ? "is_writable" : "is_readable";
		if($check_function($this->file_name)){
			$this->dbf = dbase_open($this->file_name, ($check_writeable ? 2 : 0));
			if(!$this->dbf){
				$this->setError( sprintf(INCORRECT_DBF_FILE, $this->file_name) );
			}
		} else {
			$this->setError( sprintf(INEXISTENT_DBF_FILE, $this->file_name) );
		}
	}

	private function _closeDBFFile(){
		if($this->dbf){
			dbase_close($this->dbf);
			$this->dbf = null;
		}
	}

	private function _fetchDBFInformation(){
		$this->_openDBFFile();
		if($this->dbf) {
			//En este punto salta un error si el registro 0 está vacio.
			//Ignoramos los errores, ja que aún así todo funciona perfecto.
			$this->dbf_data = @dbase_get_record_with_names($this->dbf, $this->record_number);
		} else {
			$this->setError( sprintf(INCORRECT_DBF_FILE, $this->file_name) );
		}
		$this->_closeDBFFile();
	}

	public function setDBFInformation($row_array){
		$this->_openDBFFile(true);
		if($this->dbf) {
			unset($row_array["deleted"]);

			if(!dbase_replace_record($this->dbf, array_values($row_array), $this->record_number)){
				$this->setError( sprintf(UNABLE_TO_WRITE_DBF_FILE, $this->file_name) );
			} else {
				$this->dbf_data = $row_array;
			}
		} else {
			$this->setError( sprintf(INCORRECT_DBF_FILE, $this->file_name) );
		}
		$this->_closeDBFFile();
	}
}