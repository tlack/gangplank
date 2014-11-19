<?
	require('site.php');
	require('gangplank/gangplank.php');
	check_auth();
	$g = new AutoPlank('hotel description', 'hotel descriptions');
	$g->edit->setHelpText('extra_photo_urls', 'Found other/better photos on the web? Paste the URLs <i>of the images themselves</i> here, one per line');
	$g->edit->setHelpText('video_urls', 'Found good videos on Youtube/Vimeo/etc? Paste URLs of the video pages here, one per line');
	$g->edit->addButton('view', true, 'Save &amp; View on MiamiHotels.com', 'http://miamihotels.com/hotel_details2.php?h={ean_hotel_id}');
	// $g->edit->addButton('photo_thief', true, 'Photo Thief (BETA)', 'hotel_photo_thief.php?hdk={hotel_description_key}');
	if (!empty($_GET['q'])) {
		$g->list->setWhere("hotel_name like '%$_GET[q]%' or ean_hotel_id like '%$_GET[q]%'");
	}
	$g->list->hideColumn('hotel_description_key');
	$g->list->addButton('from_ian', array('label' => 'Import a hotel from EAN', 'url' => 'hotel_description_import.php'));
	$g->list->addButton('photo_rascal', array('label' => 'Install Photo Rascal', 'url' => 'hotel_photo_rascal.php'));
	$g->handleRequest();
	admin_header('Hotel Descriptions');
	?>
	<link rel="stylesheet" type="text/css" href="gangplank/gangplank.css" />
	<?
	if ($g->getMode() == 'list') { ?>
		<form action="#">
			<input type="text" name="q" placeholder="(Search for name..)"/>
		</form>
		<?
	}
	if ($g->getMode() == 'edit') { ?>
		<!-- TinyMCE -->
		<script type="text/javascript" src="tinymce/jscripts/tiny_mce/tiny_mce.js"></script>
		<script>
		tinyMCE.init({
			// General options
			mode : "exact",
			elements: 'description',
			theme : "simple",

			height: 400
		});
		</script>
		<!-- /TinyMCE -->
	<? }

	echo $g->render();
	admin_footer();
