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
	
	
	
	//-------------------------------------------------------------------------
	function test_preserveCase() {
		//This file was retrieved from http://www.rssboard.org/files/sample-rss-2.xml 
		//	-- Linked from page: http://www.rssboard.org/rss-specification
		$testFile = dirname(__FILE__) .'/files/testPreserveCase.xml';
		
		//Test that parsing it preserves case.
		{
			$parser = new cs_phpxmlParser(file_get_contents($testFile), true);
			
			$this->assertEqual('Tue, 10 Jun 2003 04:00:00 GMT', $parser->get_path('/rss/channel/pubDate/value'));
			
			$this->assertNotEqual($parser->get_path('/rss/channel/item/0/value/value'), $parser->get_path('/rss/channel/item/0/Value/value'));
			$this->assertEqual('Testing cs_phpxml1', $parser->get_path('/rss/channel/item/0/value/value'));
			$this->assertEqual('Testing cs_phpxml2', $parser->get_path('/rss/channel/item/0/Value/value'));
			
			$this->assertEqual('test 2', $parser->get_path('/rss/channel/item/0/Value/attributes/note'));
			$this->assertEqual('test 1', $parser->get_path('/rss/channel/item/0/value/attributes/note'));
		}
		
		// Recreate the entire test XML file and make sure it matches.
		{
			$xml = new cs_phpxmlCreator('rss', null, true);
			
			$pathBase = '/rss/channel';
			$xml->add_tag($pathBase);
			
			$createData = array(
				'title'				=> "Liftoff News",
				'link'				=> "http://liftoff.msfc.nasa.gov/",
				'description'		=> "Liftoff to Space Exploration.",
				'language'			=> "en-us",
				'pubDate'			=> "Tue, 10 Jun 2003 04:00:00 GMT",
				'lastBuildDate'		=> "Tue, 10 Jun 2003 09:41:01 GMT",
				'docs'				=> "http://blogs.law.harvard.edu/tech/rss",
				'generator'			=> "Weblog Editor 2.0",
				'managingEditor'	=> "editor@example.com",
				'webMaster'			=> "webmaster@example.com"
			);
			
			foreach($createData as $tagPart=>$tagData) {
				$xml->add_tag($pathBase .'/'. $tagPart, $tagData);
			}
			
			//build items.
			$itemsData = array(
				0	=> array(
					'title'			=> "Star City",
					'link'			=> "http://liftoff.msfc.nasa.gov/news/2003/news-starcity.asp",
					'description'	=> "How do Americans get ready to work with Russians aboard the International Space Station? They take a crash course in culture, language and protocol at Russia's &lt;a href=\"http://howe.iki.rssi.ru/GCTC/gctc_e.htm\"&gt;Star City&lt;/a&gt;.",
					'pubDate'		=> "Tue, 03 Jun 2003 09:39:21 GMT",
					'guid'			=> "http://liftoff.msfc.nasa.gov/2003/06/03.html#item573",
					'value'			=> "Testing cs_phpxml1",
					'Value'			=> "Testing cs_phpxml2"
				),
				1	=> array(
					'description'	=> "Sky watchers in Europe, Asia, and parts of Alaska and Canada will experience a &lt;a href=\"http://science.nasa.gov/headlines/y2003/30may_solareclipse.htm\"&gt;partial eclipse of the Sun&lt;/a&gt; on Saturday, May 31st.",
					'pubDate'		=> "Fri, 30 May 2003 11:06:42 GMT",
					'guid'			=> "http://liftoff.msfc.nasa.gov/2003/05/30.html#item572"
				),
				2	=> array(
					'title'			=> "The Engine That Does More",
					'link'			=> "http://liftoff.msfc.nasa.gov/news/2003/news-VASIMR.asp",
					'description'	=> "Before man travels to Mars, NASA hopes to design new engines that will let us fly through the Solar System more quickly.  The proposed VASIMR engine would do that.",
					'pubDate'		=> "Tue, 27 May 2003 08:37:32 GMT",
					'guid'			=> "http://liftoff.msfc.nasa.gov/2003/05/27.html#item571"
				),
				3	=> array(
					'title'			=> "Astronaut's Dirty Laundry",
					'link'			=> "http://liftoff.msfc.nasa.gov/news/2003/news-laundry.asp",
					'description'	=> "Compared to earlier spacecraft, the International Space Station has many luxuries, but laundry facilities are not one of them.  Instead, astronauts have other options.",
					'pubDate'		=> "Tue, 20 May 2003 08:56:02 GMT",
					'guid'			=> "http://liftoff.msfc.nasa.gov/2003/05/20.html#item570"
				)
			);
			
			$xml->create_path($pathBase .'/item');
			$xml->set_tag_as_multiple($pathBase .'/item');
			
			foreach($itemsData as $i=>$myTagData) {
				$myPath = $pathBase .'/item/'. $i;
				$xml->add_tag($myPath);
				foreach($myTagData as $n=>$v) {
					$xml->add_tag($myPath .'/'. $n, $v);
				}
			}
			
			$xml->add_attribute($pathBase .'/item/0/value', array('note'=>"test 1"));
			$xml->add_attribute($pathBase .'/item/0/Value', array('note'=>"test 2"));
			
			
			$this->gfObj->debug_print(htmlentities($xml->create_xml_string()));
		}
		
	}//end test_preserveCase()
	//-------------------------------------------------------------------------
}

?>
