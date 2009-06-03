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
	private $rootElement;
	private $numericPaths = array();
	private $paths=array();
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
		if(strlen($this->fix_path(path))) {
			//fix the path to contain proper indexes.
			$path = $this->fix_path($path);
			$this->paths[$path] = $value;
			
			if(is_array($attributes) && count($attributes)) {
				$this->add_attribute($path, $attributes);
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid path string (". $path .")");
		}
		
	}//end add_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Add attributes to the tag specified by $path.
	 */
	public function add_attribute($path, array $attributes) {
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
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
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
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
	private function fix_path($path) {
		
		//clean out so the path is only alphanumeric and a select few non-alphanumerics.
		//TODO: check the RFC to determine which characters are valid.
		if(strlen($path)) {
			$path = preg_replace("/[^A-Za-z0-9:\/\-\._]/", '', $path);
		}
		if(strlen($path) > 1) {
			
			$path = strtoupper($path);
			if(preg_match("/\//", $path)) {
				//it has slashes, lets assume all is good.
				$path = preg_replace('/^\//', '', $path);
			}
			
			//prepend root element as needed.
			if(!preg_match('/^'. $this->rootElement .'/', $path)) {
				$path = '/'. $this->rootElement .'/'. $path;
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid length of path (". $path .")");
		}
		
		//final deal: let's add numbers to the path as required.
		$originalPath = $path;
		$bits = explode('/', $path);
		
		//strip off the first index (rootElement) and it's number (if present), which 
		//	should always be zero (valid XML can have only one root element).
		$test = array_shift($bits);
		if($test !== $this->rootElement) {
			throw new exception(__METHOD__ .": path (". $path .") does not begin with root element");
		}
		if(is_numeric($bits[0])) {
			$test = array_shift($bits);
			if($test != 0) {
				throw new exception(__METHOD__ .": found invalid numeric index under root (". $test ."):"
					." multiple root elements not allowed in an XML document");
			}
		}
		
		//now add numeric indexes as needed.
		$tag2Index = array();
		
		//only  (i.e. in "ROOT/0/PATH/1/TO/0/HEAVEN/0", the non numerics are in 0, 2, 4, and 6
		//	0=ROOT, 2=PATH, 4=TO, 6=HEAVEN [i.e. ROOT/PATH/TO/HEAVEN])
		/*
		 * FINAL ARRAY:::
		 * 		$tag2Index = array (
		 * 			[ROOT]		=> 0,
		 * 			[PATH]		=> 1,
		 * 			[TO]		=> 0,
		 * 			[HEAVEN]	=> 0
		 * 		);
		 */
		$lastIndexNumeric=false;
		//foreach($bits as $i=>$tagOrIndex) {
		for($i=0; $i<count($bits); $i++) {
			$myPathIndex = 0;
			$checkNext = $bits[($i +1)];
			if(isset($bits[$i]) && (!is_numeric($bits[$i]) || preg_match('/^[A-Z]/', $bits[$i]))) {
				$tagOrIndex = $bits[$i];
				//okay, we've got a text string: check if the next item is a number...
				if(is_numeric($checkNext)) {
					//user explicitly set numeric in path (i.e. "/ROOT/PATH/1/TO/HEAVEN")
					$myPathIndex = $checkNext;
					$lastIndexNumeric=true;
					$i++;
					
					//TODO: should we add this to the "path multiples" somewhere?
				}
				$tag2Idex[$tagOrIndex] = $myPathIndex;
			}
			else {
				throw new exception(__METHOD__ .": while walking path, attempted to access invalid "
					."index (". $i .") [starting at zero] or found invalid location of numeric "
					."(". $bits[$i] .")");
			}
		}
		
		if(is_array($tag2Index) && count($tag2Index)) {
			$newPath = '';
			foreach($tag2Index as $tagName => $tagIndex) {
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
	
	
	
	//=================================================================================
	/**
	 * Takes an array created by explode_path() and reconstitutes it into a proper path.
	 * 
	 * TODO: this is basically the same as cs_phpxmlAbstract::path_from_array(); consolidate.
	 */
	private function reconstruct_path(array $pathArr) {
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
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
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
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
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
		
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
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
		
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
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
		//TODO: check to ensure paths are set properly, or are purposely devoid of rootElement.
		$this->rootElement = strtoupper($newName);
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
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
		
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
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
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
throw new exception(__METHOD__ ." - line #". __LINE__ .": NEEDS TO BE FINISHED... BACKTRACE: ". cs_debug_backtrace(0));
		
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
