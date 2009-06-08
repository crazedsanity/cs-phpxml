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
		
		//build a depth tree, so we can work our way up the tree.
		$depthTree = array();
		foreach($this->xmlArray['tags'] as $path=>$tagVal) {
			$pathDepth = $this->get_path_depth($path);
			$depthTree[$pathDepth][] = $path;
		}
		krsort($depthTree);
		
		
		
		//this will build the array structure for the XML, similar to the way it used to when it
		//	relied heavily on cs_arrayToPath{}...
		$this->a2p = new cs_arrayToPath(array());
		foreach($depthTree as $depth=>$data) {
			foreach($data as $path) {
				if(is_null($this->a2p->get_data($path))) {
					$this->a2p->set_data($path, $this->xmlArray['tags'][$path]);
				}
				else {
					throw new exception(__METHOD__ .": found existing data on path(". $path .")::: ". $this->a2p->get_data($path));
				}
			}
		}
		
		return(true);
	}//end process_xml_array()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_xml_string($addXmlVersion=FALSE) {
		
		//build the xml string.
		$this->process_tags();
			
		//get the parsed data...
		$retval = $this->xmlString;
		
		if($addXmlVersion) {
			//Add the "<?xml version" stuff.
			//TODO: shouldn't the encoding be an option... somewhere?
			$retval = '<?xml version="1.0" encoding="UTF-8"?>'. "\n". $retval;
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
		elseif($this->depth == 0 && $this->iteration > 1) {
			$retval = "\n";
		}
		
		return($retval);
	}//end create_depth_string()
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
		
		$this->iteration++;
		
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
					$attribs = null;
					if(isset($this->xmlArray['attributes'][$myPath])) {
						$attribs = $this->xmlArray['attributes'][$myPath];
					}
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