<?php

namespace Silverorange\Autoloader;

$package = new Package('silverorange/blorg');

$package->addRule(new Rule('pages', 'Blorg', array('Page', 'Server')));
$package->addRule(new Rule('gadgets', 'Blorg', 'Gadget'));
$package->addRule(new Rule('views', 'Blorg', 'View'));

$package->addRule(
	new Rule(
		'dataobjects',
		'Blorg',
		array(
			'Binding',
			'Wrapper',
			'Author',
			'Comment',
			'FileImage',
			'File',
			'Post',
			'Tag',
		)
	)
);

$package->addRule(new Rule('', 'Blorg'));

Autoloader::addPackage($package);

?>
