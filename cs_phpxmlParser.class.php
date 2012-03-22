<?php
/*
 * Created on Nov 14, 2006
 * 
 * SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 * 
 * 
 * Built to convert an XML document into a multi-dimensional array.
 * 
 * 
 * *********** EXAMPLE ***********
 * 
 * Original file contents:
 * <test xmlns="http://your.domain.com/stuff.xml">
 * 		<indexOne>hello</indexOne>
 * 		<my_single_index testAttribute="hello" second_attribute="Another, for testing" />
 * 		<multiple_items>
 * 			<item>1</item>
 * 			<item>2</item>
 * 		</multiple_items>
 * </test>
 * 
 * Would create an array like:
 * array(
 * 	"test" => array(
 * 		0 => array(
 * 			"__attributes" => array(
 * 				"xmlns" => "http://your.domain.com/stuff.xml"
 * 			),
 * 			"indexOne" => array(
 * 				0 => array(
 * 					"__data__" => "hello"
 * 				)
 * 			),
 * 			"my_single_index" => array(
 * 				0 => array(
 * 					"__attribs" => array(
 * 						"testAttribute" => "hello",
 * 						"second_attribute" => "Another, for testing"
 * 					)
 * 				)
 * 			),
 * 			"multiple_items" => array(
 * 				0 => array(
 * 					"item" => array(
 * 						0 => array(
 * 							"__data__" => 1
 * 						),
 * 						1 => array(
 * 							"__data__" => 2
 * 						)
 * 					)
 * 				)
 * 			)
 * 		)
 * 	)
 * );
 *  
 * All data is retrieve using "paths" (similar to xpath).  For instance, to retrieve the value of the second index beneath 
 * the "multiple_items" tag, the path would be:
 * 		"/test/0/multiple_items/0/item/1"
 * 
 */


class cs_phpxmlParser extends cs_phpxmlAbstract {

	private $data;					// Input XML data buffer
	private $currentPath = null;
	private $closedPaths = array();
	private $xmlVals = array();
	private $xmlIndex = array();
	private $isInitialized = false;
	
	protected $a2p = null;
	protected $preserveCase=false;	// If it is set to boolean false, tag and attribute names will be UPPERCASED.
	
	const dataIndex		= cs_phpxmlCreator::dataIndex;
	const attribIndex	= cs_phpxmlCreator::attributeIndex;
	
	//=================================================================================
	/**
	 * CONSTRUCTOR: Read in XML on object creation, via raw data (string), stream, filename, or URL.
	 */
	function __construct($data_source, $preserveCase=false) {
		parent::__construct(array());
		if($data_source === 'unit_test') {
			//this is only a test... don't do anything.
			$this->isTest = TRUE;
		}
		else {
			$this->get_version();
			if(is_bool($preserveCase)) {
				$this->preserveCase=$preserveCase;
			}
			$this->data = '';
			if(preg_match('/^</', $data_source)) {
				$this->data = $data_source;
				$this->get_tree();
			}
			else {
				//something went horribly wrong.
				throw new exception(__METHOD__ .": FATAL: invald data::: ". htmlentities($data_source));
			}
		}
	}//end __construct()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Pase the XML file into a verbose, flat array struct.  Then, coerce that into a 
	 * simple nested array.
	 */
	function get_tree() {
		$parser = xml_parser_create('ISO-8859-1');
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		
		//Set an option to NOT uppercase all the tags (case sensitivity is preserved)
		if($this->preserveCase === true) {
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		}
		
		//initialize some variables, before dropping them into xml_parse_into_struct().
		$vals = array();
		$index = array();
		xml_parse_into_struct($parser, $this->data, $vals, $index); 
		xml_parser_free($parser);
		
		//sanity test: the arrays created by xml_parse_into_struct() MUST conform to a 
		//	certain pattern; if they don't, all is hopelessly lost.
		$sanityCheck=0;
		$sanityMustBe=4;
		$firstVal = $vals[0];
		$lastVal = $vals[count($vals)-1];
		$this->rootElement = $firstVal['tag'];
		if(!$this->preserveCase) {
			$this->rootElement = strtoupper($this->rootElement);
		}
		
		if($firstVal['tag'] == $lastVal['tag']) {
			$sanityCheck++;
		}
		if($lastVal['level'] == 1 && $firstVal['level'] == 1) {
			$sanityCheck++;
		}
		if($vals[$index[$this->rootElement][0]]['tag'] == $this->rootElement) {
			$sanityCheck++;
		}
		if($vals[$index[$this->rootElement][1]]['tag'] == $this->rootElement) {
			$sanityCheck++;
		}
		
		if($sanityCheck == $sanityMustBe) {
			$this->a2p = new cs_arrayToPath(array());
			$this->xmlVals = $vals;
			$this->xmlIndex = $index;
			$this->closedPaths = array();
			$this->currentPath = '/'. $this->rootElement .'/0';
			$this->build_a2p();
		}
		else {
			throw new exception(__METHOD__ .": xml_parse_into_struct() created arrays with invalid data");
		}
		$this->isInitialized = true;
		
		//retrieve the requested path.
		return($this->a2p->get_data());
	}//end get_tree()
	//=================================================================================
	
	
	
	//=================================================================================
	private function build_a2p() {
		foreach($this->xmlVals as $i=>$a) {
			$dataPath = null;
			$myData = array();
			$currentLevel = $a['level'];
			switch($a['type']) {
				case 'open':
					//add the current tag to our path.
					if($i > 0) {
						$dataPath = $this->_update_current_path($a['tag']);
					}
					else {
						$dataPath = $this->currentPath;
					}
				break;
				
				
				case 'complete':
					//set the data path by updating current path, then drop back a level: this way the 
					//	completed tag shows as a closed path AND we have the right path to store data on.
					$dataPath = $this->_update_current_path($a['tag']);
					$this->_update_current_path(null);
				break;
				
				
				case 'close':
					$this->_update_current_path(null);
				break;
			}
			if(isset($a['value'])) {
				$myData[self::dataIndex] = $a['value'];
			}
			if(isset($a['attributes'])) {
				$myData[self::attribIndex] = $a['attributes'];
			}
			
			if(count($myData)) {
				if(!is_null($dataPath) && strlen($dataPath)) {
					$this->a2p->set_data($dataPath, $myData);
				}
				else {
					throw new exception(__METHOD__ .": could not set data on invalid path (". $dataPath ."), currentPath=(". $this->currentPath .")");
				}
			}
			elseif(!count($myData) && $a['type'] == 'complete') {
				//store the blank tag.
				$this->a2p->set_data($dataPath, null);
			}
		}
	}//end build_a2p()
	//=================================================================================
	
	
	
	//=================================================================================
	/*
	 * passing null as $addThis will cause the path to go one lower (i.e. changes "/root/0/element/0" into "/root/0")
	 */
	private function _update_current_path($addThis=null) {
#$this->gfObj->debug_print(__METHOD__ .": currentPath=(". $this->currentPath ."), addThis=(". $addThis .")",1);
		$myPath = $this->currentPath;
		$bits = $this->explode_path($myPath);
		$lastIndex = array_pop($bits);
		$testPath = $this->reconstruct_path($bits);
		if(is_numeric($lastIndex)) {
			if(is_null($addThis)) {
				//remember that this path was closed!
				$this->closedPaths[$testPath] = ($lastIndex +1);
				
				//now drop that last tag off the path & set it to our current path.
				array_pop($bits);
				$myPath = $this->reconstruct_path($bits);
#$this->gfObj->debug_print(__METHOD__ .": dropping back a level, currentPath=(". $this->currentPath ."), myPath=(". $myPath .")",1);
			}
			else {
				//make sure to increment the path intelligently...
				$pathIndex = 0;
				$testPath = $this->currentPath .'/'. $addThis;
				if(isset($this->closedPaths[$testPath])) {
					//there's a closed path.  Use the stored value to determine the new path.
					$pathIndex = $this->closedPaths[$testPath];
				}
				
				//TODO: add test to ensure this path does not already exist.
				
				$bits[] = $lastIndex;
				$bits[] = $addThis;
				$bits[] = $pathIndex;
				$myPath = $this->reconstruct_path($bits);
#$this->gfObj->debug_print(__METHOD__ .": adding a level (". $addThis ."), currentPath=(". $this->currentPath ."), myPath=(". $myPath ."), testPath=(". $testPath ."), pathIndex=(". $pathIndex ."), closedPaths:::: ". $this->gfObj->debug_print($this->closedPaths,0),1);
			}
			$this->currentPath = $myPath;
		}
		else {
			throw new exception(__METHOD__ .": found non-numeric index (". $lastIndex .") when storing closed path (". $myPath ."), currentPath=(". $this->currentPath ."), addThis=(". $addThis .")");
		}
		return($this->currentPath);
	}//end _update_current_path()
	//=================================================================================
	
		//this MUST be the root element.
	
	
	//=================================================================================
	/**
	 * To get data in an XML document via a simple path, as though it were a filesystem...
	 * EXAMPLE PATH:
	 * 	"NEW-ORDER-NOTIFICATION/BUYER-SHIPPING-ADDRESS/EMAIL"
	 * 
	 * @param $path			(string) path in XML document to traverse...
	 */
	public function get_path($path=NULL, $stripTrailingZero=false) {
		$this->get_tree();
		$path = $this->fix_path($path);
		if($stripTrailingZero==true) {
			$path = preg_replace('/\/0$/', '', $path);
		}
		return($this->a2p->get_data($path));
	}//end get_path()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_root_element() {
		if(!$this->isInitialized) {
			$this->get_tree();//called so it can set rootElement
		}
		return($this->rootElement);
	}//end get_root_element()
	//=================================================================================
}

?>
