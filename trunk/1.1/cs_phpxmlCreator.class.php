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
 * <cart foo="bar">
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
	 $xml->add_tag("/cart/item/extra", null, array('location'=>"the internet"));
	 $xml->add_tag("/cart/item/1/name", "bar");
	 $xml->add_tag("/cart/item/1/value", "even more");
	 $xml->add_attribute("/cart/item/1", array('comment'=>"2"));
	 $xml->add_attribute("/cart/item/1/value", array('currency'=>"USD"));
	 $xml->add_tag("/cart/item/1/extra", null, array('location'=>"unknown"));	//faster than adding attribs later.
	 
 */


class cs_phpxmlCreator extends cs_phpxmlAbstract {
	protected $rootElement;
	protected $preserveCase = false;
	protected $a2p = null;
	
	private $tags = array();
	private $attributes = array();
	
	const dataIndex = '__data__';
	const attributeIndex = '__attribs__';
	
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
		
		if(!$this->preserveCase) {
			$this->rootElement = strtoupper($this->rootElement);
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
		
		if(preg_match('/^\/'. $this->rootElement .'/', $path)) {
			#$this->tags[$path] = $value;
			if(is_null($value)) {
				$value = "";
			}
			$this->a2p->set_data($path .'/'. self::dataIndex, $value);
			if(is_array($attributes)) {
				$this->add_attribute($path, $attributes);
			}
		}
		else {
			throw new exception(__METHOD__ .": must use full paths (". $path ."), rootElement=(". $this->rootElement .")");
		}
		
	}//end add_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Add attributes to the tag specified by $path.
	 */
	public function add_attribute($path, array $attributes=null) {
		if(preg_match('/^\/'. $this->rootElement .'$/', $path)) {
			$path = '/'. $this->rootElement .'/0';
		}
		else {
			$path = $this->fix_path($path);
		}
		
		if(!$this->preserveCase) {
			$oldArray = $attributes;
			$attributes = array();
			foreach($oldArray as $k=>$v) {
				$attributes[strtoupper($k)] = $v;
			}
			if(count($oldArray) != count($attributes)) {
				throw new exception(__METHOD__ .": cannot set multiple same-name attributes, try setting preserveCase=true");
			}
		}
		if(is_null($attributes) || !count($attributes)) {
			//nothing there...
			if(isset($this->attributes[$path])) {
				unset($this->attributes[$path]);
			}
			try {
				$this->a2p->unset_data($path .'/'. self::attributeIndex);
			}
			catch(Exception $e) {
				//no worries... I guess.
			}
		}
		else {
			$this->attributes[$path] = $attributes;
			foreach($attributes as $n=>$v) {
				if(is_null($v)) {
					$attributes[$n] = "";
				}
			}
			$this->a2p->set_data($path .'/'. self::attributeIndex, $attributes);
		}
		
	}//end add_attribute()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Creates an XML string based upon the current internal array structure.
	 */
	public function create_xml_string($addXmlVersion=FALSE, $addEncoding=FALSE) {
		$xmlBuilder = new cs_phpxmlBuilder($this->a2p->get_data(), $this->preserveCase);
		$retval = $xmlBuilder->get_xml_string($addXmlVersion, $addEncoding);
		return($retval);
		
	}//end create_xml_string()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Takes an XMLParser object & loads data from it as the internal XML array. This 
	 * facilitates the ability to add data to existing XML.
	 */
	public function load_xmlparser_data(cs_phpxmlParser $obj) {
		//TODO: need to be able to re-populate $this->tags & $this->attributes
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
	
}//end xmlCreator{}
?>
