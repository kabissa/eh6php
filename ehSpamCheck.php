<?php
/*
 * Erik Hommel <hommel@ee-atwork.nl> 30 Oct 2014
 */

ini_set( 'display_errors', '1' );
set_time_limit(0);

/*
 * Define constants to be used in the rest of the script:
 * EH_CONTACTLIMIT - the limit of the number of contacts to process in one run
 * EH_SPECIALGROUPS - the groups that if the contact belongs to one of those
 *                    the contact will not be deleted but flagged with a
 *                    special tag (see next one)
 * EH_TAG_ID - the special tag that members of the groups above will be flagged
 *             with if they are suspect
 */
define('EH_CONTACTLIMIT', 50);
define('EH_SPECIALGROUPS', '9:3:102');
define('EH_TAG_ID', 35);

// to be changed for local/server runs
require_once($_SERVER['DOCUMENT_ROOT'].
	'/speel6/sites/default/civicrm.settings.php');

require_once 'CRM/Core/Config.php';
$config = CRM_Core_Config::singleton( );

$suspects = array();
$groupMembers = array();
$countFlagged = 0;
$countTrashed = 0;

$contacts = getContactsToBeChecked();
foreach ($contacts as $contact) {
  if (isParticipant($contact['contact_id']) == FALSE) {
    if (isInSpecialGroups($contact['contact_id']) == TRUE) {
      $groupmembers[] = $contact['contact_id'];
    } 
    if (isSuspectName($contact['display_name']) == TRUE || 
      isSuspectEmail($contact['email']) == TRUE) {
      $suspects[] = $contact['contact_id'];
    }
  }
}
unset($contacts);
foreach ($suspects as $suspectContactId) {
  if (in_array($groupMembers)) {
    processFlag($suspectContactId);
    $countFlagged++;
  } else {
    processTrash($suspectContactId);
    $countTrashed++;
  }
}
echo "<p>".$countTrashed." contacts trashed, ".$countFlagged.
  " flagged as ToBeSpamChecked</p>";
/**
 * Function to get contacts to be checked
 */
function getContactsToBeChecked() {
  $params = array(
    'version' => 3,
    'is_deleted' => 0,
    'contact_type' => 'Individual',
    'options' => array('limit' => EH_CONTACTLIMIT));
  $contacts = civicrm_api('Contact', 'Get', $params);
  if (!civicrm_error($contacts)) {
    return $contacts['values'];
  } else {
    return array();
  }
}
/**
 * Function to check if the contact has been participant at any event
 */
function isParticipant($contactId) {
  return FALSE;
}
/**
 * Function to check if the contact is member of one of the special groups
 */
function isInSpecialGroups($contactId) {
  return FALSE;
}
/**
 * Function to check if the name has a digit in it and is therefore suspect
 */
function isSuspectName($name) {
  return FALSE;
}
/**
 * Function to check if the email address is suspect
 */
function isSuspectEmail($email) {
  return FALSE;
}
/**
 * Function to flag contacts as ToBeSpamChecked
 */
function processFlag($contactId) {
  
}
/**
 * Function to trash contacts
 */
function processTrash($contactId) {
  
}