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


require_once(dirname(__FILE__) .'/testOfA2P.php');
require_once(dirname(__FILE__) .'testOfCSPHPXML');

$test = &new TestSuite('CS-PHPXML Tests');
$test->addTestCase(new testOfA2p());
$test->addTestCase(new testOfCSPHPXML());
$test->run(new HtmlReporter())
?>
