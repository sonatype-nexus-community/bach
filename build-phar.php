<?php
$srcRoot = __DIR__;
$buildRoot = "./build";
 
$phar = new Phar("bach.phar", 
	FilesystemIterator::CURRENT_AS_FILEINFO |     	FilesystemIterator::KEY_AS_FILENAME, "myapp.phar");
	$phar->buildFromDirectory(dirname(__FILE__));
$phar->setStub($phar->createDefaultStub("bach"));