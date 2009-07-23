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

require_once(dirname(__FILE__) .'/cs_arrayToPath.class.php');
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
		
		//now, remove the first element, 'cuz it's blank...?
		if(!strlen($pathArr[0])) {
			array_shift($pathArr);
		}
		
		return($pathArr);
	}//end explode_path()
	//=========================================================================
	
	
	
	//=========================================================================
	final protected function path_from_array(array $bits, $addSlashPrefix=true) {
		$retval = "";
		foreach($bits as $chunk) {
			if(strlen($chunk)) {
				$retval .= '/'. $chunk;
			}
		}
		
		if($addSlashPrefix !== true) {
			//string is has a leading slash already: now we need to remove it.
			$retval = preg_replace('/^\//', '', $retval);
		}
		
		return($retval);
	}//end path_from_array()
	//=========================================================================
	
	
	
	//=================================================================================
	/**
	 * Break the path into bits, add numeric separators, and remove unwanted junk.
	 * 
	 * EXAMPLES:
	 *    input                     ||  output
	 * -----------------------------++---------------------
	 *  /path/TO/3/Home             || /PATH/0/TO/3/HOME/0
	 *  to/0/home/again             || /PATH/0/TO/0/HOME/0/AGAIN/0
	 *  /path/3/TO/HOME/again/0		|| (exception - possible multiple roots)
	 *  /0/path/to/home/0			|| (exception - starts with invalid path)
	 *  /path/0/to/0/0/home			|| (exception - invalid location of numeric tag)
	 */
	final public function fix_path($path) {
		
		//clean out so the path is only alphanumeric and a select few non-alphanumerics.
		//TODO: check the RFC to determine which characters are valid.
		if(strlen($path)) {
			$path = preg_replace("/[^A-Za-z0-9:\/\-\._]/", '', $path);
		}
		$path = preg_replace('/\/{2,}/', '/', $path);
		$page = preg_replace('/\/{1,}$/', '', $path);
		
		if(strlen($path) > 1) {
			
			$path = strtoupper($path);
			if(preg_match("/\//", $path)) {
				//it has slashes, lets assume all is good.
				$path = preg_replace('/^\//', '', $path);
			}
			
			//prepend root element as needed (but only if available)
			if(isset($this->rootElement)) {
				if(!preg_match('/^'. $this->rootElement .'/', $path)) {
					$oldPath = $path;
					$path = '/'. $this->rootElement .'/0/'. $path;
				}
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid length of path (". $path .")");
		}
		
		//now add numeric indexes as needed.
		$tag2Index = $this->create_tag2index($path);
		
		if(is_array($tag2Index) && count($tag2Index)) {
			$newPath = '';
			foreach($tag2Index as $i=>$myData) {
				//$tagName => $tagIndex) {
				$tagName = $myData[0];
				$tagIndex = $myData[1];
				$newPath .= '/'. $tagName .'/'. $tagIndex;
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to produce array of tags to indexes after "
				."processing path (". $path .")");
		}
		
		return($newPath);
		
	}//end fix_path()
	//=================================================================================
	
	
	
	//=========================================================================
	/**
	 * Breaks apart the given path into an array, with the indexes containing 
	 * (in order the path, and the values containing the tag number within 
	 * that path.
	 * 
	 * EXAMPLE:  $path="/ROOT/PATH/TO/3/HEAVEN"
	 * OUTPUT: 
	 * array(
	 * 		[ROOT]		=> 0,
	 * 		[PATH]		=> 0,
	 * 		[TO]		=> 3,	// indicates to use tag #4 under "/ROOT/0/PATH/0"
	 * 		[HEAVEN]	=> 0
	 * );
	 */
	final public function create_tag2index($path, $useCurrentPathIndex=false) {
		
		if(strlen($path) && !is_numeric($path)) {
			//final deal: let's add numbers to the path as required.
			$originalPath = $path;
			$bits = $this->explode_path($path);
			
			$retval = array();
			
			//In "ROOT/0/PATH/1/TO/0/HEAVEN/0", the non numerics are in 0, 2, 4, and 6
			//	0=ROOT, 2=PATH, 4=TO, 6=HEAVEN [i.e. ROOT/PATH/TO/HEAVEN]
			/*
			 * NOTE:  the "MULTPATH" is where the pathMultiple would reside, indicating 
			 * 			how it would determine what that tag's index would be.
			 * FINAL ARRAY:::
			 * 		$tag2Index = array (
			 * 			0	=> array (
			 * 					0			=> ROOT,
			 * 					1			=> 0,
			 * 					[MULTPATH]	=> /ROOT
			 * 				),
			 * 			1	=> array (
			 * 					0			=> PATH,
			 * 					1			=> 1,
			 * 					[MULTPATH]	=> /ROOT/0/PATH
			 * 				),
			 * 			2	=> array (
			 * 					0			=> TO,
			 * 					1			=> 0,
			 * 					[MULTPATH]	=> /ROOT/0/PATH/1/TO
			 * 				),
			 * 			3	=> array (
			 * 					0			=> HEAVEN,
			 * 					1			=> 0,
			 * 					[MULTPATH]	=> /ROOT/0/PATH/1/TO/0/HEAVEN
			 * 				)
			 * 		);
			 * 
			 */
			
			$lastIndexNumeric=false;
			//foreach($bits as $i=>$tagOrIndex) {
			$curPath = "";
			$latestPath = "";
			$debug=" ---- [". __METHOD__ ."] MOST RECENT PATH DEBUG INFO ----\n";
			for($i=0; $i<count($bits); $i++) {
				if(isset($bits[$i]) && (!is_numeric($bits[$i]) || preg_match('/^[A-Z]/', $bits[$i]))) {
					
					//when checking the next index, first make sure it exists.
					$checkNext = null;
					if($i < (count($bits) -1)) {
						$checkNext = $bits[($i +1)];
					}
					
					$myPathIndex = 0;
					$tagOrIndex = $bits[$i];
					//okay, we've got a text string: check if the next item is a number...
					if(is_numeric($checkNext)) {
						//user explicitly set numeric in path (i.e. "/ROOT/PATH/1/TO/HEAVEN")
						$myPathIndex = $checkNext;
						$lastIndexNumeric=true;
						$i++;
					}
					$curPath .= '/'. $tagOrIndex;
					
					if($useCurrentPathIndex === true) {
						//$latestPath = $curPath .'/'. $this->get_path_multiple($curPath);
						//$debug .= "curPath=(". $curPath ."), latestPath=(". $latestPath .")\n";
						
						//if there are multiple paths that START with this path, increment the index.
						$derivedIndex = 0;
						foreach($this->paths as $p=>$v) {
							$matchThis = preg_replace('/\//', '\\\/', $curPath);
							if(preg_match('/^'. $matchThis .'/', $p)) {
								$derivedIndex++;
							}
						}
						$myPathIndex = $derivedIndex;
					}
					
					$retval[] = array(
						0			=> $tagOrIndex,
						1			=> $myPathIndex,
						'MULTPATH'	=> $curPath
					);
					$curPath .= '/'. $myPathIndex;
				}
				else {
					throw new exception(__METHOD__ .": while walking path, attempted to access invalid "
						."index (". $i .") [starting at zero] or found invalid location of numeric "
						."(". $bits[$i] .")");
				}
			}
			if($useCurrentPathIndex) {
				$this->gf->debug_print(__METHOD__ .": curPath=(". $curPath ."), latest=(". $latestPath .")");
				if($latestPath == '/MAIN/0/MULTIPLE/0/ITEM/0/AGAIN/0/TEST/0') {
					$this->gf->debug_print($this->pathMultiples);
					$this->gf->debug_print($debug);
					$this->gf->debug_print($retval);
					#exit;
				}
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid path (". $path .")");
		}
		
		return($retval);
	}//end create_tag2index()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Determine how deep this path is: used for building XML string.
	 * 
	 * EXAMPLE: "/ROOT/PATH/TO/HEAVEN" is depth of 3.
	 */
	final protected function get_path_depth($path) {
		$bits = $this->create_tag2index($this->fix_path($path));
		
		return(count($bits));
	}//end get_path_depth()
	//=========================================================================
	
	
	
	//=================================================================================
	/**
	 * Takes an array created by explode_path() and reconstitutes it into a proper path.
	 * 
	 * TODO: this is basically the same as cs_phpxmlAbstract::path_from_array(); consolidate.
	 */
	final protected function reconstruct_path(array $pathArr, $isTag2Index=false) {
		//setup the path variable.
		$path = "";
		
		if($isTag2Index) {
			/*
			 * an array formatted as tag2Index means the index is the tagName, and the value 
			 * is the tagNumber, I.E.:
			 * 
			 * array(
			 * 		[ROOT]		=> 0,
			 * 		[PATH]		=> 0,
			 * 		[TO]		=> 2,
			 * 		[HEAVEN]	=> 0
			 * );
			 * needs to become "/ROOT/0/PATH/0/TO/2/HEAVEN/0"
			 */
			$oldPathArr = $pathArr;
			$pathArr = array();
			foreach($oldPathArr as $tagName=>$tagNumber) {
				$pathArr[] = $tagName;
				$pathArr[] = $tagNumber;
			}
		}
		$path = $this->path_from_array($pathArr);
		
		//give 'em what they want.
		return($path);
	}//end reconstruct_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Build internal "pathMultiples" list with indexes for parents of the given tag; 
	 * does NOT update any existing values.
	 */
	protected function build_parent_path_multiples($path) {
		$tag2Index = $this->create_tag2index($path);
		
		//since we're only verifying/building parent multiples, drop the last tag.
		array_pop($tag2Index);
		
		$retval = 0;
		if(is_array($tag2Index)) {
			$myPath = "";
			foreach($tag2Index as $i=>$myData) {
				$curTag = $myData[0];
				$index = $myData[1];
				$myPath .= '/'. $curTag .'/'. $index;
				
				if(!isset($this->pathMultiples[$myPath])) {
					//$this->pathMultiples[$myPath] = 0;
					$this->update_path_multiple($myPath,true);
					$retval++;
				}
			}
		}
		
		return($retval);
	}//end build_parent_path_multiples()
	//=================================================================================
	
	
	
	//=================================================================================
	protected function update_path_multiple($path, $justInitializeIt=false) {
		$path = $this->fix_path($path);
		$pathBits = $this->explode_path($path);
		
		$lastBit = array_pop($pathBits);
		if(is_numeric($lastBit)) {
			$index = $this->path_from_array($pathBits);
			$altIndex = $this->reconstruct_path($this->create_tag2index($path));
			$this->gf->debug_print(__METHOD__ .": index=(". $index ."), altIndex=(". $altIndex .")");
			if(preg_match('/\/[0-9]$/', $index)) {
				throw new exception(__METHOD__ .": invalid index (". $index .") from path=(". $path .") -- ORIGINAL=(". func_get_arg(0) .")");
			}
			
			if(!preg_match('/^\//', $index)) {
				throw new exception(__METHOD__ .": path_from_array() failed to create proper string...!!!");
			}
			
			if(isset($this->pathMultiples[$index])) {
				if($justInitializeIt === false) {
					$this->pathMultiples[$index]++;
				}
			}
			else {
				$this->pathMultiples[$index]=0;
			}
			$retval = $index .'/'. $this->pathMultiples[$index];
		}
		else {
			throw new exception(__METHOD__ .": invalid lastBit on path (". $path .")");
		}
		
		return($retval);
		
	}//end update_path_multiple()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_path_multiple($path) {
		$path = $this->fix_path($path);
		
		//derive it.
		$derivedIndex = 0;
		$pathBits = $this->create_tag2index($path);
		$lastBit = array_pop($pathBits);
		$findThis = preg_replace('/\//', '\\\/', $lastBit['MULTPATH']);
		foreach($this->paths as $p=>$v) {
			if(preg_match('/^'. $findThis .'/', $p)) {
				$derivedIndex++;
			}
		}
		
		if(isset($this->pathMultiples[$path])) {
			$retval = $this->pathMultiples[$path];
		}
		else {
			//throw new exception(__METHOD__ .": invalid path (". $path .")");
			$retval = 0;
		}
		
		$this->gf->debug_print(__METHOD__ .": arg0=(". func_get_arg(0) ."), new path=(". $path ."), retval=(". $retval ."), derived=(". $derivedIndex .")");
		
		return($retval);
	}//end get_path_multiple()
	//=================================================================================
}
?>