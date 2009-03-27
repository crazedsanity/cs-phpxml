<?php
/*
 * Created on Sept. 11, 2007
 * 
 * 
 * SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 * 
 */

require_once(dirname(__FILE__) .'/../cs-arrayToPath/cs_arrayToPath.class.php');
require_once(dirname(__FILE__) .'/../cs-versionparse/cs_version.abstract.class.php');

abstract class cs_phpxmlAbstract extends cs_versionAbstract {
	
	public $isTest = FALSE;
	protected $a2p;
	
	//=========================================================================
	public function __construct(array $data=null) {
		$this->set_version_file_location(dirname(__FILE__) . '/VERSION');
		if(!is_array($data)) {
			$data = array();
		}
		$this->a2p = new cs_arrayToPath($data);
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Returns a list delimited by the given delimiter.  Does the work of 
	 * checking if the given variable has data in it already, that needs to be 
	 * added to, vs. setting the variable with the new content.
	 */
	final protected function create_list($string = NULL, $addThis = NULL, $delimiter = ", ") {
		if($string) {
			$retVal = $string . $delimiter . $addThis;
		}
		else {
			$retVal = $addThis;
		}

		return ($retVal);
	} //end create_list()
	//=========================================================================
	
	
	
	//=========================================================================
	final protected function explode_path($path) {
		//make sure it has a leading slash.
		$path = preg_replace('/^\//', '', $path);
		$path = '/'. $path;
		
		//explode the path on slashes (/)
		$pathArr = explode('/', $path);
		
		//now, remove the first element, 'cuz it's blank.
		array_shift($pathArr);
		
		return($pathArr);
	}//end explode_path()
	//=========================================================================
	
	
	
	//=========================================================================
	final protected function path_from_array(array $bits, $addSlashPrefix=true) {
		$retval = "";
		if(!strlen($bits[0])) {
			array_shift($bits);
		}
		foreach($bits as $chunk) {
			$retval .= '/'. $chunk;
		}
		
		if($addSlashPrefix !== true) {
			$retval = preg_replace('/^\//', '', $retval);
		}
		
		return($retval);
	}//end path_from_array()
	//=========================================================================
	
	
	
}
?>
