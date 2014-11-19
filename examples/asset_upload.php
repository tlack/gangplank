<?
	ini_set('display_errors', 'true');
	require(dirname(__FILE__).'/../lib/gangplank/gangplank.php');
	$g = new AutoPlank('channel partner', 'channel partners');

	$g->edit->setHelpText('name', 'Friendly partner name');
	$g->edit->setHelpText('iana_id', 'IANA ID (for accredited registrars only)');

	$g->edit->setHelpText('country_name_en', 'English-language name of the partner\'s country');
	$g->edit->setHelpText('country_name_es', 'Spanish-language name of the partner\'s country');

	/*
	$g->edit->addVirtualColumn(
		'logo_url',
		'ScaledPhoto',
		array(
			'logo_url' => '190x'
		)
	);
	*/
	$g->edit->hideColumn('logo_url');
	$g->edit->addVirtualColumn('logo_url', 'Asset', array(
		'upload_url' => 'http://expedrion.go.co/uploads/',
		'upload_path' => '/home/expedrion/html/uploads/'
	));

	$g->handleRequest();
	require(dirname(__FILE__).'/../_head.php');
	?>
	<link rel="stylesheet" type="text/css" href="lib/gangplank/gangplank.css"/>
	<?
	if ($g->getMode()=='edit') {
		?>
		<p>
			<a href="Xadmin/channel_partners.php">Never mind, take me back to the list of channel partners</a>
		</p>
		<?
	}

	echo $g->render();

