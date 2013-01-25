<?php
namespace Library\ShapeDB;

class ShapeBase {
	const SHOW_ERRORS = true;
	const DEBUG = true;
	const XY_POINT_RECORD_LENGTH = 16;
	const ERROR_FILE_NOT_FOUND = "SHP File not found [%s]";
	const INEXISTENT_RECORD_CLASS = "Unable to determine shape record type [%i]";
	const INEXISTENT_FUNCTION = "Unable to find reading function [%s]";
	const INEXISTENT_DBF_FILE = "Unable to open (read/write) SHP's DBF file [%s]";
	const INCORRECT_DBF_FILE = "Unable to read SHP's DBF file [%s]";
	const UNABLE_TO_WRITE_DBF_FILE = "Unable to write DBF file [%s]";
	protected $point_count = 0;
	
	protected function readRecordNull(&$fp, $read_shape_type = false,$options = null){
		$data = array();
		if($read_shape_type) $data += readShapeType($fp);
		//_d("Returning Null shp_data array = ".getArray($data));
		return $data;
	}
	protected function readRecordPoint(&$fp, $create_object = false,$options = null){
		global $point_count;
		$data = array();
	
		$data["x"] = $this->readAndUnpack("d", fread($fp, 8));
		$data["y"] = $this->readAndUnpack("d", fread($fp, 8));
	
		////_d("Returning Point shp_data array = ".getArray($data));
		$point_count++;
		return $data;
	}
	
	protected function readRecordPointZ(&$fp, $create_object = false,$options = null){
		global $point_count;
		$data = array();
	
		$data["x"] = $this->readAndUnpack("d", fread($fp, 8));
		$data["y"] = $this->readAndUnpack("d", fread($fp, 8));
		// 	$data["z"] = readAndUnpack("d", fread($fp, 8));
		// 	$data["m"] = readAndUnpack("d", fread($fp, 8));
	
		////_d("Returning Point shp_data array = ".getArray($data));
		$point_count++;
		return $data;
	}
	
	protected function readRecordPointZSP($data, &$fp){
	
		$data["z"] = readAndUnpack("d", fread($fp, 8));
	
		return $data;
	}
	
	protected function readRecordPointMSP($data, &$fp){
	
		$data["m"] = readAndUnpack("d", fread($fp, 8));
	
		return $data;
	}
	
	protected function readRecordMultiPoint(&$fp,$options = null){
		$data = readBoundingBox($fp);
		$data["numpoints"] = readAndUnpack("i", fread($fp, 4));
		//_d("MultiPoint numpoints = ".$data["numpoints"]);
	
		for($i = 0; $i <= $data["numpoints"]; $i++){
			$data["points"][] = readRecordPoint($fp);
		}
	
		//_d("Returning MultiPoint shp_data array = ".getArray($data));
		return $data;
	}
	
	protected function readRecordPolyLine(&$fp,$options = null){
		$data = $this->readBoundingBox($fp);
		$data["numparts"]  = $this->readAndUnpack("i", fread($fp, 4));
		$data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));
	
		//_d("PolyLine numparts = ".$data["numparts"]." numpoints = ".$data["numpoints"]);
		if (isset($options['noparts']) && $options['noparts']==true) {
			//Skip the parts
			$points_initial_index = ftell($fp)+4*$data["numparts"];
			$points_read = $data["numpoints"];
		}
		else{
			for($i=0; $i<$data["numparts"]; $i++){
				$data["parts"][$i] = $this->readAndUnpack("i", fread($fp, 4));
				//_d("PolyLine adding point index= ".$data["parts"][$i]);
			}
	
			$points_initial_index = ftell($fp);
	
			//_d("Reading points; initial index = $points_initial_index");
			$points_read = 0;
			foreach($data["parts"] as $part_index => $point_index){
				//fseek($fp, $points_initial_index + $point_index);
				//_d("Seeking initial index point [".($points_initial_index + $point_index)."]");
				if(!isset($data["parts"][$part_index]["points"]) || !is_array($data["parts"][$part_index]["points"])){
					$data["parts"][$part_index] = array();
					$data["parts"][$part_index]["points"] = array();
				}
				while( ! in_array( $points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)){
					$data["parts"][$part_index]["points"][] = $this->readRecordPoint($fp, true);
					$points_read++;
				}
			}
		}
	
		fseek($fp, $points_initial_index + ($points_read * self::XY_POINT_RECORD_LENGTH));
	
		//_d("Seeking end of points section [".($points_initial_index + ($points_read * XY_POINT_RECORD_LENGTH))."]");
		return $data;
	}
	
	protected function readRecordMultiPointZ(&$fp,$options = null){
		$data = readBoundingBox($fp);
		$data["numparts"]  = readAndUnpack("i", fread($fp, 4));
		$data["numpoints"] = readAndUnpack("i", fread($fp, 4));
		// 	$fileX = 40 + (16*$data["numpoints"]);
		// 	$fileY = $fileX + 16 + (8*$data["numpoints"]);
		$fileX = 44 + (4*$data["numparts"]);
		$fileY = $fileX + (16*$data["numpoints"]);
		$fileZ = $fileY + 16 + (8*$data["numpoints"]);
		/*
		 Note: X = 44 + (4 * NumParts), Y = X + (16 * NumPoints), Z = Y + 16 + (8 * NumPoints)
		*/
	
		//_d("PolyLine numparts = ".$data["numparts"]." numpoints = ".$data["numpoints"]);
		if (isset($options['noparts']) && $options['noparts']==true) {
			//Skip the parts
			$points_initial_index = ftell($fp)+4*$data["numparts"];
			$points_read = $data["numpoints"];
		}
		else{
			for($i=0; $i<$data["numparts"]; $i++){
				$data["parts"][$i] = readAndUnpack("i", fread($fp, 4));
				//_d("PolyLine adding point index= ".$data["parts"][$i]);
			}
			$points_initial_index = ftell($fp);
	
			//_d("Reading points; initial index = $points_initial_index");
			$points_read = 0;
			foreach($data["parts"] as $part_index => $point_index){
				//fseek($fp, $points_initial_index + $point_index);
				//_d("Seeking initial index point [".($points_initial_index + $point_index)."]");
				if(!isset($data["parts"][$part_index]["points"]) || !is_array($data["parts"][$part_index]["points"])){
					$data["parts"][$part_index] = array();
					$data["parts"][$part_index]["points"] = array();
				}
				while( ! in_array( $points_read, $data["parts"]) && $points_read < $data["numpoints"]/* && !feof($fp)*/){
					$data["parts"][$part_index]["points"][] = readRecordPoint($fp, true);
					$points_read++;
				}
			}
	
			$data['Zmin'] = $this->readAndUnpack("d", fread($fp, 8));
			$data['Zmax'] = $this->readAndUnpack("d", fread($fp, 8));
	
			foreach($data["parts"] as $part_index => $point_index){
				foreach($point_index["points"] as $n => $p){
					$data["parts"][$part_index]['points'][$n] = $this->readRecordPointZSP($p, $fp, true);
				}
			}
	
			$data['Mmin'] = $this->readAndUnpack("d", fread($fp, 8));
			$data['Mmax'] = $this->readAndUnpack("d", fread($fp, 8));
	
			foreach($data["parts"] as $part_index => $point_index){
				foreach($point_index["points"] as $n => $p){
					$data["parts"][$part_index]['points'][$n] = readRecordPointMSP($p, $fp, true);
				}
			}
		}
	
		fseek($fp, $points_initial_index + ($points_read * XY_POINT_RECORD_LENGTH));
	
		//_d("Seeking end of points section [".($points_initial_index + ($points_read * XY_POINT_RECORD_LENGTH))."]");
		return $data;
	}
	
	protected function readRecordPolygon(&$fp,$options = null){
		//_d("Polygon reading; applying readRecordPolyLine function");
		return $this->readRecordPolyLine($fp,$options);
	}
	
	/**
	 * General functions
	 */
	protected function processDBFFileName($dbf_filename){
		//_d("Received filename [$dbf_filename]");
		if(!strstr($dbf_filename, ".")){
			$dbf_filename .= ".dbf";
		}
	
		if(substr($dbf_filename, strlen($dbf_filename)-3, 3) != "dbf"){
			$dbf_filename = substr($dbf_filename, 0, strlen($dbf_filename)-3)."dbf";
		}
		//_d("Ended up like [$dbf_filename]");
		return $dbf_filename;
	}
	
	protected function readBoundingBox(&$fp){
		$data = array();
		$data["xmin"] = $this->readAndUnpack("d",fread($fp, 8));
		$data["ymin"] = $this->readAndUnpack("d",fread($fp, 8));
		$data["xmax"] = $this->readAndUnpack("d",fread($fp, 8));
		$data["ymax"] = $this->readAndUnpack("d",fread($fp, 8));
	
		//_d("Bounding box read: miX=".$data["xmin"]." miY=".$data["ymin"]." maX=".$data["xmax"]." maY=".$data["ymax"]);
		return $data;
	}
	
	protected function readAndUnpack($type, $data){
		if(!$data) return $data;
		return current(unpack($type, $data));
	}
	
	protected function _d($debug_text){
		if(DEBUG){
			echo $debug_text."\n";
		}
	}
	
	protected function getArray($array){
		ob_start();
		print_r($array);
		$contents = ob_get_contents();
		ob_get_clean();
		return $contents;
	}
}