<?
	require_once(dirname(__FILE__) . '/gangplank.php');
	
	// 
	// The SimpleListPlank
	//
	// A simplified derivative of our world-famous ListPlank
	//
	class SimpleListPlank extends ListPlank {
		function SimpleListPlank($singular, $plural = '') {
			$plural = empty($plural) ? $singular . 's' : $plural;
			$this->ListPlank($singular, $plural);
			$this->show_group_controls = false;
			$this->show_edit_link = false;
			$this->show_move_link = false;
			$this->show_add_link = false;
		}
	}
?>