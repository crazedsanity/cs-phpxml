<?php
/*
 * Created on Dec 18, 2006
 * 
 * 
 * SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 * 
 * 
 * Methods to create XML that's parseable by cs_phpxmlBuilder{}.  Eliminates the need for manually creating
 * a massive array, just to feed it into cs_phpxmlBuilder: it's assumed that the XML is being built in-line,
 * though there are methods for "going back" and modifying specific items within a specific tag (in the example
 * below, the value of NAME in the second ITEM would be referenced as "/CART/ITEM/1/NAME", where "1" indicates 
 * it is the second item--0 is programmatically the first index).
 * 
 * EXAMPLE OF THE EXPECTED RETURNED XML:
 * 
 * <CART>
 * 		<ITEM comment="1">
 * 			<NAME>foo</NAME>
 * 			<VALUE>lots</VALUE>
 * 			<EXTRA location="the internet" />
 * 		</ITEM>
 * 		<ITEM comment="2">
 * 			<NAME>bar</NAME>
 * 			<VALUE currency="USD">even more</VALUE>
 * 			<EXTRA location="unknown" />
 * 		</ITEM>
 * </CART>
 * 
 * NOTE ON PATHS:
 * 	cs_arrayToPath{} facilitates referencing items within an array using a path: in the example XML (above),
 * 	the element with the value of "foo" would be in the path "/cart/item/0/name" (the number after "item"
 * 	indicates it is programatically the first element within "cart" with the element name of "item").
 * 
 * PATH CASE:
 * 	Because of the way PHP processes XML trees, regular tags are stored in UPPER CASE.  Attributes and
 * 	values (the data between open & close tags) are stored in lowercase.  Any paths given will be 
 * 	automatically changed to UPPER case.  Note that it is not possible to have a path which directly 
 *  references an attribute (using the example above, "/CART/ITEM/2/VALUE/currency" would fail: a call 
 *  would have to be made to update the attribute "currency" on the path "/CART/ITEM/2/VALUE").
 * 
 * MULTIPLE SAME-NAME TAGS WITHIN THE SAME TAG:
 * 	In the example XML (above), the path "/cart/item" will have two numeric sub-indexes in the internal
 * 	array.  For this reason, the path "/cart" must be declared as containing multiple "item" tags.
 * 
 * REFERENCING PATHS THAT DON'T ALREADY EXIST:
 *  If data is attempted to be added to a path that doesn't already exist, that path will be created. Of
 * 	course, because of this ability, extra checks have to be performed to see that the "intermediate tags"
 * 	have been created properly.
 * 
 * CODE TO CREATE THAT XML:::

		 $xml = new cs_phpxmlCreator('cart');
		 
		 //build tags in the first "ITEM" tag.
		 $xml->add_tag('/cart/item/name', "foo");
		 $xml->add_tag('/cart/item/value', "lots");
		 $xml->add_tag('/cart/item/extra', null);
		 $xml->add_attribute('/cart/item', array('comment'=>"1"));
		 $xml->add_attribute('/cart/item/extra', array('location'=>"the internet"));
		 
		 //We need to tell it that there's a NEW item tag...
		 $xml->add_tag_multiple('/cart/item');
		 
		 //now items in the second "ITEM" tag (the call to add_tag_multiple() means calls along this path
		 //		without specific indexes will work on the latest/lowest item on that path).
		 // NOTE: in this tag, we'll add attributes when creating tags.
		 $xml->add_tag('/cart/item', null, array('comment'=>"2")); //null value means it can have sub-tags.
		 $xml->add_tag('/cart/item/name', "bar");
		 $xml->add_tag('/cart/item/value', "even more", array('currency'=>"USD"));
		 $xml->add_tag('/cart/item/extra', null, array('location'=>"unknown"));
		 
		 //finally, we'll create the xml string here, which can be written to a file or whatever.
		 $xml->create_xml_string();
 */

require_once(dirname(__FILE__) ."/cs_phpxml.abstract.class.php");
require_once(dirname(__FILE__) ."/cs_phpxmlBuilder.class.php");

class cs_phpxmlCreator extends cs_phpxmlAbstract {
	protected $rootElement;
	private $paths=array();
	protected $pathMultiples=array();
	private $attributes = array();
	
	//=================================================================================
	/**
	 * The constructor.	
	 */
	public function __construct($rootElement="main") {
		//check to ensure there's a real element.
		if(!strlen($rootElement)) {
			//Give it a default root element.
			$rootElement = "main";
		}
		
		//set the root element
		$this->rootElement = strtoupper($rootElement);
		
		//create our internal data structure using arrayToPath{}.
		parent::__construct();
		$this->gf = new cs_globalFunctions;
		
	}//end __construct()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates a tag in the given path with the given attributes.
	 * 
	 * @param $path			(str) path used by arrayToPath{} to set the data into it's array: the last
	 * 							"tag" in the path (after the last "/") should be the new tag.
	 * @param $value		(str, optional) Data to set within the given path (an array of tagname=>value).
	 * @param $attributes	(array,optional) name=>value array of attributes to add to this tag.
	 */
	public function add_tag($path, $value=NULL, array $attributes=NULL) {
		//TODO: call something to verify the path, especially if there are numbers in the path.
		if(strlen($this->fix_path($path))) {
			//fix the path to contain proper indexes.
			$path = $this->fix_path($path);
			
			$builtParentMultiples = $this->build_parent_path_multiples($path);
			
			//check to see if this is part of another path: if it is, we gotta increment that final value.
			$path = $this->update_path_multiple($path);
			
			$this->paths[$path] = $value;
			
			if(is_array($attributes) && count($attributes)) {
				$this->add_attribute($path, $attributes);
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid path string (". $path .")");
		}
		
		//this returns the path it was actually created on.
		return($path);
		
	}//end add_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Add attributes to the tag specified by $path. If the path does not exist, it will 
	 * be created.
	 */
	public function add_attribute($path, array $attributes) {
		
		$path = $this->fix_path($path);
		if(isset($this->attributes[$path])) {
			$this->attributes[$path] = array_merge($this->attributes[$path], $attributes);
		}
		else {
			$this->attributes[$path] = $attributes;
		}
		
		return($path);
	}//end add_attribute()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates an XML string based upon the current internal array structure.
	 */
	public function create_xml_string($addXmlVersion=FALSE) {
		
		$this->a2p = new cs_arrayToPath(array());
		if(is_array($this->paths) && count($this->paths)) {
			foreach($this->paths as $p=>$v) {
				$this->a2p->set_data($p, $v);
			}
			
			$dataToPass = array(
				'rootElement'	=> $this->rootElement,
				'tags'			=> $this->paths,
				'attributes'	=> $this->attributes
			);
			$xmlBuilder = new cs_phpxmlBuilder($dataToPass);
			$retval = $xmlBuilder->get_xml_string($addXmlVersion);
		}
		else {
			throw new exception(__METHOD__ .": no internal data");
		}
		return($retval);
		
	}//end create_xml_string()
	//=================================================================================
	
	
	
	//=================================================================================
	private function create_tag($tagName, $value=NULL, array $attributes=NULL, $type=NULL) {
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
		throw new exception(__METHOD__ .": fix me!!!!");
		//upper-case the tagname.
		$tagName = strtoupper($tagName);
		
		//set a default type for the tag, if none defined.
		if(is_null($type) || !in_array($type, array('open', 'complete'))) {
			//setting a default type.
			$type = 'complete';
		}
		
		//setup the tag's structure.
		$myTag = array (
			$tagName	=> array(
				'type'		=> $type
			)
		);
		
		//check to see that we've got what appears to be a valid attributes array.
		if(is_array($attributes)) {
			//looks good.  Add the attributes to our array.
			$myTag[$tagName]['attributes'] = $attributes;
		}
		
		//if they've got a value, add it to the array as well.
		if(!is_null($value) && (is_string($value) || is_numeric($value))) {
			if (strlen($value)) {
				//add the value then, it's got a length! - note this will convert numeric values above into strings for checking?
				$myTag[$tagName]['value'] = htmlentities(html_entity_decode($value));
			}
		}
		
		//give 'em what they want.
		return($myTag);
	}//end create_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * The tag is set to have multiple instances of itself
	 */
	public function set_tag_as_multiple($path) {
		$path = $this->fix_path($path);
		$pathBits = $this->explode_path($path);
		array_pop($pathBits);
		$path = $this->path_from_array($pathBits);
		
		
		$gf = new cs_globalFunctions;
		$gf->debug_print($pathBits);
		
		//now update the internal tracker for that path.
		$this->update_path_multiple($path);
	}//end set_tag_as_multiple()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Instead of calling add_tag() for a bunch of different paths, pass arrays to this 
	 * method and it will do the work for you.
	 * 
	 * EXAMPLE: if multiple "songs" are beneath "/main/songs", call it like this:
	 * $myArr = array
	 * (
	 * 		'first/title'	=> "first title",
	 * 		'first/artist'	=> "Magic Man",
	 * 		'second/title'	=> "second title",
	 * 		'second/artist'	=> "Another ARtist"
	 * );
	 * $myAttribs = array(
	 * 		'first/title'	=> array('titleId'=>"123");
	 * 		'second/title'	=> array('titleId'=>"55453", 'comment'=>"this is a test");
	 * );
	 * 
	 * $xml->add_tag_multiple('/main/songs', $myArr, $myAttribs);
	 * 
	 * THE SAME CAN BE DONE USING add_tag():::
	 * 
	 * foreach($myArr as $subPath=>$value) {
	 * 		$xml->add_tag('/main/songs/'. $subPath, $value, $myAttribs[$subPath]);
	 * }
	 */
	public function add_tag_multiple($path, array $subPath2Value, array $attributes=NULL) {
		
		$path = $this->fix_path($path);
		
		$gf = new cs_globalFunctions;
		
		$retval = array();
		
		if(is_array($subPath2Value) && count($subPath2Value)) {
			foreach($subPath2Value as $subPath => $value) {
				$myPath = $this->fix_path($path .'/'. $subPath);
				$myAttr=null;
				if(isset($attributes[$subPath]) && is_array($attributes[$subPath])) {
					$myAttr = $attributes[$subPath];
				}
				$retval[$subPath] = $this->add_tag($myPath, $value, $myAttr);
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid or missing data");
		}
		
		return($retval);
	}//end add_tag_multiple()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * In some instances, it's important to be able to change the root element on-the-fly,
	 * after the class has been instantiated.  Here's where to do it.
	 */
	public function rename_root_element($newName) {
		$newName = strtoupper($newName);
		
		$oldPaths = $this->paths;
		$oldKeys = array_keys($this->paths);
		$oldVals = array_values($this->paths);
		$this->paths = array();
		for($i=0; $i<count($oldPaths); $i++) {
			$newPath = preg_replace('/^\/'. $this->rootElement .'/', '\/'. $newName, $oldKeys[$i]);
			$this->paths[$newPath] = $oldVals[$i];
		}
		$this->rootElement = $newName;
	}//end rename_root_element()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Calls $this->a2p->get_data($path).  Just a wrapper for private data.
	 */
	public function get_data($path=NULL) {
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
		$retval = $this->a2p->get_data($path);
		return($retval);
	}//end get_data()
	//=================================================================================
	
	
	//=================================================================================
	/**
	 * Takes an XMLParser object & loads data from it as the internal XML array. This 
	 * facilitates the ability to add data to existing XML.
	 */
	public function load_xmlparser_data(cs_phpxmlParser $obj) {
		//reset some internal data.
		$this->paths = array();
		$this->attributes = array();
		$this->pathMultiples = array();
		$this->rootElement = null;
		
		//dump arrayToPath data from the given object into our internal one.
		$a2p = new cs_arrayToPath(array());
		$obj->update_a2p($a2p);
		
		//reset internal "paths" (this will be systematically reset when creating new tags & such).
		$this->paths = array();
		$validPaths = $a2p->get_valid_paths();
		$this->gf->debug_print($validPaths);
		
		$this->rootElement = $validPaths[0];
		$this->rootElement = preg_replace('/^\//', '', $this->rootElement);
		$this->rootElement = preg_replace('/\/type$/', '', $this->rootElement);
		if(preg_match('/\//', $this->rootElement)) {
			throw new exception(__METHOD__ .": rootElement (". $this->rootElement .") contains invalid characters");
		}
		
		foreach($validPaths as $i=>$path) {
			if(preg_match('/\/value$/', $path)) {
				$tagPath = preg_replace('/\/value/', '', $path);
				$this->add_tag($tagPath, $a2p->get_data($path));
			}
			elseif(preg_match('/\/attributes\/(.*)$/', $path)) {
				$bits = split('/attributes/', $path);
				
				$this->add_attribute($bits[0], array($bits[1] => $a2p->get_data($path)));
			}
		}
		
	}//end load_xmlparser_data()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Destroy a given path and all paths beneath it...
	 */
	public function remove_path($path,$destroySubs=false) {
		
		$path = $this->fix_path($path);
		
		$retval = 0;
		if(isset($this->paths[$path])) {
			unset($this->paths[$path]);
			$retval++;
		}
		
		//$pathRegexed = preg_replace('/\//', '\\\/', $path);
		$destroyed = 0;
		$pathRegexed = str_replace('/', '//', $path);
		foreach($this->paths as $i=>$v) {
			//$i = preg_replace('/\//', '\\\/', $i);
			if(preg_match('/^'. $pathRegexed .'/', $i)) {
				if($destroySubs) {
					unset($this->paths[$i]);
					$retval++;
				}
				else {
					throw new exception(__METHOD__ .": the given path (". $path .") has"
						." at least one sibling (". $i .")");
				}
			}
		}
		
		//TODO: should it throw an exception if $retval==0?
		
		return($retval);
	}//end remove_path();
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Determine if the given path actually exists.
	 */
	protected function path_exists($path) {
		
		//TODO: consider handling requests for paths within numeric indexes (i.e. where at tag is used multiple times)
		$path = $this->fix_path($path);
		if(strlen($path)) {
			$retval = false;
			if(isset($this->paths[$path])) {
				$retval = true;
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid path (". $path .")");
		}
		
		return($retval);
	}//end path_exists()
	//=================================================================================
	
	
	
	//=================================================================================
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
	private function update_path_multiple($path, $justInitializeIt=false) {
		$path = $this->fix_path($path);
		$pathBits = $this->explode_path($path);
		
		$lastBit = array_pop($pathBits);
		if(is_numeric($lastBit)) {
			$index = $this->path_from_array($pathBits);
			if(preg_match('/\/[0-9]$/', $index)) {
				throw new exception(__METHOD__ .": invalid index (". $index .") from path=(". $path .") -- ORIGINAL=(". func_get_arg(0) .")");
			}
			
			if(!preg_match('/^\//', $index)) {
				throw new exception(__METHOD__ .": path_from_array() failed to create proper string...!!!");
			}
			
			if(isset($this->pathMultiples[$index])) {
				if($justInitializeIt === false) {
					$this->pathMultiples[$index]++;
					$this->gf->debug_print("<font color='red'><b>". __METHOD__ ."</b></font>: updating path multiple for (". $index .") to (". $this->pathMultiples[$index] 
							.") -- onlyIfNotPresent=(". $this->gf->interpret_bool($justInitializeIt) .")");
				}
			}
			else {
				$this->gf->debug_print("<b>". __METHOD__ ."</b>: initializing path multiple for (". $index .")");
				$this->pathMultiples[$index]=0;
			}
			$retval = $index .'/'. $this->pathMultiples[$index];
			
			$this->gf->debug_print(__METHOD__ .": setting retval as (". $retval .")");
		}
		else {
			throw new exception(__METHOD__ .": invalid lastBit on path (". $path .")");
		}
		
		return($retval);
		
	}//end update_path_multiple()
	//=================================================================================
	
}//end xmlCreator{}
?>
