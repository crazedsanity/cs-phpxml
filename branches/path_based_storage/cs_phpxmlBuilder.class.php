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
	private $rootElement = NULL;
	private $depth = 0;
	private $maxDepth = 50; //if the code gets past this depth of nested tags, assume something went wrong & die.
	private $crossedPaths = array (); //list of paths that have been traversed in the array.
	private $iteration = 0; //current iteration/loop number
	private $maxIterations = 2000; //if we loop this many times, assume something went wront & die.
	private $noDepthStringForCloseTag=NULL; //used to tell close_tag() to not set depth string...
	private $preserveCase=false;
	
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
			
			//create an arrayToPath{} object.
			parent::__construct($xmlArray);
			
			//process the data.
			$this->process_xml_array();
		}
	}//end __construct()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Takes an array like the one that $this->get_tree() spits-out, and turns it back 
	 * into an XML string.
	 */
	private function process_xml_array() {
		//make sure we've got the "goAhead" 
		if($this->goAhead == TRUE) {
			//rip-out the root element.
			$keys = array_keys($this->xmlArray);
			
			if(count($keys) !== 1) {
				//there should only be ONE root element.
				throw new exception(__METHOD__ ."(): multiple root elements (or none) found!");
			}
			else {
				//set the root element.
				$this->rootElement = $keys[0];
				
				//pull the rootElement out of the equation.
				$rootAttributes = $this->a2p->get_data("/". $this->rootElement ."/attributes");
				if(is_array($rootAttributes)) {
					//remove it from our internal array.
					$this->a2p->unset_data("/". $this->rootElement ."/attributes");
				}
				//now remove the "type" index.
				$this->a2p->unset_data("/". $this->rootElement ."/type");
			}
			
			//open a tag for the root element.
			$this->open_tag($this->rootElement, $rootAttributes);
			
			//loop through the array...
			$this->process_sub_arrays('/'. $this->rootElement);
			
			//close the root element.
			$this->xmlString .= "\n";
			$this->close_tag($this->rootElement);
			
			//tell 'em it's all good.
			$retval = TRUE;
		}
		else {
			//no dice, pal.
			$retval = FALSE;
		}
		
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
			$retval .= '/';
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
		$this->iteration++;
		if($this->iteration > $this->maxIterations) {
			//deep recursion!
			throw new exception(__METHOD__ ."(): too many iterations (". $this->iteration .")!");
		}
		elseif(is_null($path) || strlen($path) == 0) {
			//bad.
			throw new exception(__METHOD__ ."(): bad path ($path)!");
		}
		
		//pull the data we're going to be working with.
		$subArray = $this->a2p->get_data($path);
		$origSubArray = $subArray;
		
		if(is_array($subArray)) {
			/*
			 * NOTE: "type" is always set, except for numeric indexes: for instance, if there are
			 * multiple "item" tags beneath "items" (/CART/ITEMS/ITEM), then /CART/ITEMS/type exists,
			 * but /CART/ITEMS/ITEM/type does NOT: /CART/ITEMS/ITEM/0/type, however, does exist.
			 */
			//set the type & attributes stuff.
			{
				if(isset($subArray['type']) || isset($subArray['attributes'])) {
					$parentType = $subArray['type'];
					
					$parentAttribs = null;
					if(isset($subArray['attributes']) && is_array($subArray['attributes'])) {
						$parentAttribs = $subArray['attributes'];
					}
					unset($subArray['type'], $subArray['attributes']);
				}
			}
			
			if(!is_null($parentTag)) {
				//open the tag.
				$this->open_tag($parentTag, $parentAttribs);
			}
			
			//loop through the array.
			foreach($subArray as $tagName=>$data) {
				if(is_array($data)) {
					$type = NULL;
					if(isset($data['type'])) {
						$type = $data['type'];
						unset($data['type']);
					}
					
					$attrArr = NULL;
					if(isset($data['attributes'])) {
						//pull it.
						$attrArr = $data['attributes'];
						
						//remove it.
						unset($data['attributes']);
					}
					
					$tagValue = NULL;
					if(isset($data['value'])) {
						$tagValue = $data['value'];
						unset($data['value']);
					}
					
					//if there's a type, deal with it.  If not, deal with that, too.
					if(!is_null($type) && !is_numeric($tagName)) {
						if($type === 'open') {
							//open the tag...
							$this->open_tag($tagName, $attrArr);
							
							//loop through the sub-data...
							foreach($data as $subTagName => $subData) {
								//update the path, call self, sally forth, tally ho.
								$myPath = $this->create_list($path, $tagName .'/'. $subTagName, '/');
								
								//run a pre-check on that piece of the data...
								$checkData = $this->a2p->get_data($myPath);
								if(isset($checkData['type']) && $checkData['type'] === 'open') {
									//
									$this->process_sub_arrays($myPath, $subTagName);
								}
								elseif(isset($checkData['type']) && $checkData['type'] === 'complete') {
									//it's complete.  Just create the tag here.
									if(isset($checkData['value'])) {
										//got a value...
										$myAttribs = null;
										if(isset($checkData['attributes']) && is_array($checkData['attributes'])) {
											$myAttribs = $checkData['attributes'];
										}
										$this->open_tag($subTagName, $myAttribs);
										$this->add_value_plus_close_tag($checkData['value'], $subTagName);
									}
									else {
										//stand-alone (single) tag.
										$this->open_tag($subTagName, $checkData['attributes'], TRUE);
									}
								}
								else {
									//nothin' doin'
									$this->process_sub_arrays($myPath);
								}
							}
							
							//now close the tag.
							$this->close_tag($tagName);
						}
						elseif($type === 'complete') {
							//TODO: deal with $parentTag here.
							//No need to go any further.
							if(is_null($tagValue)) {
								//single tag, no need to close it.
								$this->open_tag($tagName, $attrArr, TRUE);
							}
							else {
								//got a value: open, append the value, & close it.
								$this->open_tag($tagName, $attrArr);
								$this->add_value_plus_close_tag($tagValue, $tagName);
							}
						}
						else {
							//unknown tag name.
							throw new exception(__METHOD__ ."(): invalid tag type=($type)");
						}
					}
					else {
						
						//null type....
						if(isset($data['0']) && is_array($data['0'])) {
							$myBasePath = $this->create_list($path, $tagName, '/');
							foreach($data as $numericIndex=>$numericSubData) {
								$mySubPath = $this->create_list($myBasePath, $numericIndex, '/');
								$this->process_sub_arrays($mySubPath, $tagName);
							}
						}
						elseif(is_array($subArray['0'])) {
							//special.  Don't know how, yet, but it's fscking special.
							$myBasePath = $this->create_list($path, $tagName, '/');
							$pathArr = $this->a2p->explode_path($myBasePath);
							array_pop($pathArr);
							
							$useThisTagName = array_pop($pathArr);
							$checkData = $this->a2p->get_data($myBasePath);
							if($checkData['type'] === 'complete') {
								//process it specially.
								if(is_null($checkData['value']) || !isset($checkData['value'])) {
									//single tag...
									$this->open_tag($useThisTagName, $checkData['attributes'], TRUE);
								}
								else {
									//single tag with value.
									$myAttribs = null;
									if(isset($checkData['attributes']) && is_array($checkData['attributes'])) {
										$myAttribs = $checkData['attributes'];
									}
									$this->open_tag($useThisTagName, $myAttribs);
									$this->add_value_plus_close_tag($checkData['value'], $useThisTagName);
								}
							}
							else {
								//pass it down the line.
								$this->process_sub_arrays($myBasePath, $useThisTagName);
							}
						}
						else {
							//something broke.
							throw new exception(__METHOD__ ."(): non-null type=($type) on numeric path=($path)");
						}
					}
				}
				else {
					//TODO: is this ever triggered?  Should it cause an exception?
					//not an array.
					$this->xmlString .= $data;
					$this->noDepthStringForCloseTag = TRUE;
				}
			}
				
			if(!is_null($parentTag)) {
				//close the tag.
				$this->close_tag($parentTag);
			}
		}
		else {
			throw new exception(__METHOD__ ."(): found non-array at path ($path)!");
		}
		
		//decrement the iteration, so things know that we're finishing-up with the current one.
		$this->iteration--;
		
	}//end process_sub_arrays()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Determine the parent tagname from the given path, optionally dropping back more than
	 * 	one level (i.e. for "/main/cart/items/0/name/value", going back 3 levels returns
	 * 	"items" ("name"=1, "0"=2, and so on).
	 */
	private function get_parent_from_path($path, $goBackLevels=1) {
		if($goBackLevels < 0) {
			$goBackLevels = 1;
		}
		$path = preg_replace('/\/\//', '/', $path);
		$path = preg_replace('/^\//', '', $path);
		$pathArr = explode('/', $path);
		
		$pathArr = array_reverse($pathArr);
		$retval = $pathArr[$goBackLevels];
		
		return($retval);
	}//end get_parent_from_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Adds a "value" to the xmlString & closes the tag.
	 */
	private function add_value_plus_close_tag($value, $tagName) {
		if(!strlen($value) || !strlen($tagName)) {
			//fatal error.
			throw new exception(__METHOD__ ."(): invalid value ($value), or no tagName ($tagName)!");
		}
		
		//append the value, then close the tag.
		$this->xmlString .= htmlentities($value);
		$this->close_tag($tagName,FALSE);
	}//end add_value_plus_close_tag()
	//=================================================================================

}

?>