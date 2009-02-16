#!/usr/bin/php
<?php

require "PHPSTLTemplate.php";
require "Compiler.php";
require "Tag.php";
require "CoreTag.php";
require "HTMLTag.php";

$t = new PHPSTLTemplate(dirname(__FILE__).'/test.xml');

print $t->render();

?>
