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


class testOfA2P extends UnitTestCase {
	
	//-------------------------------------------------------------------------
	function setUp() {
		$this->a2p = new cs_arrayToPath(array());
		$this->gfObj = new cs_globalFunctions;
	}//end setUp()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function tearDown() {
	}//end tearDown()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_basics() {
		//make sure nothing is in the object initialially.
		$this->assertEqual(array(), $this->a2p->get_data());
		
		$newData = array(
			'look at me'	=> '23dasdvcv3q3qeedasd'
		);
		$this->a2p->reload_data($newData);
		$this->assertNotEqual(array(), $this->a2p->get_data());
		
		
		//load a complex array & test to ensure the returned value is the same.
		$newData = array(
			'x'		=> array(
				'y'		=> array(
					'z'		=> array(
						'fiNal'		=> 'asdfadsfadfadsfasdf'
					)
				),
				'_y_'	=> null,
				'-'		=> null
			),
			'a nother path2 Stuff -+=~!@#$' => '-x-'
		);
		$this->a2p->reload_data($newData);
		$this->assertEqual($newData, $this->a2p->get_data());
		$this->assertEqual($newData['x']['y']['z']['fiNal'], $this->a2p->get_data('/x/y/z/fiNal'));
		
		//before going on, test that the list of valid paths makes sense.
		$expectedValidPaths = array(
			'/x/y/z/fiNal',
			'/a nother path2 Stuff -+=~!@#$',
			'/x/_y_',
			'/x/-',
		);
		$actualValidPaths = $this->a2p->get_valid_paths();
		$this->assertEqual(count($expectedValidPaths), count($actualValidPaths));
		
		//NOTE: since cs_arrayToPath::get_valid_paths() doesn't return paths in their found order, can't directly compare the arrays.
		$this->assertTrue(count($expectedValidPaths), count($actualValidPaths)); 
		foreach($expectedValidPaths as $i=>$path) {
			$findIndex = array_search($path, $actualValidPaths);
			$this->assertTrue(is_numeric($findIndex));
			$this->assertTrue(strlen($expectedValidPaths[$findIndex]));
			$this->assertTrue(strlen($actualValidPaths[$findIndex]));
		}
		
		
		$this->a2p->set_data('/x/y/z/fiNal', null);
		$this->assertNotEqual($this->a2p->get_data('/x/y/z/fiNal'), $newData['x']['y']['z']['fiNal']);
		
		//ensure paths with dots are ok.
		$this->assertEqual($this->a2p->get_data('/x/y/z/fiNal'), $this->a2p->get_data('/x/y/z/g/q/x/../../../fiNal'));
		
		//make sure extra slashes are okay.
		$this->assertEqual($this->a2p->get_data('/x/y/z/fiNal'), $this->a2p->get_data('/x/y//z///fiNal//'));
	}//end test_basics()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_path_tracer() {
		
		$myTests = array(
			'simple' => array(
					'data' => array(
						'x'=>null,
						'y'=>null
					),
					'paths' => array(
						'/x',
						'/y'
					)
			),
			'moreComplex' => array(
					'data' => array(
						'x' => array(
							'y' => array(
							)
						),
						'x2' => array(
							'y' => array(
							)
						)
					),
					'paths' => array(
						'/x/y',
						'/x2/y'
					)
			),
			'numericData' => array(
					'data' => array(
						0	=> array(
							1 => array()
						),
						'1'	=> array(
							'1' => array()
						),
						2	=> array(
							'0'
						)
					),
					'paths' => array(
						'/0/1',
						'/1/1',
						'/2/0'
					)
			),
			'dataWithDepth' => array(
					'data' => array(
						'1' => array(
							'2' => array(
								'3' => array(
									'4' => ""
								)
							)
						),
						'one' => array(
							'two' => array(
								'three' => array(
									'checkme' => array(),
									'four' => array()
								)
							)
						),
						'first' => array(
							'second' => array(
								'third' => array(
									'fourth' => array(
										'fifth' => array(
											'sixth' => array(
												'seventh' => array()
											)
										)
									)
								)
							)
						)
					),
					'paths' => array(
						'/1/2/3/4',
						'/one/two/three/checkme',
						'/one/two/three/four',
						'/first/second/third/fourth/fifth/sixth/seventh'
					)
			),
			'likeXML' => array(
					'data'	=> array(
						'methodResponse' => array(
							'methodName'	=> 'blogger.getUsersBlogs',
							'info' => array(
								'deeper'	=> array(
									'test' => array(
										'of' => array(
											'path' => array(
												'tracer' => 'YEAH!'
											)
										)
									)
								)
							),
							'params' => array(
								'param' => array(
									array(
										'value'	=> array(
											'string'	=> null
										)
									),
									array(
										'value' => array(
											'string'	=> 'usernameHere'
										)
									),
									array(
										'value' => array(
											'string'	=> 'passw0rd'
										)
									) 
								)
							)
						),
					
					),
					'paths' => array(
						'/methodResponse/methodName',
						'/methodResponse/info/deeper/test/of/path/tracer',
						'/methodResponse/params/param/0/value/string',
						'/methodResponse/params/param/1/value/string',
						'/methodResponse/params/param/2/value/string'
					)
			),
		);
		
		
		foreach($myTests as $testName=>$testData) {
			$this->a2p->reload_data($testData['data']);
			
			$validPaths = $this->a2p->get_valid_paths();
			if(!$this->assertEqual(count($testData['paths']), count($validPaths))) {
				$this->gfObj->debug_print(__METHOD__ .": failed test (". $testName .")... VALID PATHS::: ". $this->gfObj->debug_print($validPaths,0,1) .
						", EXPECTED PATHS::: ". $this->gfObj->debug_print($testData['paths'],0,1));
				exit;
			}
			
			foreach($testData['paths'] as $path) {
				$index = array_search($path, $validPaths);
				$this->assertTrue(strlen($testData['paths'][$index]));
			}
		}
		
	}//end test_path_tracer()
	//-------------------------------------------------------------------------
	
}//end testOfA2P{}
?>
