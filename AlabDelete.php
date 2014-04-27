<?php
ini_set( 'display_startup_errors', '0' );
ini_set( 'display_errors', '1' );
require_once($_SERVER['DOCUMENT_ROOT'].
	'/speel6/sites/default/civicrm.settings.php');
require_once 'CRM/Core/Config.php';
require_once('api/v2/GroupContact.php');
require_once('api/v2/Contact.php');
$config =& CRM_Core_Config::singleton( );
    
$groupQuery = "SELECT id FROM civicrm_group WHERE title = 'EH Group Alabama'";
$daoGroup = CRM_Core_DAO::executeQuery($groupQuery);
if ($daoGroup->fetch()) {
    $groupId = $daoGroup->id;
}
unset($daoGroup, $groupQuery);
/*
 * select all GroupContacts
 */
$deleteCount = 0;
$query = "SELECT contact_id FROM civicrm_group_contact WHERE group_id = $groupId 
    AND status = 'Added'";
$dao = CRM_Core_DAO::executeQuery($query);
while ($dao->fetch()) {
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_contact WHERE id = {$dao->contact_id}");    
    $deleteCount++;
}

echo "<p>$deleteCount contacts deleted";
