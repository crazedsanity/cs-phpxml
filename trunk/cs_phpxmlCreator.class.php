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
 * Methods to create XML that's parseable by xmlBuilder{}.  Eliminates the need for manually creating
 * a massive array, just to feed it into xmlBuilder: it's assumed that the XML is being built in-line,
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
 * 	arrayToPath{} facilitates referencing items within an array using a path: in the example XML (above),
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

class cs_phpxmlCreator extends cs_xmlAbstract {
	private $xmlArray;
	private $lastTag;
	private $rootElement;
	private $a2pObj;
	private $reservedWords = array('attributes', 'type', 'value');
	private $tagTypes = array('open', 'complete');
	private $numericPaths = array();
	
	//=================================================================================
	/**
	 * The constructor.
	 */
	public function __construct($rootElement="main", array $xmlns=NULL) {
		$this->get_version();
		//check to ensure there's a real element.
		if(!strlen($rootElement)) {
			//Give it a default root element.
			$rootElement = "main";
		}
		
		//set the root element
		$this->rootElement = strtoupper($rootElement);
		
		//create the basic XML structure here.
		$xmlArray = $this->create_tag($this->rootElement, array(), $xmlns, 'open');
		
		//create our internal data structure using arrayToPath{}.
		$this->a2pObj = new arrayToPath($xmlArray);
		
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
		//get the final tag name, and set the path to be less that.
		$pathArr = $this->explode_path($path);
		$tagName = array_pop($pathArr);
		$path = $this->reconstruct_path($pathArr);
		
		//make sure the path is correct.
		$path = $this->verify_path($path);
		
		//build a tag as requested.
		$myTag = $this->create_tag($tagName, $value, $attributes);
		
		//check to see if there's already data on this path.
		$myData = $this->a2pObj->get_data($path);
		if(is_array($myData)) {
			//set the type as "open".
			$myData['type'] = 'open';
			
			//add the new path.
			$myData = array_merge($myData, $myTag);
			$this->a2pObj->set_data($path, $myData);
		}
		else {
			//not an array... how can this be?
			throw new exception(__METHOD__ ."(): found unclean path that passed verification ($path)");
		}
	}//end add_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Add attributes to the tag specified by $path.
	 */
	public function add_attribute($path, array $attributes) {
		//make sure they're not trying to create attributes within attributes.
		if(preg_match('/attributes/', $path)) {
			//dude, that is just not cool.
			throw new exception(__METHOD__ ."(): cannot add attributes within attributes.");
		}
		
		//verify the path (creates intermediate tags as needed).
		$path = $this->verify_path($path);
		$path = $this->create_list($path, 'attributes', '/');
		
		//add the attribute.
		$this->a2pObj->set_data($path, $attributes);
		
	}//end add_attribute()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Verifies that all tags within the given path have been created properly.  Any
	 * tags along the path will be created if they don't already exist.  The last
	 * portion of the path is assumed to be the final tag name: the "type" of that
	 * tag won't be changed, but those preceding it will.
	 */
	public function verify_path($path, $justCheckIt=FALSE) {
		//fix the path's case.
		$path = $this->fix_path($path);
		
		//now, let's explode the path, & go through each bit of it, making sure the tags
		//	are setup properly.
		$pathArr = $this->explode_path($path);
		
		//check to see if the path exists at all.
		$checkData = $this->a2pObj->get_data($path);
		if($justCheckIt) {
			if(!is_array($checkData)) {
				//it's NOT an array: return NULL to let 'em know.
				$path = NULL;
			}
		}
		elseif(!is_array($checkData)) {
			throw new exception(__METHOD__ ."(): found invalid path at ($path)");
		}		
		elseif(count($pathArr) > 1) {
			$lastTag = array_pop($pathArr);
			
			$currentPath = "/";
			$lastPath = $currentPath;
			foreach($pathArr as $index=>$tagName) {
				//okay, set the current path.
				$currentPath = $this->create_list($currentPath, $tagName, '/');
				
				$myData = $this->a2pObj->get_data($currentPath);
				
				$myType = $myData['type'];
				if($myType !== 'open' && !isset($this->numericPaths[$currentPath])) {
					//throw an exception, so they know we got a boo-boo.	
					throw new exception(__METHOD__ ."(): missing type on currentPath=($currentPath), path=($path)");
				}
			}
			
			//now, let's check to see if there's already a tag in the final path ($currentPath) with
			//	the same name as $lastTag.
			$finalData = $this->a2pObj->get_data($currentPath);
		}
		
		return($path);
	}//end verify_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates an XML string based upon the current internal array structure.
	 */
	public function create_xml_string($addXmlVersion=FALSE) {
		$xmlBuilder = new xmlBuilder($this->a2pObj->get_data());
		$retval = $xmlBuilder->get_xml_string($addXmlVersion);
		return($retval);
		
	}//end create_xml_string()
	//=================================================================================
	
	
	
	//=================================================================================
	private function create_tag($tagName, $value=NULL, array $attributes=NULL, $type=NULL) {
		//upper-case the tagname.
		$tagName = strtoupper($tagName);
		
		//set a default type for the tag, if none defined.
		if(is_null($type) || !in_array($type, $this->tagTypes)) {
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
		
		//break the path into an array.
		$pathArr = $this->explode_path($path);
		
		//fix each tag's case.
		$newPathArr = array();
		foreach($pathArr as $index=>$tagName) {
			//fix each tag in the path.
			$newPathArr[] = $this->fix_tagname($tagName);
		}
		
		//check if the first element is our root element: if not, add it.
		if($newPathArr[0] !== $this->rootElement) {
			array_unshift($newPathArr, $this->rootElement);
		}
		
		//now reconstruct the path.
		$path = $this->reconstruct_path($newPathArr);
		
		return($path);
	}//end fix_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Changes the case of the given tagName, upper-casing all non-reserved words.
	 */
	private function fix_tagname($tagName) {
		//check to see if the tag is reserved.
		if(in_array($tagName, $this->reservedWords)) {
			//lower it's case.
			$tagName = strtolower($tagName);
		}
		else {
			//not reserved: should be upper-case.
			$tagName = strtoupper($tagName);
		}
		
		return($tagName);
	}//end fix_tagname()
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
	 * The tag is set as having multiple indexes below it, so they're not parsed as numeric
	 * tags...
	 */
	public function set_tag_as_multiple($path) {
		//get the path array.
		$path = $this->fix_path($path);
		
		//remove the "type" from that part of the array.
		$this->a2pObj->unset_data($path ."/type");
		
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
		//set a default return value.
		$retval = FALSE;
		
		if(!is_null($path) && strlen($path) > 1 && !$this->verify_path($path,TRUE)) {
			//create an array to loop through.
			$path = $this->fix_path($path);
			$pathArr = $this->a2pObj->explode_path(strtoupper($path));
			
			//rip the final tag out.
			$finalTag = array_pop($pathArr);
			
			if(count($pathArr) > 0) {
				//now loop it.
				$currentPath = "/";
				foreach($pathArr as $key=>$tagName) {
					//check data in the current path...
					$pathOk = $this->a2pObj->get_data($currentPath);
					
					$tagPath = $this->create_list($currentPath, $tagName, '/');
					if(!strlen($pathOk[$tagName]['type']) && !isset($this->numericPaths[$tagPath])) {
						//update the current path as needed.
						$this->add_tag($tagPath);
					}
					
					//update the current path.
					$currentPath = $this->create_list($currentPath, $tagName, '/');
				}
					
				//set the final tag...
				$finalPath = $this->create_list($currentPath, $finalTag, '/');
				$this->add_tag($finalPath);
			}
			else {
				//Setting the root item???  Kill it!
				throw new exception(__METHOD__ ."(): attempted to create root element");
			}
		}
		
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
	public function add_tag_multiple($path, $data, array $attributes=NULL) {
		$path = $this->fix_path($path);
		//set a default value.
		$retval = NULL;
		
		//check to see if it's already a numeric path.
		if(isset($this->numericPaths[$path])) {
			//good to go: pull the data that already exists.
			$myData = $this->a2pObj->get_data($path);
			
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
					$myTag = $this->create_tag($tagName, $value);
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
			$this->a2pObj->set_data($path, $myData);
		}
		else {
			//it's not already a numeric path.  DIE.
			debug_print($this->numericPaths);
			throw new exception(__METHOD__ ."() attempted to add data to non-numeric path ($path)");
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
		//first, change the internal "rootElement" pointer.
		$newName = strtoupper($newName);
		$oldRoot = $this->rootElement;
		$this->rootElement = $newName;
		
		//now change our array information.
		$myData = $this->a2pObj->get_data("/$oldRoot");
		$this->a2pObj->unset_data("/");
		$newData = array (
			$this->rootElement => $myData
		);
		$this->a2pObj->reload_data($newData);
		
		//update the "numericPaths" array, if there's anything in it.
		if(is_array($this->numericPaths) && count($this->numericPaths)) {
			foreach($this->numericPaths as $pathName=>$garbage) {
				//replace "/$oldRoot" with the new rootElement.
				unset($this->numericPaths[$pathName]);
				$pathName = preg_replace("/^\/$oldRoot/", "/". $this->rootElement, $pathName);
				$this->numericPaths[$pathName] = $garbage;
			}
		}
		
	}//end rename_root_element()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Calls $this->a2pObj->get_data($path).  Just a wrapper for private data.
	 */
	public function get_data($path=NULL) {
		$retval = $this->a2pObj->get_data($path);
		return($retval);
	}//end get_data()
	//=================================================================================
	
	
	//=================================================================================
	/**
	 * Takes an XMLParser object & loads data from it as the internal XML array. This 
	 * facilitates the ability to add data to existing XML.
	 */
	public function load_xmlparser_data(XMLParser $obj) {
		$data = $obj->get_tree();
		$this->xmlArray = $data;
		$this->a2pObj = new arrayToPath($data);
		
		$x = array_keys($this->a2pObj->get_data(NULL));
		
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
		if(!is_null($path)) {
			$this->a2pObj->unset_data($path);
		}
		else {
			throw new exception(__METHOD__ .": invalid path given (". $path .")");
		}
	}//end remove_path();
	//=================================================================================
}//end xmlCreator{}
?>
