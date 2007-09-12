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

require_once(dirname(__FILE__) .'/../cs-arrayToPath/arrayToPathClass.php');
require_once(dirname(__FILE__) ."/xmlAbstract.class.php");


class XMLParser extends cs_xmlAbstract {

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

	var $data;			// Input XML data buffer
	var $vals;			// Struct created by xml_parse_into_struct
	var $collapse_dups;	// If there is only one tag of a given name,
						//   shall we store as scalar or array?
	var $index_numeric;	// Index tags by numeric position, not name.
						//   useful for ordered XML like CallXML.
	private $a2p;
	private $xmlTags;
	private $xmlIndex;
	private $levelArr;
	private $childTagDepth = 0;
	private $makeSimpleTree = FALSE;
	
	//=================================================================================
	/**
	 * CONSTRUCTOR: Read in XML on object creation, via raw data (string), stream, filename, or URL.
	 */
	function __construct($data_source, $data_source_type='raw', $collapse_dups=1, $index_numeric=0) {
		$this->get_version();
		$this->collapse_dups = $collapse_dups;
		$this->index_numeric = $index_numeric;
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
		return($this->get_children($vals, $i));
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
			if(isset($thisvals['attributes'])) {
				$tag['attributes'] = $thisvals['attributes'];
			}
			if(isset($thisvals['value'])) {
				if($this->makeSimpleTree) {
					$tag = $thisvals['value'];
				} else {
					$tag['value'] = $thisvals['value'];
				}
			}
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
		
		if ($i > -1 && isset($vals[$i]['value'])) {
			//Node has CDATA before it's children.
			$children['VALUE'] = $vals[$i]['value'];
		}

		// Loop through children, until hit close tag or run out of tags
		while (++$i < count($vals)) {
			$type = $vals[$i]['type'];
			
			/* TODO: find something that causes this instance to fire-off, so I can tell WTF it should do.
			if ($type === 'cdata')
			{
				//TODO: find somewhere that causes this instance to fire off, so we can 
				// 'cdata':	Node has CDATA after one of it's children
				// 		(Add to cdata found before in this case)
				$children['VALUE'] .= $vals[$i]['value'];
			}
			else#*/
			if($type === 'complete' || $type === 'open') {
				// 'complete':	At end of current branch
				// 'open':	Node has children, recurse
				$tag = $this->build_tag($vals[$i], $vals, $i, $type);
				if ($this->index_numeric) {
					$tag['TAG'] = $vals[$i]['tag'];
					$children[] = $tag;
				}
				else {
					$children[$vals[$i]['tag']][] = $tag;
				}
			}
			elseif ($type === 'close') {
				// 'close:	End of node, return collected data
				//		Do not increment $i or nodes disappear!
				break;
			}
		} 
		
		if ($this->collapse_dups) {
			foreach($children as $key => $value) {
				if (is_array($value) && (count($value) == 1)) {
					$children[$key] = $value[0];
				}
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
		$a2p = new arrayToPath($this->get_tree());
		return($a2p->get_data($path));
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
}

?>