#!/usr/bin/php
<?php

require "PHPSTLTemplate.php";

PHPSTLCompiler::$CacheDirectory = dirname(__FILE__).'/template-test-cache';
$t = new PHPSTLTemplate(dirname(__FILE__).'/test.xml');
$t->getPHPSTLCompiler()->setCaching(false);

print $t->render();

?>
