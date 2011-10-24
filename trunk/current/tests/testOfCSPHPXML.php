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



	function test_basics() { 
		$testXml = new _testXml;
		$testXml->rootElement = 'mAiN';
		$testXml->preserveCase = true;
		$fixPathTests = array(
			'/mAiN/TEST/1/STUFF'	=> '/mAiN/0/TEST/1/STUFF/0',
			'/'						=> '',
			'/mAiN/0/TEST'			=> '/mAiN/0/TEST/0',
			'/mAiN/0/TEST/0/ONE/1'	=> '/mAiN/0/TEST/0/ONE/1'
		);
		try {
			foreach($fixPathTests as $fixThis => $matchesThis) {
				$testXml->preserveCase = true;
				$this->assertEqual($testXml->fix_path($fixThis), $matchesThis);

				$testXml->preserveCase = false;
				$this->assertEqual($testXml->fix_path($fixThis), strtoupper($matchesThis));
			}
		}
		catch(Exception $e) {
			throw new exception("Failed basic fix_path() test... ". $e->getMessage());
		}

		$xml = new cs_phpxmlCreator('cart', null, true);
		$xml->add_attribute("/cart", array("foo"=>"bar"));
		$xml->add_tag("/cart/item/name", "foo");
		$xml->add_attribute("/cart/item", array('comment'=>"1"));  //this REPLACES all attributes with the given array.
		$xml->add_tag("/cart/item/value", "lots");
		$xml->add_tag("/cart/item/extra", null);
		$xml->add_tag("/cart/item/extra", null, array('location'=>"the internet"));
		$xml->add_tag("/cart/item/1/name", "bar");
		$xml->add_tag("/cart/item/1/value", "even more");
		$xml->add_attribute("/cart/item/1", array('comment'=>"2"));
		$xml->add_attribute("/cart/item/1/value", array('currency'=>"USD"));
		$xml->add_tag("/cart/item/1/extra/0", null, array('location'=>"unknown"));   //faster than adding attribs later.
		$xml->add_tag("/cart/item/1/extra/1/magic", "STUFFING!", array("first"=>"the first tag", "second"=>"second tag"));
		$xml->add_tag("/cart/item/1/extra/1/extra", null);
		$xml->add_tag("/cart/extra/magic", "STUFFING!", array("first"=>"the first tag", "second"=>"second tag"));
		$xml->add_tag("/cart/extra/extra", null);
		$xml->add_tag("/cart/tag.with.dots.in.it/item", "value of tag with dots");
		
		$testFileContents = file_get_contents(dirname(__FILE__) .'/files/basic.xml');
		$testFileContents = preg_replace("/\n\$/", '', $testFileContents);
		$generatedXML = $xml->create_xml_string();
		
		if(!$this->assertEqual(serialize($testFileContents), serialize($generatedXML))) {
			$this->gfObj->debug_print(htmlentities(serialize($testFileContents)));
			$this->gfObj->debug_print(htmlentities(serialize($generatedXML)));
		}

		$parser = new cs_phpxmlParser($generatedXML);
		#$this->gfObj->debug_print($xml->load_xmlparser_data());
		$parser->get_tree();
	}//end test_basics();
	
	
	
	//-------------------------------------------------------------------------
	function texst_pass_data_through_all_classes() {
		
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
		$testFileContents = preg_replace("/\n\$/", '', file_get_contents($testFile));
		$parser = new cs_phpxmlParser($testFileContents);
		
		//first, make sure we can load the file & get the VALUE/value value... 
		{
			if(!$this->assertEqual('location of this TAG is /MAIN/TAGONE/VALUE', $parser->get_tag_value('/MAIN/TAGONE/VALUE'))) {
				$this->gfObj->debug_print($parser->get_path('/MAIN/TAGONE/VALUE/value'));
			}
			
			$expectedArray = array(
				'MAIN' => array(
					0 => array(
						'TAGONE' => array(
							0	=> array(
								'VALUE' => array(
									0 => array(
										'__data__'	=> "location of this TAG is /MAIN/TAGONE/VALUE"
									)
								)
							)
						),
						'TAGTWO' => array(
							0 => array(
								'__attribs__'	=> array(
									'VALUE'	=> "this is the attribute of /MAIN/TAGTWO/attributes/VALUE"
								)
							)
						),
						'DATA' => array(
							0 => array(
								'VALUE'	=> array(
									0 => array(
										'DATA'	=> array(
											0 => array(
												'VALUE'	=> array(
													0 => array(
														'__data__'	=> "data"
													)
												)
											)
										)
									)
								)
							)
						)
					)
				)
			);
			
			if(!$this->assertEqual($expectedArray, $parser->get_path('/'))) {
				$this->gfObj->debug_print(serialize($parser->get_path('/')));
				$this->gfObj->debug_print(serialize($expectedArray));
			}
		}
		
		//now drop it into creator, and see if we can modify it.
		{
			$creator = new cs_phpxmlCreator($parser->get_root_element());
			$creator->load_xmlparser_data($parser);
			if(!$this->assertEqual($expectedArray, $creator->get_data('/'))) {
				$this->gfObj->debug_print($expectedArray);
				$this->gfObj->debug_print($creator->get_data('/'));
				$this->gfObj->debug_print($creator);
			}
			$creator->add_tag('/MAIN/TAGTHREE', "Test tag 3 creation", array('VALUE'=>"tag3 value"));
			$expectedArray['MAIN'][0]['TAGTHREE'][0] = array(
				cs_phpxmlCreator::attributeIndex	=> array(
					'VALUE'		=> "tag3 value"
				),
				cs_phpxmlCreator::dataIndex			=> "Test tag 3 creation"
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
							"	<tagtwo value=\"this is the attribute of /MAIN/TAGTWO/attributes/VALUE\" />\n" .
							"	<data>\n" .
							"		<value>\n" .
							"			<data>\n" .
							"				<value>data</value>\n" .
							"			</data>\n" .
							"		</value>\n" .
							"	</data>\n" .
							"	<tagthree value=\"tag3 value\">Test tag 3 creation</tagthree>\n" .
							"</main>";
			if(!$this->assertEqual($expectedXml, $creator->create_xml_string())) {
				$this->gfObj->debug_print(serialize(htmlentities($expectedXml)));
				$this->gfObj->debug_print(serialize(htmlentities($creator->create_xml_string())));
			}
			
			//get data on the long path...
			$this->assertEqual('data', $creator->get_tag_value('/MAIN/DATA/VALUE/DATA/VALUE'));
		}
		
		//test that we can pass the test XML file through all the classes...
		{
			$parser = new cs_phpxmlParser($testFileContents);
			$creator = new cs_phpxmlCreator($parser->get_root_element());
			$creator->load_xmlparser_data($parser);
			$builder = new cs_phpxmlBuilder($creator->get_data());
			$expectedXml = $testFileContents;
			$actualXml = $builder->get_xml_string();
			if(!$this->assertEqual($expectedXml, $actualXml)) {
				$this->gfObj->debug_print(serialize(htmlentities($expectedXml)));
				$this->gfObj->debug_print(serialize(htmlentities($actualXml)));
			}

			//sub-test: make SURE that calling $builder->get_xml_string() returns the same thing on all subsequent calls.
			$nextCall = $builder->get_xml_string();
			$this->assertEqual($actualXml, $nextCall, "Builder is not resetting internal XML string");
		}
		
		//test that we can CREATE xml (from scratch) that has tags named "value".
		{
			///METHODRESPONSE/PARAMS/PARAM/value/STRUCT/MEMBER
			$creator = new cs_phpxmlCreator('methodresponse', null, false);
			$creator->add_tag('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER', 'stuff', array('teSt'=>"1234"));
			
			$this->assertEqual('stuff', $creator->get_tag_value('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER'));
			//this will be equal because the path gets upper-cased.
			$this->assertEqual('stuff', $creator->get_tag_value('/methodResponse/params/param/value/struct/member'));
			
			//These have different cases, but should be the same because it is NOT preserving case.
			$this->assertEqual('1234', $creator->get_attribute('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER', 'teSt'));
			$this->assertEqual('1234', $creator->get_attribute('/METHODRESPONSE/PARAMS/PARAM/VALUE/STRUCT/MEMBER', 'TEST'));
		}
		
	}//end test_issue267
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_preserveCase() {
		//This file was retrieved from http://www.rssboard.org/files/sample-rss-2.xml 
		//	-- Linked from page: http://www.rssboard.org/rss-specification
		$testFile = dirname(__FILE__) .'/files/testPreserveCase.xml';
		
		//Test that parsing it preserves case.
		{
			$parser = new cs_phpxmlParser(file_get_contents($testFile), true);
			
			$this->assertEqual('Tue, 10 Jun 2003 04:00:00 GMT', $parser->get_path('/rss/channel/pubDate/'. cs_phpxmlCreator::dataIndex));
			$this->assertEqual($parser->get_path('/rss/channel/pubDate/'. cs_phpxmlCreator::dataIndex), $parser->get_tag_value('/rss/channel/pubDate'));
			
			$this->assertNotEqual($parser->get_path('/rss/channel/item/0/value/'. cs_phpxmlCreator::dataIndex), $parser->get_path('/rss/channel/item/0/Value/'. cs_phpxmlCreator::dataIndex));
			$this->assertEqual('Testing cs_phpxml1', $parser->get_path('/rss/channel/item/0/value/'. cs_phpxmlCreator::dataIndex));
			$this->assertEqual('Testing cs_phpxml2', $parser->get_path('/rss/channel/item/0/Value/'. cs_phpxmlCreator::dataIndex));
			
			$this->assertEqual('test 2', $parser->get_attribute('/rss/channel/item/0/Value', 'note'));
			$this->assertEqual('test 1', $parser->get_attribute('/rss/channel/item/0/value', 'note'));
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
			
			foreach($itemsData as $i=>$myTagData) {
				$myPath = $pathBase .'/item/'. $i;
				$xml->add_tag($myPath);
				foreach($myTagData as $n=>$v) {
					$xml->add_tag($myPath .'/'. $n, $v);
				}
			}
			
			$xml->add_attribute($pathBase .'/item/0/value', array('note'=>"test 1"));
			$xml->add_attribute($pathBase .'/item/0/Value', array('note'=>"test 2"));
		}
		
	}//end test_preserveCase()
	//-------------------------------------------------------------------------
}

		class _testXML extends cs_phpxmlAbstract {
			public $rootElement;
			public $preserveCase;
			public function fix_path($path) {
				return(parent::fix_path($path));
			}
		}
?>
