<?
	/*
	EXAMPLE OF USING 'FOREIGNKEY' VIRTAL COLUMNS

	Table layout is:

	mysql> describe featured_sites;
	+-------------------+--------------+------+-----+---------------------+----------------+
	| Field             | Type         | Null | Key | Default             | Extra          |
	+-------------------+--------------+------+-----+---------------------+----------------+
	| featured_site_key | int(11)      | NO   | PRI | NULL                | auto_increment |
	| domain            | varchar(255) | NO   |     |                     |                |
	| name              | varchar(255) | NO   |     |                     |                |
	| case_study_text   | text         | NO   |     |                     |                |
	| screenshot        | varchar(255) | NO   |     |                     |                |
	| add_dt            | datetime     | NO   |     | 0000-00-00 00:00:00 |                |
	| is_visible        | tinyint(4)   | NO   |     | 1                   |                |
	| category_key      | tinyint(4)   | NO   |     | 0                   |                |
	+-------------------+--------------+------+-----+---------------------+----------------+
	8 rows in set (0.00 sec)

	mysql> describe featured_site_categories;
	+---------------+--------------+------+-----+---------+----------------+
	| Field         | Type         | Null | Key | Default | Extra          |
	+---------------+--------------+------+-----+---------+----------------+
	| category_key  | int(11)      | NO   | PRI | NULL    | auto_increment |
	| category_name | varchar(255) | NO   |     |         |                |
	+---------------+--------------+------+-----+---------+----------------+
	2 rows in set (0.00 sec)
	*/
	ini_set('display_errors', 'true');
	require(dirname(__FILE__).'/../gangplank/gangplank.php');
	$g = new AutoPlank('featured site', 'featured sites');
	$g->edit->addVirtualColumn(
		'screenshot',
		'ScaledPhoto',
		array('screenshot' => '410x')
	);
	$g->list->addVirtualColumn(
		'category_key',
		'ForeignKey',
		array('foreign_table' => 'featured_site_categories',
		      'foreign_key' => 'category_key',
					'foreign_desc' => 'category_name')
	);
	$g->edit->addVirtualColumn(
		'category_key',
		'ForeignKey',
		array('foreign_table' => 'featured_site_categories',
		      'foreign_key' => 'category_key',
					'foreign_desc' => 'category_name')
	);
	$g->handleRequest();
	require(dirname(__FILE__).'/../_head.php');
	?>
	<link rel="stylesheet" type="text/css" href="gangplank/gangplank.css"/>
	<?
	echo $g->render();
