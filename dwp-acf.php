<?php
/*
Plugin Name: DeployWP | ACF
Description: ACF integration with deployWP
*/

function dwp_acf_setup(){
    register_deploy_module('acf', dirname(__FILE__).'/deploy_acf.class.php');
}
add_action('deployWP', 'dwp_acf_setup');

?>