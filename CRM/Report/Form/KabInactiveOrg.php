<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 | Kaibissa Report Inactive Users                                     |
 | Erik Hommel, 15 November 2012                                      |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */
ini_set( 'display_errors', '1' );

require_once 'CRM/Report/Form.php';

class CRM_Report_Form_KabInactiveOrg extends CRM_Report_Form {

    function __construct( ) {
        $this->_columns = array( );
        parent::__construct( );
    }

    function postProcess( ) {
        $this->beginPostProcess( );
        

        $this->_columnHeaders = array(
            'nameCivi'          => array( 'title' => 'Organization' ),
            'nameDrupal'        => array( 'title' => 'User name in Drupal'),
            'login'             => array( 'title' => 'Last logged in date' ),
            'created'           => array( 'title' => 'Date user created in Drupal' ),
            'accessed'          => array( 'title' => 'Last date accessed site'),
            'contact_changed'   => array( 'title' => 'Date details last changed'),
            'changed_by'        => array( 'title' => 'Details last changed by'),
            'on_hold'           => array( 'title' => 'Email delivery disabled'),
            'address'           => array( 'title' => 'Organization address'),
            'city'              => array( 'title' => 'City'),
            'country'           => array( 'title' => 'Country')
        );

        $this->buildRows ( $rows );
	$this->alterDisplay( $rows);
        $this->doTemplateAssignment( $rows );
        $this->endPostProcess( $rows );    
	}
    function alterDisplay( &$rows ) {

        $entryFound = false;
        foreach ( $rows as $rowNum => $row ) {
            /*
             * make display_name function as link to contact view
             */
            if ( array_key_exists('nameCivi', $row ) ) {
                $url = CRM_Utils_System::url( "civicrm/contact/view",
                        'reset=1&cid=' . $row['contact_id'], $this->_absoluteUrl );
                $rows[$rowNum]['nameCivi_link' ] = $url;
                $rows[$rowNum]['nameCivi_hover'] = ts("Kabissa Profile");
                $entryFound = true;
            }
            // skip looking further in rows, if first row itself doesn't 
            // have the column we need
            if ( !$entryFound ) {
                break;
            }
            
            if ( !$entryFound ) {
                break;
            }
        }
    }
    function buildRows( &$rows ) {
        // use this method to modify $this->_columnHeaders
        $this->modifyColumnHeaders( );
        /*
         * calculate date 1 year ago
         */
        $lastYear = date("Y-m-d", strtotime( "now - 1 year") );
        /*
         * retrieve all active organizations in CiviCRM with API
         */
        $apiParams = array(
            'version'                   =>  3,
            'contact_type'              =>  'Organization',
            'contact_is_deleted'        =>  0,
            'return.id'                 =>  1,
            'return.organization_name'  =>  1,
            'return.street_address'     =>  1,
            'return.city'               =>  1,
            'return.country'            =>  1,
            'rowCount'                  =>  500
        );
        $allOrgs = civicrm_api( "Contact", "Get", $apiParams );
        /*
         * if error in retrieving orgs, show
         */
        if ( $allOrgs['is_error'] == 1 ) {
            CRM_Core_Session::setStatus( "Could not find organizations with CiviCRM API Contact Get, error : {$allOrgs['error_message']}. Contact the system administrator" );
        }
        /*
         * for every organization, check if there are
         * - any posts in Drupal in the last year
         * - any contact details changed in the last year
         * - who changed the details
         */
        foreach ( $allOrgs['values'] as $allOrg ) {
            $orgInactive = true;
            $inactiveOrg = array( );
            if ( isset( $allOrg['contact_id'] ) ) {
                $inactiveOrg['contact_id'] = $allOrg['contact_id'];
            } else {
                $inactiveOrg['contact_id'] = 0;
            }
            if ( isset( $allOrg['organization_name'] ) ) {
                $inactiveOrg['nameCivi'] = $allOrg['organization_name'];
            } else {
                $inactiveOrig['nameCivi'] = "";
            }
            if ( isset( $allOrg['street_address'] ) ) {
                $inactiveOrg['address'] = $allOrg['street_address'];
            } else {
                $inactiveOrg['address'] = "";
            }
            if ( isset( $allOrg['city'] ) ) {
                $inactiveOrg['city'] = $allOrg['city'];
            } else {
                $inactiveOrg['city'] = "";
            }
            if ( isset( $allOrg['country'] ) ) {
                $inactiveOrg['country'] = $allOrg['country'];
            } else {
                $inactiveOrg['country'] = "";
            }
            /*
             * check if contact details have been changed in
             * the last year. If so, no further checking required
             */
            $qryCiviLog = 
"SELECT COUNT(*) AS aantal FROM civicrm_log WHERE entity_id = {$allOrg['contact_id']} AND modified_date > '$lastYear'";
            $daoCiviLog = CRM_Core_DAO::executeQuery( $qryCiviLog );
            if ( $daoCiviLog->fetch() ) {
                /*
                 * if aantal > 0, $orgInactive = false and remove array element. 
                 * If not, retrieve last updated and last updated by
                 */               
                if ( $daoCiviLog->aantal > 0 ) {
                    $orgInactive = false;
                    $inactiveOrg = array( );
                } else {
                    $qryCiviLog = 
"SELECT modified_id, modified_date FROM civicrm_log WHERE entity_id = {$allOrg['contact_id']} ORDER BY modified_date DESC";
                    $daoCiviLog = CRM_Core_DAO::executeQuery( $qryCiviLog );
                    if ( $daoCiviLog->fetch() ) {
                        $inactiveOrg['contact_changed'] = date("d-M-Y", strtotime( $daoCiviLog->modified_date ) );
                        $apiParams = array(
                            'version'       =>  3,
                            'contact_id'    =>  $daoCiviLog->modified_id,
                            'return'        =>  'display_name'
                        );
                        $apiModName = civicrm_api( "Contact", "getvalue", $apiParams );
                        if ( $apiModName['is_error'] == 0 ) {
                            $inactiveOrg['changed_by'] = $apiModName;
                        } else {
                            $inactiveOrg['changed_by'] = "";
                        }
                    }
                }
            }
            /*
             * only further processing if still inactive
             */
            if ( $orgInactive ) {
                /*
                 * retrieve Drupal user for Organization
                 */
                $apiParams = array(
                    'version'       => 3,
                    'contact_id'    => $allOrg['contact_id']
                );
                $apiUF = civicrm_api( "UFMatch", "Getsingle", $apiParams );
                if ( $apiUF['is_error'] == 1 ) {
                    $inactiveOrg['nameDrupal'] = "not in Drupal";
                    $inactiveOrg['login'] = "";
                    $inactiveOrg['created'] = "";
                    $inactiveOrg['accessed'] = "";
                } else {
                    $drupalID = $apiUF['uf_id'];
                    $drupalUsers = db_query('SELECT name, created, access, login FROM {users} WHERE uid = $drupalID' );
                    if ( $drupalUser = db_fetch_object( $drupalUsers ) ) {
                        /*
                         * if user has either logged in or accessed in the last year,
                         * user is considered active
                         */
                        $login = format_date( $drupalUser->login, 'custom', 'Ymd');
                        $access = format_date( $drupalUser->access, 'custom', 'Ymd' );
                        if ( $login > $lastYear || $access > $lastYear ) {
                            $orgInactive = false;
                            $inactiveOrg = array( );
                        } else {
                            $inactiveOrg['nameDrupal'] = $drupalUser->name;
                            $inactiveOrg['created'] = format_date( $drupalUser->created, 'custom', 'd-M-Y' );
                            $inactiveOrg['accessed'] = date( 'd-M-Y', strtotime( $access ) );
                            $inactiveOrg['login'] = date( 'd-M-Y', strtotime( $login ) );
                        }
                    }
                }
            }
            if ( !empty( $inactiveOrg ) ) {
                $rows[] = $inactiveOrg;
            }
        }
    }
}
