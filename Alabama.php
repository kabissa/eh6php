<?php
ini_set( 'display_startup_errors', '0' );
ini_set( 'display_errors', '1' );
require_once($_SERVER['DOCUMENT_ROOT'].
	'/speel6/sites/default/civicrm.settings.php');
require_once 'CRM/Core/Config.php';
require_once('api/v2/GroupContact.php');
$config =& CRM_Core_Config::singleton( );
    
$group_query = "SELECT id FROM civicrm_group WHERE title = 'EH Group Alabama'";
$dao_group = CRM_Core_DAO::executeQuery($group_query);
if ($dao_group->fetch()) {
    $group_id = $dao_group->id;
}
/*
 * select all addresses where city = Al;abama with limit 25000
 */
$query_address = "SELECT contact_id FROM civicrm_address WHERE city = 'Alabama' OR city = 'ALABAMA' LIMIT 25000";
$dao_address = CRM_Core_DAO::executeQuery($query_address);
while ($dao_address->fetch()) {
    /*
     * put member in group
     */
    $params = array(
        'version'       =>  3,
        'contact_id'    =>  $dao_address->contact_id,
        'group_id'      =>  $group_id
    );
    $result = civicrm_group_contact_add($params);
    
    /*
     * set contact to is_deleted
     */
    //$upd_contact = "UPDATE civicrm_contact SET is_deleted = 1 WHERE id = {$dao_address->contact_id}";
    //CRM_Core_DAO::executeQuery($upd_contact);
    echo "<p>25000 contacts processed";
}
