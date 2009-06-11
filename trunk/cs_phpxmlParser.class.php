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
 * Built for PHP to programatically parse & understand data within an XML document.
 * 
 * 
 * NOTES ON CODE ORIGINS:::
 * ---------------------------------- 
 * 		Based on code found online at:
 * 		http://php.net/manual/en/function.xml-parse-into-struct.php
 * 		Author: Eric Pollmann
 * 		Released into public domain September 2003
 * 		http://eric.pollmann.net/work/public_domain/
 * ---------------------------------- 
 * 
 * *********** EXAMPLE ***********
 * 
 * Original file contents:
 * <test xmlns="http://your.domain.com/stuff.xml">
 * 		<indexOne>hello</indexOne>
 * 		<my_single_index testAttribute="hello" />
 * 		<multiple_items>
 * 			<item>1</item>
 * 			<item>2</item>
 * 		</multiple_items>
 * </test>
 * 
 * Would return:
 * 
 * array(
 * 	TEST => array(
 * 		type => 'open',
 * 		attributes => array(
 * 			xmlns => 'http://your.domain.com/stuff.xml'
 * 		)
 * 		INDEXONE => 'hello',
 * 		MY_SINGLE_INDEX = array(
 * 			type => 'complete',
 * 			
 * 		)
 * 	)
 * );
 *  
 * 
 */

require_once(dirname(__FILE__) ."/cs_phpxml.abstract.class.php");


class cs_phpxmlParser extends cs_phpxmlAbstract {

/*
 * Based on code found online at:
 * http://php.net/manual/en/function.xml-parse-into-struct.php
 * 
 * Some things to keep in mind:  
 * 	1.) all indexes that appear within the document are UPPER CASE.
 *  2.) attributes of a tag will be represented in **lower case** as "attributes":
 * 			this is done to avoid collisions, in case there's a tag with the name
 * 			of "attributes"... 
 *  3.) Anything that has a tag named "values" will be represented in the final array
 * 			by "VALUES/VALUES", as retrieved by get_path() (see "get_path()" notes).
 * 
 * TODO: implement something to take array like this class returns & put it in XML form.
 */

	private $data;			// Input XML data buffer
	private $vals;			// Struct created by xml_parse_into_struct
	private $xmlTags;
	private $xmlIndex;
	private $levelArr;
	private $childTagDepth = 0;
	private $makeSimpleTree = FALSE;
	
	private $pathIndex=0;
	protected $pathList = array();
	protected $pathMultiples=array();
	protected $paths = array();
	private $curPath=null;
	
	//=================================================================================
	/**
	 * CONSTRUCTOR: Read in XML on object creation, via raw data (string), stream, filename, or URL.
	 */
	function __construct($data_source, $data_source_type='raw') {
		parent::__construct(array());
		if($data_source === 'unit_test') {
			//this is only a test... don't do anything.
			$this->isTest = TRUE;
		}
		else {
			$this->get_version();
			$this->data = '';
			if($data_source_type == 'raw') {
				$this->data = $data_source;
			}
			elseif ($data_source_type == 'stream') {
				while (!feof($data_source)) {
					$this->data .= fread($data_source, 1000);
				}
			}
			// try filename, then if that fails...
			elseif (file_exists($data_source)) {
				$this->data = implode('', file($data_source)); 
	
			}
			// try URL.
			else {
				//something went horribly wrong.
				throw new exception(__METHOD__ .": FATAL: unable to find resource");
			}
		}
		
		$this->gf = new cs_globalFunctions;
	}//end __construct()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Pase the XML file into a verbose, flat array struct.  Then, coerce that into a 
	 * simple nested array.
	 */
	function get_tree($simpleTree=FALSE) {
		$this->makeSimpleTree = $simpleTree;
		$parser = xml_parser_create('ISO-8859-1');
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		
		//initialize some variables, before dropping them into xml_parse_into_struct().
		$vals = array();
		$index = array();
		xml_parse_into_struct($parser, $this->data, $vals, $index); 
		xml_parser_free($parser);
		
		$i = -1;
		
		$retval = $this->get_children($vals, $i);
		
		//drop the last item in the pathList if it is empty.
		if(!strlen($this->pathList[(count($this->pathList) -1)])) {
			unset($this->pathList[(count($this->pathList) -1)]);
		}
		return($retval);
	}//end get_tree()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Internal function: build a node of the tree.
	 */
	private function build_tag($thisvals, $vals, &$i, $type) {
		
		$tag = array();
		$tag['type'] = $type;
		
		if($type === 'complete') {
			// complete tag, just return it for storage in array.
			if($this->makeSimpleTree) {
				$tag = $thisvals['value'];
			}
			else {
				if(isset($thisvals['attributes'])) {
					$tag['attributes'] = $thisvals['attributes'];
				}
				if(isset($thisvals['value'])) {
					$tag['value'] = $thisvals['value'];
				}
			}
			
			$matches = 0;
			$matchPath = $this->curPath;
			$path = $matchPath . $thisvals['tag'];
		}
		else {
			// open tag, recurse
			$myChildren = $this->get_children($vals, $i);
			if(isset($thisvals['attributes'])) {
				$tag['attributes'] = $thisvals['attributes'];
			}
			$tag = array_merge($tag, $myChildren);
			
			//build it as simple as possible.
			if($this->makeSimpleTree) {
				unset($tag['attributes'], $tag['type']);
			}
		}
		

		return($tag);
	}//end build_tag()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * Internal function: build an nested array representing children
	 */
	private function get_children($vals, &$i) {
		$children = array();     // Contains node data
		
		if($i == -1) {
			$this->pathIndex=0;
			$this->pathList=array();
		}
		
		$this->curPath = null;
		if(isset($this->pathList[$this->pathIndex])) {
			$this->curPath = $this->pathList[$this->pathIndex];
		}
			
		// Loop through children, until hit close tag or run out of tags
		while (++$i < count($vals)) {
			$type = $vals[$i]['type'];
			
			if($type === 'complete' || $type === 'open') {
				// 'complete':	At end of current branch
				// 'open':	Node has children, recurse
				
				if($type == 'complete') {
					
					//TODO: If a new path is along an existing path, expand it out to include all sub-paths...
					/*
					 * EXAMPLE (from unit testing, see tests/files/test1.xml):
					 * 	/MAIN/MULTIPLE/0/ITEM/0
					 * 	/MAIN/MULTIPLE/0/ITEM/1
					 * 	/MAIN/MULTIPLE/0/ITEM/2
					 * 	/MAIN/MULTIPLE/0/ITEM/3/AGAIN/0/TEST/0
					 * 	/MAIN/MULTIPLE/0/ITEM/3/AGAIN/0
					 * 	/MAIN/MULTIPLE/0/ITEM/4
					 * 	/MAIN/MULTIPLE/1/ONE/0
					 * 	/MAIN/MULTIPLE/1/TWO/0
					 * 	/MAIN/MULTIPLE/1/THREE/0
					 */
					$this->update_pathlist($this->pathList[$this->pathIndex], null, false);
					
					$this->update_pathlist($this->pathList[$this->pathIndex] .'/'. $vals[$i]['tag'], $this->pathIndex, false);
					
					$this->pathIndex++;
				}
				else {
					if(isset($this->pathList[$this->pathIndex])) {
						$this->update_pathlist($this->pathList[$this->pathIndex] .'/'. $vals[$i]['tag'], $this->pathIndex);
					}
					else {
						$this->update_pathlist('/'. $vals[$i]['tag'], $this->pathIndex);
					}
				}
				
				$tag = $this->build_tag($vals[$i], $vals, $i, $type);
				
				$children[$vals[$i]['tag']][] = $tag;
			}
			elseif ($type === 'close') {
				
				// 'close:	End of node, return collected data
				//		Do not increment $i or nodes disappear!
				$bits = $this->explode_path($this->pathList[$this->pathIndex]);
				array_pop($bits);
				$this->update_pathlist($this->path_from_array($bits), $this->pathIndex, true);
				break;
			}
			else {
				throw new exception(__METHOD__ .": found invalid type (". $type .")");
			}
		} 
		
		
		foreach($children as $key => $value) {
			if (is_array($value) && (count($value) == 1)) {
				$children[$key] = $value[0];
			}
		}
		return $children;
	}//end get_children()
	//=================================================================================
	
	
	
	//=================================================================================
	/**
	 * To get data in an XML document via a simple path, as though it were a filesystem...
	 * EXAMPLE PATH:
	 * 	"NEW-ORDER-NOTIFICATION/BUYER-SHIPPING-ADDRESS/EMAIL"
	 * 
	 * @param $path			(string) path in XML document to traverse...
	 */
	public function get_path($path=NULL) {
		$this->a2p->reload_data($this->get_tree());
		return($this->a2p->get_data($path));
	}//end get_path()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_root_element() {
		//get EVERYTHING.
		$myData = $this->get_path();
		$keys = array_keys($myData);
		return($keys[0]);
	}//end get_root_element()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_value($path) {
		$retval = NULL;
		if(!is_null($path)) {
			$path = preg_replace('/\/$/', '', $path);
			$path = strtoupper($path);
			$path = $path . '/value';
			
			$retval = $this->get_path($path);
		}
		
		return ($retval);
	}//end get_value()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_attribute($path, $attributeName=NULL) {
		$retval = NULL;
		if(!is_null($path)) {
			$path = preg_replace('/\/$/', '', $path);
			$path = strtoupper($path);
			$path = $path . '/attributes/'. strtoupper($attributeName);
			
			$retval = $this->get_path($path);
		}
		
		return($retval);
		
	}//end get_attribute()
	//=================================================================================
	
	
	
	//=================================================================================
	public function update_a2p(cs_arrayToPath &$a2p) {
		$a2p->reload_data($this->a2p->get_data());
		return($a2p);
	}//end update_a2p()	
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_pathlist() {
		if(is_array($this->pathList)) {
			$myPathList = array();
			
			//loop through the path list and create a full VALID path list for something 
			//	like cs_phpxmlCreator{} to use (denotes path multiples).
			
			$this->gf->debug_print($this->pathList);
			foreach($this->pathList as $i=>$p) {
				$this->gf->debug_print($this->create_tag2index($p,true));
//				$this->build_parent_path_multiples($p);
//				$this->update_path_multiple($p);
//				$myPathList[] = $this->fix_path($p,true);
			}
			$this->gf->debug_print(__METHOD__ .": paths:::: ". $this->gf->debug_print($this->pathList,0));
		}
		else {
			throw new exception(__METHOD__ .": no paths for creating list");
		}
		return($myPathList);
	}//end get_pathlist()
	//=================================================================================
	
	
	
	//=================================================================================
	public function get_pathindex() {
		return($this->pathIndex);
	}//end get_pathindex()
	//=================================================================================
	
	
	
	//=================================================================================
	private function update_pathlist($path, $pathIndex=null) {
		if(is_numeric($pathIndex)) {
			$this->pathList[$pathIndex] = $path;
		}
		else {
			$this->pathList[] = $path;
		}
	}//end update_pathlist()
	//=================================================================================
}

?>