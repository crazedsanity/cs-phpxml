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
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_pass_data_through_all_classes() {
		
		$testFiles = array(
			dirname(__FILE__) .'/files/test1.xml',
			dirname(__FILE__) .'/files/test2.xml',
			dirname(__FILE__) .'/files/test3.xml'
		);
		
		foreach($testFiles as $testFile) {
			//first, put it into cs-phpxmlParser.
			$parser = new cs_phpxmlParser(file_get_contents($testFile));
			
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
			
		}
		
	}//end test_pass_data_through_all_classes
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_creator() {
		$creator = new cs_phpxmlCreator('main');
		$creator->create_path('config/settings');
		$creator->set_tag_as_multiple('config/settings');
		$creator->add_tag_multiple('config/settings', array('first'=>1,'second'=>2), array('note'=>'first settings block'));
		$creator->add_tag_multiple('config/settings', array('another'=>1,'again'=>2));
		
		$this->gfObj->debug_print(__METHOD__ .": CREATED XML STRING::: ". htmlentities($creator->create_xml_string()) . $this->gfObj->debug_print($creator,0));
		
		$this->assertTrue(strlen($creator->create_xml_string()), "Zero-length XML string returned from creator");
		$this->assertEqual($creator->create_xml_string(), file_get_contents(dirname(__FILE__) .'/files/testCreator1.xml'));
		
		
		
		
	}//end test_creator()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**URL: http://project.crazedsanity.com/extern/helpdesk/view?ID=267
	 */
	function test_issue_267() {
		$creator = new cs_phpxmlCreator('main');
		
		$creator->create_path('/main/data/value');
		$creator->set_tag_as_multiple('/main/data/value');
		$creator->add_tag('/main/data/value/0/data', 'data');
		$creator->add_tag('/main/data/value/1/extra');
		$this->assertEqual($creator->create_xml_string(), file_get_contents(dirname(__FILE__) .'/files/issue267.xml'));
		
		$this->gfObj->debug_print(htmlentities($creator->create_xml_string()));
	}//end test_issue_267()
	//-------------------------------------------------------------------------
}

?>
