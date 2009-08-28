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
		$this->gfObj->debugPrintOpt=1;
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_pass_data_through_all_classes() {
		
		//first, put it into cs-phpxmlParser.
		$testFile = dirname(__FILE__) .'/files/test1.xml';
		$parser = new cs_phpxmlParser(file_get_contents($testFile));
		
		//now move it into the creator.
		$creator = new cs_phpxmlCreator($parser->get_root_element());
		$creator->load_xmlparser_data($parser);
		
		//now move the data into the xmlBuilder (would be used to make the content of the XML file)
		$builder = new cs_phpxmlBuilder($creator->get_data());
		
		//okay, now let's compare it to the original contents.
		$origMd5 = md5(file_get_contents($testFile));
		$newMd5  = md5($builder->get_xml_string());
		$this->assertEqual($origMd5, $newMd5);
		
	}//end test_pass_data_through_all_classes
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_issue267 () {
		
		$testFile = dirname(__FILE__) .'/files/test2-issue267.xml';
		$parser = new cs_phpxmlParser(file_get_contents($testFile));
		
		//first, make sure we can load the file & get the VALUE/value value... 
		{
			if(!$this->assertEqual('location of this TAG is /MAIN/TAGONE/VALUE', $parser->get_path('/MAIN/TAGONE/VALUE/value'))) {
				$this->gfObj->debug_print($parser->get_path('/MAIN/TAGONE/VALUE/value'));
			}
			
			$expectedArray = array(
				'MAIN'	=> array(
					'type'		=> "open",
					'TAGONE'	=> array(
						'type'			=> "open",
						'VALUE'			=> array(
										'type'	=> "complete",
										'value'	=> "location of this TAG is /MAIN/TAGONE/VALUE"
						)
					),
					'TAGTWO'	=> array(
						'type'			=> "complete",
						'attributes'	=> array(
										'VALUE'	=> "this is the attribute of /MAIN/TAGTWO/attributes/VALUE"
						)
					),
					'DATA'		=> array(
							'type'	=> "open",
							'VALUE'	=> array(
								'type'	=> "open",
								'DATA'	=> array(
									'type'	=> "open",
									'VALUE'	=> array(
										'type'	=> "complete",
										'value'	=> "data"
									)
								)
							)
						)
				)
			);
			
			if(!$this->assertEqual($expectedArray, $parser->get_path('/'))) {
				$this->gfObj->debug_print($parser->get_path('/'));
			}
		}
		
		//now drop it into creator, and see if we can modify it.
		{
			$creator = new cs_phpxmlCreator($parser->get_root_element());
			$creator->load_xmlparser_data($parser);
			if(!$this->assertEqual($expectedArray, $creator->get_data('/'))) {
				$this->gfObj->debug_print($expectedArray);
				$this->gfObj->debug_print($creator->get_data('/'));
				
			}
			$creator->add_tag('TAGTHREE', "Test tag 3 creation", array('VALUE'=>"tag3 value"));
			$expectedArray['MAIN']['TAGTHREE'] = array(
				'type'			=> "complete",
				'attributes'	=> array(
					'VALUE'		=> "tag3 value"
				),
				'value'			=> "Test tag 3 creation"
			);
			
			if(!$this->assertEqual($expectedArray, $creator->get_data('/'))) {
				$this->gfObj->debug_print($expectedArray);
				$this->gfObj->debug_print($creator->get_data('/'));
			}
			
			//now see if the XML created appears identical.
			$expectedXml =	"<main>\n" .
							"	<tagone>\n" .
							"		<value>location of this TAG is /MAIN/TAGONE/VALUE</value>\n" .
							"	</tagone>\n" .
							"	<tagtwo value=\"this is the attribute of /MAIN/TAGTWO/attributes/VALUE\"/>\n" .
							"	<data>\n" .
							"		<value>\n" .
							"			<data>\n" .
							"				<value>data</value>\n" .
							"			</data>\n" .
							"		</value>\n" .
							"	</data>\n" .
							"	<tagthree value=\"tag3 value\">Test tag 3 creation</tagthree>\n" .
							"</main>";
			$this->assertEqual($expectedXml, $creator->create_xml_string());
			
			//get data on the long path...
			$this->assertEqual('data', $creator->get_data('/MAIN/DATA/VALUE/DATA/VALUE/value'));
		}
		
		//test that we can pass the test XML file through all the classes...
		{
			$parser = new cs_phpxmlParser(file_get_contents($testFile));
			$creator = new cs_phpxmlCreator($parser->get_root_element());
			$creator->load_xmlparser_data($parser);
			$builder = new cs_phpxmlBuilder($creator->get_data());
			$this->assertEqual(file_get_contents($testFile), $builder->get_xml_string());
		}
		
		//test that we can CREATE xml (from scratch) that has tags named "value".
		{
			///METHODRESPONSE/PARAMS/PARAM/value/STRUCT/MEMBER
			$creator = new cs_phpxmlCreator('methodresponse');
			$creator->create_path('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT');
			$creator->add_tag('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER', 'stuff', array('teSt'=>"1234"));
			$this->assertTrue($creator->verify_path('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER'));
			$this->assertTrue($creator->verify_path('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER'));
			$this->assertTrue($creator->verify_path('/METHODRESPONSE/PARAMS/PARAM/VALUE/struct/MEMBER'));
			$this->assertTrue($creator->verify_path('/methodResponse/params/param/value/struct/member'));
			$this->assertTrue($creator->verify_path('/methodResponse/params/param/value/struct'));
			$this->assertTrue($creator->verify_path('/methodResponse/params/param/value'));
			$this->assertTrue($creator->verify_path('/methodResponse/params/param/value/struct/member'));
			$this->assertTrue($creator->verify_path('/methodResponse/params/param/Value/struct/member'));
			$this->assertTrue($creator->verify_path('/methodResponse/params/param/vALUE/struct/member'));
			
			$this->assertEqual('stuff', $creator->get_data('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER/value'));
			$this->assertNotEqual('stuff', $creator->get_data('/methodResponse/params/param/value/struct/member/value'));
			
			$this->assertEqual('1234', $creator->get_data('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER/attributes/teSt'));
			$this->assertEqual('', $creator->get_data('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER/attributes/TEST'));
		}
		
	}//end test_issue2
	//-------------------------------------------------------------------------
}

?>
