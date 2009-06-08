<?php
/*
 * Created on Dec 1, 2006
 * 
 * SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 * 
 */
require_once(dirname(__FILE__) ."/cs_phpxml.abstract.class.php");

	
class cs_phpxmlBuilder extends cs_phpxmlAbstract {
	protected $rootElement = NULL;
	
	private $xmlArray = NULL;
	private $xmlString = "";
	private $depth = 0;
	private $maxDepth = 50; //if the code gets past this depth of nested tags, assume something went wrong & die.
	private $crossedPaths = array (); //list of paths that have been traversed in the array.
	private $iteration = 0; //current iteration/loop number
	private $maxIterations = 2000; //if we loop this many times, assume something went wront & die.
	private $noDepthStringForCloseTag=NULL; //used to tell close_tag() to not set depth string...
	
	//=================================================================================
	/**
	 * The construct.  Pass the array in here, then call get_xml_string() to see the results.
	 */
	public function __construct($xmlArray) {
		if(is_array($xmlArray) && count($xmlArray)) {
			
			if(isset($xmlArray['tags']) && isset($xmlArray['attributes']) && isset($xmlArray['rootElement'])) {
				$this->xmlArray = $xmlArray;
				$this->rootElement = $this->xmlArray['rootElement'];
				unset($this->xmlArray['rootElement']);
				
				//create an arrayToPath{} object.
				parent::__construct($xmlArray);
				
				//process the data.
				$this->process_xml_array();
			}
			else {
				throw new exception(__METHOD__ .": expected array containing rootElement and XML paths to tags and attributes");
			}
		}
	}//end __construct()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Turns a list of paths to XML tags and list of paths to attributes, and turns all 
	 * of it into a coherent XML file.
	 */
	private function process_xml_array() {
		//make sure we've got the "goAhead" 
			
			
			$depthTree = array();
			foreach($this->xmlArray['tags'] as $path=>$tagVal) {
				$pathDepth = $this->get_path_depth($path);
				$depthTree[$pathDepth][] = $path;
			}
			
			
			
			//build a depth tree, so we can work our way up the tree.
			$this->gf = new cs_globalFunctions;
			$this->gf->debug_print($depthTree);
			krsort($depthTree);
			$this->gf->debug_print($depthTree);
			
			
			$rootPaths = array();
			foreach($this->xmlArray['tags'] as $tagPath=>$crap) {
				$a = $this->create_tag2index($tagPath);
				array_pop($a);
				
				$newPath = $this->reconstruct_path($a,true);
				$rootPaths[$newPath][] = $tagPath;
			}
			
			$this->gf->debug_print($rootPaths);
			ksort($rootPaths);
			$this->gf->debug_print($rootPaths);
			
			
			//TODO: use $rootPaths (sorted) to build tags (see below)
			//the sorted rootPaths should allow for creation of each path really quickly, with 
			//	adding of attributes as the only real drawback.
			
			
			
			
			//this will build the array structure for the XML, similar to the way it used to when it
			//	relied heavily on cs_arrayToPath{}...
			$this->a2p = new cs_arrayToPath(array());
			foreach($depthTree as $depth=>$data) {
				foreach($data as $path) {
					if(is_null($this->a2p->get_data($path))) {
						$this->gf->debug_print(__METHOD__ .": setting data into path (". $path .") with data: ". $this->xmlArray['tags'][$path]);
						$this->a2p->set_data($path, $this->xmlArray['tags'][$path]);
					}
					else {
						throw new exception(__METHOD__ .": found existing data on path(". $path .")::: ". $this->a2p->get_data($path));
					}
				}
			}
			
			#$this->gf->debug_print($this->a2p);
			
			
			$this->process_tags(null);
			
			
			$this->gf->debug_print(htmlentities($this->xmlString));
exit(__FILE__ ." -- ". __LINE__);
			//open a tag for the root element.
			//$this->open_tag($this->rootElement, $rootAttributes);
			
			
			//loop through the array...
			$this->process_sub_arrays($this->fix_path('/'. $this->rootElement));
			$this->gf->debug_print(htmlentities($this->xmlString));
throw new exception(__METHOD__ ." - line #". __LINE__ ."::: not finished yet");
			
			//close the root element.
			$this->xmlString .= "\n";
			$this->close_tag($this->rootElement);
			
			//tell 'em it's all good.
			$retval = TRUE;
			
			
		return($retval);
	}//end process_xml_array()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_xml_string($addXmlVersion=FALSE) {
		if($this->goAhead == TRUE) {
			
			//get the parsed data...
			$retval = $this->xmlString;
			
			if($addXmlVersion) {
				//Add the "<?xml version" stuff.
				//TODO: shouldn't the encoding be an option... somewhere?
				$retval = '<?xml version="1.0" encoding="UTF-8"?>'. "\n". $retval;
			} 
		}
		else {
			//FAILURE!
			$retval = NULL;
		}
		
		return($retval);
	}//end get_xml_string()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates an opening tag, possibly with attributes, and appends it to $this->xmlString.
	 * EXAMPLE: <my_opening_tag tag1="tag1_value" tag2="tag2_value">
	 * If $singleTag is TRUE:
	 * 			<my_opening_tag tag1="tag1_value" tag2="tag2_value"/>
	 */
	private function open_tag($tagName, $attrArr=NULL, $singleTag=FALSE) {
		//set the name of the last tag opened, so it can be used later as needed.
		$this->lastTag = $tagName;
		
		$retval = '<'. strtolower($tagName);
		if(is_array($attrArr) && count($attrArr)) {
			foreach($attrArr as $field=>$value) {
				$addThis = strtolower($field) . '="' . htmlentities($value) .'"';
				$retval = $this->create_list($retval, $addThis, " ");
			}
		}
		
		if($singleTag) {
			//it's a single tag, i.e.: <tag comment="i am single" />
			$retval .= ' /';
		}
		$retval .= ">";
		
		$depthString = $this->create_depth_string();
		$this->xmlString .= $depthString . $retval;
	
		//only increment the depth if there are tags beneath this one.
		if(!$singleTag) {
			$this->depth++;
		}	
		
	}//end open_tag();
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates a closing tag & appends it to $this->xmlString.
	 */
	private function close_tag($tagName, $includeDepthString=TRUE) {
		$this->depth--;
		$depthString = "";
		if($includeDepthString && !$this->noDepthStringForCloseTag) {
			//add depth.
			$depthString = $this->create_depth_string();
		}
		$this->noDepthStringForCloseTag = NULL;
		$this->xmlString .= $depthString . "</". strtolower($tagName) . ">";
	}//end close_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	private function create_depth_string() {
		//
		$retval = "";
		if($this->depth > 0) {
			$retval = "\n";
			//make some tabs, so the XML looks nice.
			for($x=0; $x < $this->depth; $x++) {
				//
				$retval .= "\t";
			}
		}
		
		return($retval);
	}//end create_depth_string()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Recursively processes the internal xmlArray.
	 * 
	 * @param $path				(str) the current "path" in the array, for arrayToPath{}.
	 * @param $parentTag	(str,optional) passed if there's multiple same-name tags at that level...
	 */
	private function process_sub_arrays($path='/', $parentTag=NULL) {
		throw new exception(__METHOD__ .": DO NOT USE");
	}//end process_sub_arrays()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Determine the parent tagname from the given path, optionally dropping back more than
	 * 	one level (i.e. for "/main/cart/items/0/name/value", going back 3 levels returns
	 * 	"items" ("name"=1, "0"=2, and so on).
	 */
	private function get_parent_from_path($path, $goBackLevels=1) {
		throw new exception(__METHOD__ .": DO NOT USE");
	}//end get_parent_from_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Adds a "value" to the xmlString & closes the tag.
	 */
	private function add_value_plus_close_tag($value, $tagName) {
		if(!strlen($value) || !strlen($tagName)) {
			//fatal error.
			throw new exception(__METHOD__ ."(): invalid value (". $value ."), or no tagName (". $tagName .")!");
		}
		
		//append the value, then close the tag.
		$this->xmlString .= htmlentities($value);
		$this->close_tag($tagName,false);
	}//end add_value_plus_close_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	private function process_tags($path=null) {
		
		//pull information for the given path.
		$myData = $this->a2p->get_data($path);
		
		$keys = array_keys($myData);
		
		//go through all the keys.
		foreach($keys as $i=>$tagName) {
			if(!is_numeric($tagName)) {
				$myPath = $path .'/'. $tagName;
				$this->handle_tag_subs($myPath);
			}
			else {
				throw new exception(__METHOD__ .": found numeric tag at path=(". $path .")");
			}
		}
		
	}//end process_tags()
	//=================================================================================
	
	
	
	//=================================================================================
	private function handle_tag_subs($path) {
		if(strlen($path) && !is_numeric($path) && preg_match('/\//', $path)) {
			
			$myData = $this->a2p->get_data($path);
			$myTag = array_pop($this->explode_path($path));
			
			if(is_array($myData)) {
				foreach($myData as $pathMultiple=>$pathData) {
					//$pathMultiple should be a number; $pathData is either text (open+close tag) or an array (open tag & pass to process_tags()).
					
					//pass arrays back to process_tags(), otherwise handle here.
					$myPath = $path .'/'. $pathMultiple;
					$attribs = $this->xmlArray['attributes'][$myPath];
					$callProcessTags=false;
					$singleTag=false;
					if(is_array($pathData)) {
						$this->open_tag($myTag, $attribs,false);
						$this->process_tags($myPath);
						$this->close_tag($myTag);
					}
					elseif(is_null($pathData) || !strlen($pathData)) {
						//no need to put anything beneath the tag; it is single (nothing in it)
						$this->open_tag($myTag, $attribs, true);
					}
					else {
						//there aren't tags below it, so open tag, add data, & close it.
						$this->open_tag($myTag, $attribs, false);
						$this->add_value_plus_close_tag($pathData, $myTag	);
					}
				}
			}
			else {
				throw new exception(__METHOD__ .": invalid data found at path=(". $path .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid path (". $path .")");
		}
		
	}//end handle_tag_subs()
	//=================================================================================

}

?>