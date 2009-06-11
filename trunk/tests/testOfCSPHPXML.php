<?php
/*
 * Created on Jan 25, 2009
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

require_once(dirname(__FILE__) .'/../cs_phpxmlBuilder.class.php');
require_once(dirname(__FILE__) .'/../cs_phpxmlCreator.class.php');
require_once(dirname(__FILE__) .'/../cs_phpxmlParser.class.php');

class testOfCSPHPXML extends UnitTestCase {
	
	//-------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions();
		$GLOBALS['DEBUGPRINTOPT']=1;
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_pass_data_through_all_classes() {
		
		$testFiles = array(
			dirname(__FILE__) .'/files/test1.xml',
			#dirname(__FILE__) .'/files/test2.xml',
			#dirname(__FILE__) .'/files/test3.xml'
		);
		
		foreach($testFiles as $testFile) {
			$this->gfObj->debug_print(htmlentities(file_get_contents($testFile)));
			$parser->gf->debugPrintOpt = 1;
			//first, put it into cs-phpxmlParser.
			$parser = new cs_phpxmlParser(file_get_contents($testFile));
			$parser->get_tree();
			$this->gfObj->debug_print($parser->get_pathlist());
			exit(__METHOD__ ." -- ". __LINE__);
			#$this->gfObj->debug_print($parser->get_pathindex());
			#$this->gfObj->debug_print($parser->get_path_multiples());
			
			/*
			//now move it into the creator.
			$creator = new cs_phpxmlCreator($parser->get_root_element());
			$creator->load_xmlparser_data($parser);
			
			$this->gfObj->debug_print(__METHOD__ .": XML STRING FROM CREATOR:::". htmlentities($creator->create_xml_string()));
			
			//now move the data into the xmlBuilder (would be used to make the content of the XML file)
			$builder = new cs_phpxmlBuilder($creator->get_data());
			
			$this->gfObj->debug_print(__METHOD__ .": BUILDER DATA::: ". htmlentities($this->gfObj->debug_print($builder,0,1)));
			
			//okay, now let's compare it to the original contents.
			$origMd5 = md5(file_get_contents($testFile));
			$newMd5  = md5($builder->get_xml_string());
			if(!$this->assertEqual($origMd5, $newMd5)) {
				$this->gfObj->debug_print("FAILED TO MATCH DATA, testFile::: ". htmlentities($this->gfObj->debug_print(file_get_contents($testFile),0))
					."<BR><h2>DERIVED:::</h2> ". htmlentities($this->gfObj->debug_print($builder->get_xml_string(),0,1)));
				$this->assertEqual($builder->get_xml_string(), file_get_contents($testFile));
			}
			#*/
		}
		
	}//end test_pass_data_through_all_classes
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_creator() {
		
		$creator = new cs_phpxmlCreator('main');
		
		//run some tests to make sure that paths are fixed properly.
		$test2real = array(
			'test/3/x/y/z/0'									=> '/MAIN/0/TEST/3/X/0/Y/0/Z/0',
			'main/0/MyTest/x/Y/z'								=> '/MAIN/0/MYTEST/0/X/0/Y/0/Z/0',
			'test/3/x/y/Z/0'									=> '/MAIN/0/TEST/3/X/0/Y/0/Z/0',
			'this/is/a/very /long/paTH'							=> '/MAIN/0/THIS/0/IS/0/A/0/VERY/0/LONG/0/PATH/0',
			'path/with/3/alpha-num3r1c/chars:in/it-good-test'	=> '/MAIN/0/PATH/0/WITH/3/ALPHA-NUM3R1C/0/CHARS:IN/0/IT-GOOD-TEST/0',
			'path/with/same/word/in/it/twice:path/path'			=> '/MAIN/0/PATH/0/WITH/0/SAME/0/WORD/0/IN/0/IT/0/TWICE:PATH/0/PATH/0',
			'tagwithnum0'										=> '/MAIN/0/TAGWITHNUM0/0'
		);
		foreach($test2real as $test=>$real) {
			$this->assertEqual($creator->fix_path($test), $real);
		}
		
		$creator->add_tag_multiple('config/settings', array('first'=>1,'second'=>2));
		$creator->add_attribute('config/settings', array('note'=>'first settings block'));
		$creator->add_tag_multiple('config/settings', array('another'=>1,'again'=>2));
		$creator->add_attribute('config/settings/1', array('note'=>'second settings block'));
		$creator->add_tag('config', 'will this override all that other stuff I did...');
		$creator->add_tag('/main/config/0/settings/again', "There should now be TWO again tags at this level");
		$creator->add_tag('/main/config/0/settings/1', "this should appear BELOW the zeroth index");
		$creator->add_tag('/main/config/0/settings/blanktag', null);
		$creator->add_tag('config/test', 'my test of cs_phpxmlBuilder');
		
		
		$this->assertTrue(strlen($creator->create_xml_string()), "Zero-length XML string returned from creator");
		if(!$this->assertEqual($creator->create_xml_string(), file_get_contents(dirname(__FILE__) .'/files/testCreator1.xml'))) {
			$this->gfObj->debug_print(__METHOD__ .": CREATED XML STRING::: ". htmlentities($creator->create_xml_string()) ."<hr>"
				. $this->gfObj->debug_print(htmlentities(file_get_contents(dirname(__FILE__) .'/files/testCreator1.xml'))));
		}
		
		
	}//end test_creator()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**URL: http://project.crazedsanity.com/extern/helpdesk/view?ID=267
	 */
	function test_issue_267() {
		$creator = new cs_phpxmlCreator('main');
		
		$creator->add_tag('/main/data/value/data/value', 'data');
		$creator->add_tag('/main/data/value/1/extra');
		if(!$this->assertEqual($creator->create_xml_string(), file_get_contents(dirname(__FILE__) .'/files/issue267.xml'))) {
			$this->gfObj->debug_print(htmlentities($creator->create_xml_string()));
		}
	}//end test_issue_267()
	//-------------------------------------------------------------------------
}

?>
