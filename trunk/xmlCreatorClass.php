<?php
/*
 * Created on Dec 18, 2006
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

require_once(dirname(__FILE__) ."/xmlBuilderClass.php");
require_once(dirname(__FILE__) ."/../arrayToPathClass.php");

class xmlCreator
{
	private $xmlArray;
	private $lastTag;
	private $rootElement;
	private $arrayToPath;
	private $reservedWords = array('attributes', 'type', 'value');
	private $tagTypes = array('open', 'complete');
	
	//=================================================================================
	/**
	 * The constructor.
	 */
	public function __construct($rootElement="main", array $xmlns=NULL)
	{
		//check to ensure there's a real element.
		if(!strlen($rootElement))
		{
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
	public function add_tag($path, $value=NULL, array $attributes=NULL)
	{
		//make sure the path is correct.
		$path = $this->verify_path($path);
		
		$pathArr = $this->explode_path($path);
		$tagName = array_pop($pathArr);
		
		//set the path to be without the tagname.
		$path = $this->reconstruct_path($pathArr);
		
		//build a tag as requested.
		$myTag = $this->create_tag($tagName, $value, $attributes);
		
		//check to see if there's already data on this path.
		$myData = $this->a2pObj->get_data($path);
		if(!is_array($myData))
		{
			//make it an array.
			$myData = array();
		}
		$myData = array_merge($myData, $myTag);
		
		$this->a2pObj->set_data($path, $myData);
	}//end add_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Add attributes to the tag specified by $path.
	 */
	public function add_attribute($path, array $attributes)
	{
		//make sure they're not trying to create attributes within attributes.
		if(preg_match('/attributes/', $path))
		{
			//dude, that is just not cool.
			throw new exception("xmlCreator{}->add_attribute(): cannot add attributes within attributes.");
		}
		
		//verify the path (creates intermediate tags as needed).
		$path = $this->verify_path($path);
		$path = create_list($path, 'attributes', '/');
		
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
	private function verify_path($path)
	{
		//fix the path's case.
		$path = $this->fix_path($path);
		
		//now, let's explode the path, & go through each bit of it, making sure the tags
		//	are setup properly.
		$pathArr = $this->explode_path($path);
		
		if(count($pathArr) > 1)
		{
			$lastTag = array_pop($pathArr);
			
			$currentPath = "/";
			foreach($pathArr as $index=>$tagName)
			{
				//okay, set the current path.
				$currentPath = create_list($currentPath, $tagName, '/');
				
				$myData = $this->a2pObj->get_data($currentPath);
				
				$myType = $myData['type'];
				if($myType !== 'open')
				{
					//change the type of this tag to open, since it's got at least one tag beneath.
					$typePath = create_list($currentPath, 'type', '/');
					$this->a2pObj->set_data($typePath, 'open');
				}
			}
			
			//now, let's check to see if there's already a tag in the final path ($currentPath) with
			//	the same name as $lastTag.
			$finalData = $this->a2pObj->get_data($currentPath);
			
			if(isset($finalData[$lastTag]))
			{
				//TODO: somethign with setting it as multiple... or something.
				debug_print(" ----- on $currentPath, $tagName already exists.");
			}
		}
		
		return($path);
	}//end verify_path()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates all tags within the given path that don't already exist.
	 */
	private function create_intermediate_tags($path)
	{
	}//end create_intermediate_tags()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates an XML string based upon the current internal array structure.
	 */
	public function create_xml_string()
	{
		$xmlBuilder = new xmlBuilder($this->a2pObj->get_data());
		$retval = $xmlBuilder->get_xml_string();
		return($retval);
		
	}//end create_xml_string()
	//=================================================================================
	
	
	
	//=================================================================================
	private function create_tag($tagName, $value=NULL, array $attributes=NULL, $type=NULL)
	{
		//set a default type for the tag, if none defined.
		if(is_null($type) || !in_array($type, $this->tagTypes))
		{
			//setting a default type.
			$type = 'complete';
		}
		
		//setup the tag's structure.
		$myTag = array
		(
			$tagName	=> array(
				'type'		=> $type
			)
		);
		
		//check to see that we've got what appears to be a valid attributes array.
		if(is_array($attributes))
		{
			//looks good.  Add the attributes to our array.
			$myTag[$tagName]['attributes'] = $attributes;
		}
		
		//if they've got a value, add it to the array as well.
		if(!is_null($value) && strlen($value) && is_string($value))
		{
			//add the value.
			$myTag[$tagName]['value'] = $value;
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
	private function fix_path($path)
	{
		
		//break the path into an array.
		$pathArr = $this->explode_path($path);
		
		//fix each tag's case.
		$newPathArr = array();
		foreach($pathArr as $index=>$tagName)
		{
			//fix each tag in the path.
			$newPathArr[] = $this->fix_tagname($tagName);
		}
		
		//check if the first element is our root element: if not, add it.
		if($newPathArr[0] !== $this->rootElement)
		{
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
	private function fix_tagname($tagName)
	{
		//check to see if the tag is reserved.
		if(in_array($tagName, $this->reservedWords))
		{
			//lower it's case.
			$tagName = strtolower($tagName);
		}
		else
		{
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
	private function reconstruct_path(array $pathArr)
	{
		//setup the path variable.
		$path = "";
		foreach($pathArr as $index=>$tagName)
		{
			//add this tag to the current path.
			$path = create_list($path, $tagName, '/');
		}
		
		//add the leading '/'.
		$path = '/'. $path;
		
		//give 'em what they want.
		return($path);
	}//end reconstruct_path()
	//=================================================================================
	
	
	
	//=================================================================================
	private function explode_path($path)
	{
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
	public function set_tag_as_multiple($path)
	{
		//get the path array.
		$path = $this->fix_path($path);
		
		//remove the "type" from that part of the array.
		$this->a2pObj->unset_data($path ."/type");
	}//end set_tag_as_multiple()
	//=================================================================================
}//end xmlCreator{}
?>
