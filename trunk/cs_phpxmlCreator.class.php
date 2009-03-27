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
 * 
 * PATH CASE:
 * 	Because of the way PHP processes XML trees, regular tags are stored in UPPER CASE.  Attributes and
 * 	values (the data between open & close tags) are stored in lowercase.  Any paths given will be 
 * 	automatically changed to UPPER case.
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
 * (forthcoming)
 */

require_once(dirname(__FILE__) ."/cs_phpxml.abstract.class.php");
require_once(dirname(__FILE__) ."/cs_phpxmlBuilder.class.php");

class cs_phpxmlCreator extends cs_phpxmlAbstract {
	private $rootElement;
	private $numericPaths = array();
	private $paths=array();
	private $attributes = array();
	
	//=================================================================================
	/**
	 * The constructor.	
	 */
	public function __construct($rootElement="main", array $xmlns=NULL) {
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
		
		//TODO: check to see if the given path is part of a "multiples" (i.e. in $this->numericPaths) path.
		$this->paths[$path] = $value;
		if(!is_null($attributes)) {
			cs_debug_backtrace(1);
			exit;
			$this->add_attribute($path, $attributes);
		}
		
	}//end add_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Add attributes to the tag specified by $path.
	 */
	public function add_attribute($path, array $attributes) {
//		if($this->path_exists($path)) {
			if(is_array($this->attributes[$path])) {
				$this->attributes[$path] = array_merge($this->attributes[$path], $attributes);
			}
			else {
				$this->attributes[$path] = $attributes;
			}
//		}
//		else {
//			$this->gf->debug_print(__METHOD__ .": about to throw an exception... here's the backtrace::: ". htmlentities(cs_debug_backtrace(0)));
//			throw new exception(__METHOD__ .": attempted to add attribute to non-existent path (". $path .")");
//		}
		
	}//end add_attribute()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates an XML string based upon the current internal array structure.
	 */
	public function create_xml_string($addXmlVersion=FALSE) {
		$data2Load = $this->a2p->get_data();
		if(is_array($data2Load) && count($data2Load)) {
			$xmlBuilder = new cs_phpxmlBuilder($this->a2p->get_data());
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
	 * Break the path into bits, and fix the case of each tag to UPPER, except for any
	 * reserved words.
	 */
	private function fix_path($path) {
		$path = preg_replace('/\/{2,}/', '/', $path);
		$path = strtoupper($path);
		
		//break the path into an array.
		$pathArr = $this->explode_path($path);
		
		//check if the first element is our root element: if not, add it.
		if($pathArr[0] !== $this->rootElement) {
			array_unshift($pathArr, $this->rootElement);
		}
		
		//now reconstruct the path.
		$path = $this->reconstruct_path($pathArr);
		
		return($path);
	}//end fix_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Takes an array created by explode_path() and reconstitutes it into a proper path.
	 * 
	 * TODO: this is basically the same as cs_phpxmlAbstract::path_from_array(); consolidate.
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
	/**
	 * The tag is set as having multiple indexes below it, so they're not parsed as numeric
	 * tags...
	 */
	public function set_tag_as_multiple($path) {
		//add this path to our internal array of numeric paths.
		$this->numericPaths[$path]++;
	}//end set_tag_as_multiple()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates all intermediary tags for the given path.  The final tag is assumed to be
	 * complete.
	 */
	public function create_path($path) {
		
		$retval = $this->add_tag($path,null);
		
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
	public function add_tag_multiple($path, array $data, array $attributes=NULL) {
		
		try {
			$this->create_path($path);
			$this->set_tag_as_multiple($path);
			
			foreach($data as $tagSubPath=>$tagValue) {
				$myPath = $path .'/'. $tagSubPath;
				if(is_array($attributes) && isset($attributes[$tagSubPath])) {
					$retval = $this->add_tag($tagSubPath, $tagValue, $attributes[$tagSubPath]);
				}
				else {
					$retval = $this->add_tag($tagSubPath, $tagValue);
				}
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": caught exception: ". $e->getMessage());
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
		//TODO: check to ensure paths are set properly, or are purposely devoid of rootElement.
		$this->rootElement = strtoupper($newName);
	}//end rename_root_element()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Calls $this->a2p->get_data($path).  Just a wrapper for private data.
	 */
	public function get_data($path=NULL) {
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
		
		//dump arrayToPath data from the given object into our internal one.
//		$obj->update_a2p($this->a2p);
		$a2p = new cs_arrayToPath(array());
		$obj->update_a2p($a2p);
		
		//reset internal "paths" (this will be systematically reset when creating new tags & such).
		$this->paths = array();
		$validPaths = $a2p->get_valid_paths();
		
		$this->numericPaths = $obj->get_path_multiples();
		
		$this->gf->debug_print(htmlentities($this->gf->debug_print($obj,0,1)));
		exit;
		
		foreach($validPaths as $i=>$path) {
			$bits = $this->explode_path($path);
			
			if($bits[count($bits)-2] == 'attributes') {
				#$this->gf->debug_print(__METHOD__ .": found attributes on (". $path .")::: ". htmlentities($this->gf->debug_print($a2p->get_data($path),0,1)));
				
				$attrName = array_pop($bits);
				array_pop($bits); //drop the "attributes" part.
				$attrPath = $this->path_from_array($bits);
				
				#$this->gf->debug_print(__METHOD__ .": creating path (". $attrPath .")");
				
				#$this->create_path($attrPath);
				$this->add_attribute($attrPath, array($attrName=>$a2p->get_data($path)));
				
			}
			elseif($bits[count($bits)-1] == 'value') {
				#$this->gf->debug_print(__METHOD__ .": found value on (". $path .")::: ". htmlentities($a2p->get_data($path)));
				
				array_pop($bits); //remove the "value" part from the path.
				$myPath = $this->reconstruct_path($bits);
				$this->add_tag($myPath, $a2p->get_data($path));
			}
		}
		
		//now, for the sake of testing, load everything into our internal a2p object.
		
		$this->gf->debug_print($this->numericPaths);
		exit;
		
		foreach($this->paths as $p=>$v) {
			$data2Set = array(
				'type'	=> "complete",
				'value'	=> $v
			);
			
			//break the path down & create types in each.
			$bits = explode('/', $p);
			if(!strlen($bits[0])) {
				array_shift($bits);
			}
			$newPath = "";
			foreach($bits as $chunk) {
				$newPath .= '/'. $chunk;
				
				$data = $this->a2p->get_data($newPath);
				if(!isset($this->numericPaths[$newPath]) && !isset($data['type'])) {
					$data['type'] = 'open';
					
					$this->gf->debug_print(__METHOD__ .": originalPath=(<b>". $p ."</b>), setting type on path=(". $newPath .")");
					$this->a2p->set_data($newPath, $data);
				}
			}
			
			$this->a2p->set_data($p, $data2Set);
		}
		
		
		#$this->gf->debug_print($this->a2p);
		#exit;
		
		foreach($this->attributes as $p=>$a) {
			$oldData = $this->a2p->get_data($p);
			$setThis = array('attributes' => $a);
			if(is_array($oldData)) {
				$oldData['attributes'] = $a;
				$setThis = $oldData;
			}
			$this->a2p->set_data($p, $setThis);
		}
		
		
		#$this->gf->debug_print($this->a2p->get_valid_paths());
		
		#$this->gf->debug_print(htmlentities($this->gf->debug_print($this,0)));
//		exit;
		
	}//end load_xmlparser_data()
	//=================================================================================
	
	
	
	//=================================================================================
	public function remove_path($path) {
		throw new exception(__METHOD__ .": fix me!!!!");
		if(!is_null($path)) {
			$this->a2p->unset_data($path);
		}
		else {
			throw new exception(__METHOD__ .": invalid path given (". $path .")");
		}
	}//end remove_path();
	//=================================================================================
	
	
	
	//=================================================================================
	protected function path_exists($path) {
		
		//TODO: consider handling requests for paths within numeric indexes (i.e. where at tag is used multiple times)
		if(strlen($path)) {
			if(is_numeric(array_search($this->fix_path($path), $this->paths))) {
				$retval = true;
			}
			else {
				$retval = false;
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid path (". $path .")");
		}
		
		return($retval);
	}//end path_exists()
	//=================================================================================
}//end xmlCreator{}
?>
