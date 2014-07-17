<?php

if(class_exists('acf')):

class deploy_acf extends deployWP_module {

	function setup(){
		$this->deploy_on_front = true;

		$plugins 	= get_plugins();
		$data 		= false;
		foreach ($plugins as $plugin => $d) {
			if($plugin == 'advanced-custom-fields-pro/acf.php' or $plugin == 'advanced-custom-fields/acf.php'){
				$data = $d;
				break;
			}
		}

		if(!$data){
			error_log('DWP module for ACF could not locate a valid version of ACF - only 4+ is supported');
			return false;
		}

		$this->ver = floor((float)$data['Version']);

		if($this->ver < 4){
			error_log('DWP module for ACF only supports versions 4+');
			return false;
		}

	}

	function collect(){
		global $deployWP, $pagenow;

		$method = 'collect_acf_'.$this->ver;
		$this->$method();

	}

	function collect_acf_5(){

		add_action('acf/update_field_group',		array($this, 'set_json_save_dir'), 9, 1);
		add_action('acf/update_field_group',		array($this, 'unset_json_save_dir'), 11, 1);
		add_action('acf/duplicate_field_group',		array($this, 'set_json_save_dir'), 9, 1);
		add_action('acf/duplicate_field_group',		array($this, 'unset_json_save_dir'), 11, 1);
		add_action('acf/untrash_field_group',		array($this, 'set_json_save_dir'), 9, 1);
		add_action('acf/untrash_field_group',		array($this, 'unset_json_save_dir'), 11, 1);
		add_action('acf/trash_field_group',			array($this, 'set_json_save_dir'), 9, 1);
		add_action('acf/trash_field_group',			array($this, 'unset_json_save_dir'), 11, 1);
		add_action('acf/delete_field_group',		array($this, 'set_json_save_dir'), 9, 1);
		add_action('acf/delete_field_group',		array($this, 'unset_json_save_dir'), 11, 1);
		add_action('acf/include_fields', 			array($this, 'set_json_save_dir'), 9, 1);
		add_action('acf/include_fields', 			array($this, 'unset_json_save_dir'), 11, 1);

	}

    function acf_save_json($r){
    	$dir = $this->env_dir.'/acf-json';
    	if(!file_exists($dir))
    		mkdir($dir, 0777, true);

 		$r = trim($dir);
    	return $r;
    }

    function set_json_save_dir($field_group){

    	if(!is_array($field_group) or !isset($field_group['key']))
    		return null;

    	if(in_array($field_group['title'], apply_filters('deployWP/acf/collect_fields', array())))
	    	add_filter('acf/settings/save_json', array($this, 'acf_save_json'));
    }

    function unset_json_save_dir(){
    	remove_filter('acf/settings/save_json', array($this, 'acf_save_json'));
    }

	function collect_acf_4(){
		if($pagenow !== 'index.php')
			return false;

		/* The path to the file that registers the fields */
		$file 	  = $this->env_dir.'/register-acf-fields.php';
		
		/* The arguments to get all ACF fields */
		$get_acfs 	= apply_filters('deployWP/acf/collect_fields', array());
		$acfs 		= array();
		foreach($get_acfs as $get){
			if($p = get_page_by_title($get, OBJECT, 'acf')){
				$acfs[] = $p;
			}
		}

		/* Get fields */
		if($acfs){

			/* 
			Fields where found.
			Now we need to get an array of their IDs
			*/
			foreach($acfs as &$acf){
				$acf = $acf->ID;
			}

			/* Require the export class of the ACF plugin */
			require_once(WP_PLUGIN_DIR.'/advanced-custom-fields/core/controllers/export.php');

			/*
			This will fool the ACF exporter into believing that
			a POST request with the fields to export has been made.
			*/
			$_POST['acf_posts'] = $acfs;
			
			/* New export object */
			$export = new acf_export();

			/*
			The html_php method outputs the needed html for the wp-admin
			area. We capture that with ob_start and split it by html tags
			in order to find the value of the textarea that holds the PHP
			code we need. Dirty dirty dirty.
			*/
			ini_set('display_errors', 'Off');
			$buffer = ob_start();
			$export->html_php();
			$contents = ob_get_contents();
			ob_end_clean();

			/* Unset the POST variable again to prevent errors with rest of page-load */
			unset($_POST['acf_posts']);

			/*
			Now we split the string to find the contents of the
			textarea with code to use when registering the fields.
			*/
			$contents = preg_split('~readonly="true">~', $contents);
			$contents = preg_split('~</textarea>~', $contents[1]);
			$contents = '<?php '.$contents[0].' ?>';

			/* Write the contents to the file */
			$file = fopen($file, 'w+');
			fwrite($file, $contents);
			fclose($file);

		}
	}

	function deploy(){
		global $deployWP, $pagenow;
		$method = 'deploy_acf_'.$this->ver;
		$this->$method();
	}

	function deploy_acf_4(){
		global $deployWP;

		/* The path to the file that registers the fields */
		$file 	  = $this->deploy_from_dir.'/register-acf-fields.php';

		/* We are live - or dev or staging - fetch the file that registers the fields. */
		if(file_exists($file)){
			require_once($file);
		}
	}

	function deploy_acf_5(){
		add_filter('acf/settings/load_json', array($this, 'acf_load_json'));
	}

	function acf_load_json($r){
    	$r[] = trim($this->deploy_from_dir.'/acf-json');
    	return $r;
    }
}

endif;

?>