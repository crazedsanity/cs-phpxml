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


abstract class cs_phpxmlAbstract extends cs_versionAbstract {
	
	public $isTest = FALSE;
	protected $a2p;
	protected $rootElement;
	protected $preserveCase=false;
	const dataIndex = '__data__';
	const attributeIndex = '__attribs__';
	
	//=========================================================================
	public function __construct(array $data=null) {
		$this->set_version_file_location(dirname(__FILE__) . '/VERSION');
		if(!is_array($data)) {
			$data = array();
		}
		$this->a2p = new cs_arrayToPath($data);
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Returns a list delimited by the given delimiter.  Does the work of 
	 * checking if the given variable has data in it already, that needs to be 
	 * added to, vs. setting the variable with the new content.
	 */
	final public function create_list($string = NULL, $addThis = NULL, $delimiter = ", ") {
		if($string) {
			$retVal = $string . $delimiter . $addThis;
		}
		else {
			$retVal = $addThis;
		}

		return ($retVal);
	} //end create_list()
	//=========================================================================
	
	
	
	//=================================================================================
	/**
	 * Takes an array created by explode_path() and reconstitutes it into a proper path.
	 */
	protected function reconstruct_path(array $pathArr) {
		//setup the path variable.
		$path = "";
		foreach($pathArr as $index=>$tagName) {
			//add this tag to the current path.
			$path = $this->create_list($path, $tagName, '/');
		}
		
		//add the leading '/'.
		$path = '/'. $path;
		
		//give 'em what they want.
		return($path);
	}//end reconstruct_path()
	//=================================================================================
	
	
	
	//=================================================================================
	protected function explode_path($path) {
		//make sure it has a leading slash.
		$path = preg_replace('/^\//', '', $path);
		$path = '/'. $path;
		
		//explode the path on slashes (/)
		$pathArr = explode('/', $path);
		
		//now, remove the first element, 'cuz it's blank.
		array_shift($pathArr);
		
		return($pathArr);
	}//end explode_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Calls $this->a2p->get_data($path).  Just a wrapper for private data.
	 */
	public function get_data($path=NULL) {
		if(!is_null($path) && $path != '/') {
			$path = $this->fix_path($path);
		}
		
		$retval = $this->a2p->get_data($path);
		return($retval);
	}//end get_data()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_tag_value($path) {
		$usePath = $this->fix_path($path);
		$data = $this->a2p->get_data($usePath);
		if(isset($data[cs_phpxmlCreator::dataIndex])) {
			$retval = $data[cs_phpxmlCreator::dataIndex];
		}
		else {
			throw new exception(__METHOD__ .": invalid path (". $path .") or no value present");
		}
		return($retval);
	}//end get_tag_value()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_attribute($path, $attributeName=NULL) {
		$retval = NULL;
		if(!is_null($path)) {
			$path = preg_replace('/\/$/', '', $path);
			if(!$this->preserveCase) {
				$path = strtoupper($path);
				$attributeName = strtoupper($attributeName);
			}
			$data = $this->get_path($path);
			if(is_array($data[cs_phpxmlCreator::attributeIndex])) {
				$data = $data[cs_phpxmlCreator::attributeIndex];
				$retval = $data;
				if(!is_null($attributeName)) {
					if(isset($data[$attributeName])) {
						$retval = $data[$attributeName];
					}
					else {
						throw new exception(__METHOD__ .": no such attribute (". $attributeName .") on path=(". $path .")");
					}
				}
			}
			else {
				throw new exception(__METHOD__ .": no attributes found on path=(". $path .")");
			}
		}
		
		return($retval);
		
	}//end get_attribute()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Break the path into bits, explicitely removing the rootElement from the 
	 * given path: once numeric indexes have been added, the rootElement will 
	 * then be prepended.
	 * 
	 * NOTE: this must NOT be used when altering attributes of the root path or 
	 * in the instance that the root element contains data.
	 */
	protected function fix_path($path) {
		if(!strlen($this->rootElement)) {
			throw new exception(__METHOD__ .": invalid root element");
		}
		if($path === '/') {
			$path = "";
		}
		else {
			$path = preg_replace('/\/$/', '', $path);
			if(!$this->preserveCase) {
				$path = strtoupper($path);
			}
			$path = preg_replace('/\/+/', '/', $path);
			
			$bits = $this->explode_path($path);
			
			//don't append '/0' if they're looking for an attribute or data value.
			$appendThis = null;
			{
				if($bits[(count($bits)-1)] == cs_phpxmlCreator::dataIndex) {
					$appendThis = array_pop($bits);
				}
				if($bits[(count($bits)-1)] == cs_phpxmlCreator::attributeIndex) {
					$appendThis = array_pop($bits);
				}
			}
			
			if(preg_match('/^\//', $path)) {
				/*  absolute path: first item MUST be root element, followed by 0 -- a higher number 
				 *	would indicate multiple root elements.
				 */
				if(preg_match('/^\/'. $this->rootElement .'\/{0,1}/i', $path) || $path == '/') {
					array_shift($bits);
					if(preg_match('/^\/'. $this->rootElement .'\/0\/{0,1}/i', $path)) {
						array_shift($bits);
					}
				}
				else {
					throw new exception(__METHOD__ .": absolute paths must start with rootElement (". $this->rootElement ."), " .
							"and any numeric indexed directly following it MUST be zero (path: ". $path . ")");
				}
			}
			elseif(preg_match('/^'. $this->rootElement .'/i', $path)) {
				array_shift($bits);
				if(preg_match('/^'. $this->rootElement .'\/0/i', $path)) {
					array_shift($bits);
				}
			}
			
			/* the key::: each tag should have a number in the path beyond it.  So "/path/to/something" becomes 
			 *	"/path/0/to/0/something/0", but "/path/to/1/something" must become "/path/0/to/1/something/0" 
			 *	(instead of "/path/0/to/0/1/0/something/0" by automatically adding a 0 to each bit)
			 *
			 * The index handled should ALWAYS be a tag: should the next index be numeric, it will be skipped.
			 */
			
			if(count($bits) == 1 && (is_null($bits[0]) || strlen($bits[0]) == 0)) {
				$bits = array();
			}
			//this array will be transformed into a path again later.
			$useRoot = $this->rootElement;
			if(!$this->preserveCase) {
				$useRoot = strtoupper($this->rootElement);
			}
			$newBits = array(
				0	=> $useRoot,
				1	=> "0"
			);
			$highestBit = (count($bits) -1);
			for($i=0;$i<count($bits);$i++) {
				$currentBit = $bits[$i];
				if(is_numeric($currentBit)) {
					/*
					 * This happens when:
					 * 	-- there are two numerics in a row ("/path/0/0")
					 */
					throw new exception(__METHOD__ .": found numeric (". $currentBit .") where tag should have been");
				}
				else {
					//add this tag ($n) to the array
					$newBits[] = $currentBit;
					
					//make sure we don't go past the end of the array.
					if($i < $highestBit) { 
						if(is_numeric($bits[($i+1)])) {
							//next item is numeric: put it into the path & skip it.
							$newBits[] = $bits[($i+1)];
							$i++;
						}
						else {
							$newBits[] = "0";
						}
					}
					else {
						//the next bit would have been beyond the end of the array, so the index would be 0.
						$newBits[] = "0";
						break;
					}
				}
			}
			
			if(!is_null($appendThis)) {
				$newBits[] = $appendThis;
			}
			$path = $this->reconstruct_path($newBits);
		}
		if(preg_match('/\/\//', $path)) {
			throw new exception(__METHOD__ .": path has too many slashes (". $path .")");
		}
		
		return($path);
	}//end fix_path()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_path($path=null) {
		$path = $this->fix_path($path);
		return($this->a2p->get_data($path));
	}//end get_path()
	//=================================================================================
}
?>
