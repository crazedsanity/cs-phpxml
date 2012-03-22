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

	
class cs_phpxmlBuilder extends cs_phpxmlAbstract {
	private $goAhead = FALSE;
	private $xmlArray = NULL;
	private $xmlString = "";
	protected $rootElement = NULL;
	private $depth = 0;
	private $maxDepth = 50; //if the code gets past this depth of nested tags, assume something went wrong & die.
	private $crossedPaths = array (); //list of paths that have been traversed in the array.
	private $iteration = 0; //current iteration/loop number
	private $maxIterations = 2000; //if we loop this many times, assume something went wront & die.
	private $noDepthStringForCloseTag=NULL; //used to tell close_tag() to not set depth string...
	protected $preserveCase=false;
	private $rootAttributes=null;

	private $dataIndex = cs_phpxmlCreator::dataIndex;
	private $attributeIndex = cs_phpxmlCreator::attributeIndex;
	
	//=================================================================================
	/**
	 * The construct.  Pass the array in here, then call get_xml_string() to see the results.
	 */
	public function __construct($xmlArray, $preserveCase=false) {
		if(is_array($xmlArray) && count($xmlArray)) {
			//all looks good.  Give 'em the go ahead.
			$this->goAhead = TRUE;
			$this->xmlArray = $xmlArray;
			
			if(is_bool($preserveCase)) {
				$this->preserveCase = $preserveCase;
			}
			
			//check to make sure there's only ONE root element.
			if(count($xmlArray) != 1) {
				throw new exception(__METHOD__ .": one (and only one) root element allowed");
			}
			
			$keys = array_keys($xmlArray);
			$this->rootElement = $keys[0];
			if(!$this->preserveCase) {
				$this->rootElement = strtoupper($this->rootElement);
			}
			
			//create an arrayToPath{} object.
			parent::__construct($xmlArray);
		}
		else {
			throw new exception(__METHOD__ .": FATAL: no array passed::: ". $this->gfObj->debug_var_dump($xmlArray));
		}
	}//end __construct()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_xml_string($addXmlVersion=FALSE, $addEncoding=null) {
		$this->xmlString = "";
		$xmlDataArray = $this->a2p->get_data($this->rootElement);
		
		if(is_array($xmlDataArray) && count($xmlDataArray)) {
			//process the data.
			$this->tag_builder($xmlDataArray, $this->rootElement);
		}
		else {
			//it just the root element, no attributes, no nothin'.
			//EXAMPLE::: <root />  (not so sure it is valid XML).
			throw new exception(__METHOD__ .": unhandled case (root element without sub-tags)");
		}
		
		
		if($addXmlVersion) {
			$addThis = "";
			if(is_string($addXmlVersion) && preg_match('/[0-9]\./[0-9]', $addXmlVersion)) {
				$addThis .= 'version="'. $addXmlVersion .'"';
			}
			else {
				$addThis = ' version="1.0"';
			}
			if(!is_null($addEncoding) && $addEncoding !== false) {
				$addThis = ' encoding="'. $addEncoding .'"';
			}
			//Add the "<?xml version" stuff.
			$this->xmlString = '<?xml'. $addThis .'?' . ">\n". $this->xmlString;
		} 
		
		return($this->xmlString);
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
		
		if(!$this->preserveCase) {
			$tagName = strtolower($tagName);
		}
		$retval = '<'. $tagName;
		
		
		if(is_array($attrArr) && count($attrArr)) {
			foreach($attrArr as $field=>$value) {
				if(!$this->preserveCase) {
					$field = strtolower($field);
				}
				$addThis = $field . '="' . htmlentities($value) .'"';
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
		if(!$this->preserveCase) {
			$tagName = strtolower($tagName);
		}
		if($this->depth != 0) {
			$depthString = "";
			if($includeDepthString && !$this->noDepthStringForCloseTag) {
				//add depth.
				$depthString = $this->create_depth_string();
			}
			$this->noDepthStringForCloseTag = NULL;
			$this->xmlString .= $depthString . "</". $tagName . ">";
		}
		else {
			$this->xmlString .= "\n</". $tagName .">";
		}
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
	
	
	
	//=================================================================================
	private function tag_builder($data, $parentTag, $path=null) {
		if(is_null($path)) {
			$path = '/'. $this->rootElement;
		}
#$this->gfObj->debug_print(__METHOD__ .": path=(". $path .")". $this->gfObj->debug_print($data,0),1);
		
		if(is_array($data)) {
			$parentTagOpened = false;
			foreach($data as $i=>$v) {
$originalArray = $v;
				$updatedPath = $path;
				
				$attribs = null;
				$tagData = null;
				
				if(is_array($v)) {
				if(isset($v[$this->attributeIndex])) {
					$attribs = $v[$this->attributeIndex];
					unset($v[$this->attributeIndex]);
				}
				
				if(isset($v[$this->dataIndex])) {
					$tagData = $v[$this->dataIndex];
					unset($v[$this->dataIndex]);
				}
				}
				
				if(is_array($v) && count($v)) {
					$this->open_tag($parentTag, $attribs);
					foreach($v as $subIndex=>$subValue) {
						$updatedPath = $path .'/'. $subIndex;
						$this->tag_builder($subValue, $subIndex, $updatedPath);
					}
					$this->close_tag($parentTag, true);
				}
				else {
					//we've reached a dead-end for this path.
					if(!is_null($tagData) && strlen($tagData)) {
						$this->open_tag($parentTag, $attribs, false);
						$this->add_value_plus_close_tag($tagData, $parentTag, false);
					}
					else {
						#$this->close_tag($parentTag);
						//single tag (i.e. "<tag />").
						$this->open_tag($parentTag, $attribs, true);
					}
				}
			}
			#$this->close_tag($parentTag, true);
		}
		else {
			//throw new exception(__METHOD__ .": invalid data::: ". $this->gfObj->debug_var_dump($data,0));
			$this->open_tag($parentTag);
		}
		
	}//end tag_builder()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Adds a "value" to the xmlString & closes the tag.
	 */
	private function add_value_plus_close_tag($value, $tagName, $addDepthString=false) {
		if(!strlen($value) || !strlen($tagName)) {
			//fatal error.
			throw new exception(__METHOD__ ."(): invalid value ($value), or no tagName ($tagName)!");
		}
		
		//append the value, then close the tag.
		$this->xmlString .= htmlentities(stripslashes($value), ENT_NOQUOTES);
		$this->close_tag($tagName,$addDepthString);
	}//end add_value_plus_close_tag()
	//=================================================================================

}

?>
