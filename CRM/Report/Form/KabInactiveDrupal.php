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
 | Kaibissa Report Inactive Drupal users                              |
 | Erik Hommel, 12 December 2012                                      |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */
require_once 'CRM/Report/Form.php';

class CRM_Report_Form_KabInactiveDrupal extends CRM_Report_Form {

    function __construct( ) {
        $this->_columns = array( );
        parent::__construct( );
    }

    function postProcess( ) {
        $this->beginPostProcess( );
        

        $this->_columnHeaders = array(
            'uid'     => array( 'title' => 'Drupal UserID' ),
            'name'    => array( 'title' => 'Name' ),
            'login'   => array( 'title' => 'Last logged in date' ),
            'created' => array( 'title' => 'Date user created in Drupal' ),
            'access'  => array( 'title' => 'Last date accessed site'),
        );

        $this->buildRows ( $rows );
	$this->alterDisplay( $rows);
        $this->doTemplateAssignment( $rows );
        $this->endPostProcess( $rows );    
	}
    function buildRows( &$rows ) {
        // use this method to modify $this->_columnHeaders
        $this->modifyColumnHeaders( );
        /*
         * calculate date 1 year ago
         */
        $lastYear = date("Ymd", strtotime( "now - 1 year") );
        /*
         * retrieve all drupal users 
         */
        $drupalUsers = db_query(
'SELECT uid, name, created, access, login FROM {users} WHERE uid <> 0 AND status <> 0 LIMIT 250' );
        while ( $drupalUser = db_fetch_object( $drupalUsers ) ) {
            /*
             * if user has not logged in and accessed in the last year,
             * user is considered inactive
             */
            $inactiveContact = array();
            $count++;
            $login = format_date( $drupalUser->login, 'custom', 'Ymd');
            $access = format_date( $drupalUser->access, 'custom', 'Ymd' );
            if ( $login <= $lastYear && $access <= $lastYear ) {
                $inactiveContact['uid'] = $drupalUser->uid;
                $inactiveContact['name'] = $drupalUser->name;
                $inactiveContact['created'] = format_date( $drupalUser->created, 'custom', 'd-M-Y' );
                $inactiveContact['access'] = date( 'd-M-Y', strtotime( $access ) );
                $inactiveContact['login'] = date( 'd-M-Y', strtotime( $login ) );
                $rows[] = $inactiveContact;
            }
        }
    }
    function alterDisplay( &$rows ) {

        $entryFound = false;
        foreach ( $rows as $rowNum => $row ) {
            /*
             * make name function as link to view
             */
            if ( array_key_exists('name', $row ) ) {
                $rows[$rowNum]['name_link' ] = "http://www.kabissa.org/user/".$row['uid'];
                $rows[$rowNum]['name_hover'] = ts("Kabissa Profile");
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
    
}
