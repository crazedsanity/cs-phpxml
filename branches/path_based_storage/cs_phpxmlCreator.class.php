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
 * though there are methods for "going back" and modifying specific items within a specific tag (tags 
 * that have the same name are represented numerically).
 * 
 * EXAMPLE OF THE EXPECTED RETURNED XML:
 * <cart>
 * 		<item comment="1">
 * 			<name>foo</name>
 * 			<value>lots</value>
 * 			<extra location="the internet" />
 * 		</item>
 * 		<item comment="2">
 * 			<name>bar</name>
 * 			<value currency="USD">even more</value>
 * 			<extra location="unknown" />
 * 		</item>
 * </cart>
 * 
 * NOTE ON PATHS:
 * 	cs_arrayToPath{} facilitates referencing items within an array using a path: in the example XML (above),
 * 	the element with the value of "foo" would be in the path "/cart/item/0/name" (the number after "item"
	 * 	indicates it is programatically the first element within "cart" with the element name of "item").  
 * 	Internally, all paths are stored with an index after the tag, so it is stored as "/cart/0/item/0/name/0".
 * 
 * PATH CASE:
 * 	Paths will be stored as case sensitive ONLY if the "preserveCase" argument to the constructor is passed 
 * 	as boolean TRUE.  If case isn't preserved, then internal paths will be stored in UPPERCASE, and the 
 * 	entirety of the output XML will be in *lowercase*.  In the event that case is preserved, using tags with 
 * 	differing cases will cause multiple tags to be created (i.e. "/cart/item/value" would be distinct 
 * 	from "/cart/item/Value").
 * 
 * MULTIPLE SAME-NAME TAGS WITHIN THE SAME TAG:
 * 	In the example XML (above), the path "/cart/item" will have two numeric sub-indexes in the internal
 * 	array (0 and 1).  Non-explicit paths, such as "/cart/item", will default to the first item: reading 
 * 	from "/cart/item/name" would return "foo", whereas using "/cart/item/1/name" would return "bar". 
 * 
 * REFERENCING PATHS THAT DON'T ALREADY EXIST:
 *  If data is attempted to be added to a path that doesn't already exist, that path will be created. Of
 * 	course, because of this ability, extra checks have to be performed to see that the "intermediate tags"
 * 	have been created properly.
 * 
 * CODE TO CREATE EXAMPLE XML:::
 *
 
	 $xml->add_tag("/cart/item/name", "foo");
	 $xml->add_attribute("/cart/item", array('comment'=>"1"));	//this REPLACES all attributes with the given array.
	 $xml->add_tag("/cart/item/value", "lots");
	 $xml->add_tag("/cart/item/extra", null);
	 $xml->add_tag("/cart/item/extra", array('location'=>"the internet"));
	 $xml->add_tag("/cart/item/1/name", "bar");
	 $xml->add_tag("/cart/item/1/value", "even more");
	 $xml->add_attribute("/cart/item/1", array('comment'=>"2"));
	 $xml->add_attribute("/cart/item/1/value", array('currency'=>"USD"));
	 $xml->add_tag("/cart/item/1/extra", null, array('location'=>"unknown"));	//faster than adding attribs later.
	 
 */


class cs_phpxmlCreator extends cs_phpxmlAbstract {
	private $xmlArray;
	private $rootElement;
	private $numericPaths = array();
	private $preserveCase = false;
	protected $a2p = null;
	
	private $tags = array();
	private $attributes = array();
	
	//=================================================================================
	/**
	 * The constructor.
	 */
	public function __construct($rootElement="main", array $xmlns=NULL, $preserveCase=false) {
		//check to ensure there's a real element.
		if(!strlen($rootElement)) {
			//Give it a default root element.
			$rootElement = "main";
		}
		
		if(is_bool($preserveCase)) {
			$this->preserveCase = $preserveCase;
		}
		
		//set the root element
		if(!$this->preserveCase) {
			$rootElement = strtoupper($rootElement);
		}
		$this->rootElement = $rootElement;
		
		//create our internal data structure using arrayToPath{}.
		parent::__construct();
		
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
		
		if(!$this->preserveCase) {
			$path = strtoupper($path);
		}
		$path = $this->fix_path($path);
		
		$this->tags[$path] = $value;
		$this->add_attribute($path, $attributes);
		
	}//end add_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Add attributes to the tag specified by $path.
	 */
	public function add_attribute($path, array $attributes=null) {
		if(preg_match('/^\/'. $this->rootElement .'$/', $path)) {
			$path = $this->rootElement;
		}
		else {
			$path = $this->fix_path($path);
		}
		
		if(is_null($attributes) || !count($attributes)) {
			//nothing there...
			if(isset($this->attributes[$path])) {
				unset($this->attributes[$path]);
			}
		}
		else {
			$this->attributes[$path] = $attributes;
		}
		
	}//end add_attribute()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * DEPRECATED
	 */
	public function verify_path() {
		//TODO: remove this unused method.
		trigger_error(__METHOD__ .": deprecated");
	}//end verify_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates an XML string based upon the current internal array structure.
	 */
	public function create_xml_string($addXmlVersion=FALSE, $addEncoding=FALSE) {
		$this->initialize_a2p();
		$xmlBuilder = new cs_phpxmlBuilder($this->a2p->get_data(), $this->preserveCase);
		$retval = $xmlBuilder->get_xml_string($addXmlVersion, $addEncoding);
		return($retval);
		
	}//end create_xml_string()
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
	private function fix_path($path) {
		if(!$this->preserveCase) {
			$path = strtoupper($path);
		}
		$path = preg_replace('/\/+/', '/', $path);
		
		$bits = $this->explode_path($path);
		
		if(preg_match('/^\//', $path)) {
			/*  absolute path: first item MUST be root element, followed by 0 -- a higher number 
			 *	would indicate multiple root elements.
			 */
			if(preg_match('/^\/'. $this->rootElement .'\//', $path)) {
				array_shift($bits);
				if(preg_match('/^\/'. $this->rootElement .'\/0\//', $path)) {
					array_shift($bits);
				}
			}
			else {
				throw new exception(__METHOD__ .": absolute paths must start with rootElement (". $this->rootElement ."), " .
						"and any numeric indexed directly following it MUST be zero (path: ". $path . ")");
			}
		}
		
		/* the key::: each tag should have a number in the path beyond it.  So "/path/to/something" becomes 
		 *	"/path/0/to/0/something/0", but "/path/to/1/something" must become "/path/0/to/1/something/0" 
		 *	(instead of "/path/0/to/0/1/0/something/0" by automatically adding a 0 to each bit)
		 *
		 * The index handled should ALWAYS be a tag: should the next index be numeric, it will be skipped.
		 */
		
		//this array will be transformed into a path again later.
		$newBits = array(
			0	=> $this->rootElement,
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
		
		$path = $this->reconstruct_path($newBits);
		
		return($path);
	}//end fix_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Takes an array created by explode_path() and reconstitutes it into a proper path.
	 */
	private function reconstruct_path(array $pathArr) {
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
	private function explode_path($path) {
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
	 * DEFUNCT.
	 */
	public function set_tag_as_multiple($path) {
		//TODO: remove this defunct method
		trigger_error(__METHOD__ .": method is defunct");
	}//end set_tag_as_multiple()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates a blank tag: use of this is deprecated and may be removed in 
	 * the future.
	 */
	public function create_path($path) {
		$retval = $this->add_tag($path,null,null);
		return($retval);
	}//end create_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Like add_tag() except that $path has numeric sub-indexes, & the data to be added
	 * can be added as the next index (kinda like setting $array[] = $dataArr).  
	 * 
	 * EXAMPLE: if multiple "songs" are beneath "/main/songs", call it like this:
	 * $myArr = array
	 * (
	 * 		'first'	=> array
	 * 		(
	 * 			'title'		=> 'first title',
	 * 			'artist'	=> 'Magic Man'
	 * 		),
	 * 		'second'	=> array
	 * 		(
	 * 			'title'		=> 'second title',
	 * 			'artist'	=> 'Another ARtist'
	 * 		)
	 * );
	 * $xml->add_tag_multiple('/main/songs', $myArr[0]);
	 * $xml->add_tag_multiple('/main/songs', $myArr[1]);
	 */
	public function add_tag_multiple() {
		//TODO: re-implement this...
		throw new exception(__METHOD__ .": NOT IMPLEMENTED YET");
		
		//loop through, finding all paths that start with the given one.  Find the 
		//	highest referenced index at the end of that path, then create a new tag 
		//	along that path but implement the given index by one.
		
		foreach($this->tags as $i=>$v) {
			
		}
		
		
		$path = $this->fix_path($path);
		
		
		$bits = $this->explode_path($path);
		$lastBit = $bits[(count($bits)-1)];
		
		if(is_numeric($lastBit)) {
			throw new exception(__METHOD__ .": cannot set path as numeric if part of path (". $lastBit .") is numeric");
		}
		else {
			$path = $path .'/';
			
			foreach($this->tags as $path=>$subData) {
				if(preg_match()) {
					
				}
			}
		}
		
		
		//set a default value.
		$retval = NULL;
		
		//check to see if it's already a numeric path.
		if(isset($this->numericPaths[$path])) {
			//good to go: pull the data that already exists.
			$myData = $this->a2p->get_data($path);
			
			//set the tagData array...
			$tagData = array();
			
			//if there's attributes for the main tag, set 'em now.
			$tagData['type'] = 'open';
			if(!is_null($attributes)) {
				//set it.
				$tagData['attributes'] = $attributes;
			}
			
			if(is_array($data)) {
				//loop through $dataArr & create tags for each of the indexes.
				foreach($data as $tagName=>$value) {
					//create the tag.
					$myTag = $this->add_tag($tagName, $value);
					$tagData = array_merge($tagData, $myTag);
					
				}
			}
			else {
				//it's just data, meaning it's the VALUE.
				if(!is_null($data) && strlen($data)) {
					$tagData['value'] = $data;
				}
				$tagData['type'] = 'complete';
			}
			
			//now add the tag as a numeric index to the existing data.
			$retval = count($myData);
			$myData[] = $tagData;
			
			//now set the data into our array.
			$this->a2p->set_data($path, $myData);
		}
		else {
			//it's not already a numeric path.  DIE.
			throw new exception(__METHOD__ ."() attempted to add data to non-numeric path ($path)");
		}
		
		return($retval);
	}//end add_tag_multiple()
	//=================================================================================
	
	
	
	//=================================================================================
	public function rename_root_element() {
		trigger_error(__METHOD__ .": DEPRECATED");
	}//end rename_root_element()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Calls $this->a2p->get_data($path).  Just a wrapper for private data.
	 */
	public function get_data($path=NULL) {
		
		$path = $this->fix_path($path);
		$this->initialize_a2p();
		
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
		//TODO: need to be able to re-populate $this->tags & $this->attributes
		throw new exception(__METHOD__ .": RE-IMPLEMENT THIS");
		$data = $obj->get_tree();
		$this->xmlArray = $data;
		$this->a2p = new cs_arrayToPath($data);
		
		$x = array_keys($this->a2p->get_data(NULL));
		
		if(count($x) > 1) {
			throw new exception(__METHOD__ .": too many root elements");
		}
		else {
			$this->rootElement = $x[0];
		}
	}//end load_xmlparser_data()
	//=================================================================================
	
	
	
	//=================================================================================
	public function remove_path($path) {
		//TODO: Fix this (it deletes WAY TOO MUCH)
		throw new exception(__METHOD__ .": fix me!");
		$path = $this->fix_path($path);
		foreach($this->tags as $i=>$v) {
			$regexSafePath = preg_replace('/\//', '\\/', $i);
			if(preg_match('/^'. $regexSafePath .'/', $i)) {
				unset($this->tags[$i]);
			}
		}
		foreach($this->attributes as $i=>$v) {
			$regexSafePath = preg_replace('/\//', '\\/', $i);
			if(preg_match('/^'. $regexSafePath .'/', $i)) {
				unset($this->attributes[$i]);
			}
		}
	}//end remove_path();
	//=================================================================================
	
	
	
	//=================================================================================
	private function initialize_a2p() {
		$this->a2p = new cs_arrayToPath(array());
		foreach($this->tags as $path=>$value) {
			if(!is_null($value) && strlen($value)) {
				$this->a2p->set_data($path .'/value', $value);
				$this->a2p->set_data($path .'/type', "complete");
			}
			else {
				$this->a2p->set_data($path, null);
			}
		}
		foreach($this->attributes as $path=>$attribs) {
			$this->a2p->set_data($path .'/attributes', $attribs);
		}
		
	}//end initialize_a2p()
	//=================================================================================
}//end xmlCreator{}
?>
