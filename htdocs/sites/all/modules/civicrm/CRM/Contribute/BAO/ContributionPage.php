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
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

require_once 'CRM/Contribute/DAO/ContributionPage.php';

/**
 * This class contains Contribution Page related functions.
 */
class CRM_Contribute_BAO_ContributionPage extends CRM_Contribute_DAO_ContributionPage 
{
    /**
     * takes an associative array and creates a contribution page object
     *
     * @param array $params (reference ) an assoc array of name/value pairs
     *
     * @return object CRM_Contribute_DAO_ContributionPage object 
     * @access public
     * @static
     */
    public static function &create(&$params) 
    {
        $dao = new CRM_Contribute_DAO_ContributionPage( );
        $dao->copyValues( $params );
        $dao->save( );
        return $dao;
    }

   /**
     * update the is_active flag in the db
     *
     * @param int      $id        id of the database record
     * @param boolean  $is_active value we want to set the is_active field
     *
     * @return Object             DAO object on sucess, null otherwise
     * @static
     */
   static function setIsActive( $id, $is_active ) 
   {
        return CRM_Core_DAO::setFieldValue( 'CRM_Contribute_DAO_ContributionPage', $id, 'is_active', $is_active );
   }
    
    static function setValues( $id, &$values ) 
    {
        $params = array('id' => $id);

        CRM_Core_DAO::commonRetrieve( 'CRM_Contribute_DAO_ContributionPage', $params, $values );

        // get the amounts and the label
        require_once 'CRM/Core/OptionGroup.php';  
        $values['amount'] = array( );
        CRM_Core_OptionGroup::getAssoc( "civicrm_contribution_page.amount.{$id}", $values['amount'], true );

        // get the profile ids
        require_once 'CRM/Core/BAO/UFJoin.php'; 
        $ufJoinParams = array( 'entity_table' => 'civicrm_contribution_page',   
                               'entity_id'    => $id );   
        list( $values['custom_pre_id'],
              $values['custom_post_id'] ) = CRM_Core_BAO_UFJoin::getUFGroupIds( $ufJoinParams ); 

        // add an accounting code also
        if ( $values['contribution_type_id'] ) {
            $values['accountingCode'] = CRM_Core_DAO::getFieldValue( 'CRM_Contribute_DAO_ContributionType',
                                                                     $values['contribution_type_id'],
                                                                     'accounting_code' );
        }
    }


    /**
     * Function to send the emails
     * 
     * @param int     $contactID         contact id 
     * @param array   $values            associated array of fields
     * @param boolean $isTest            if in test mode
     * @param boolean $returnMessageText return the message text instead of sending the mail
     *
     * @return void
     * @access public
     * @static
     */
    static function sendMail( $contactID, &$values, $isTest = false, $returnMessageText = false ) 
    { 
        require_once "CRM/Core/BAO/UFField.php";
        $gIds = array( );
        $params = array( );
        if ( isset( $values['custom_pre_id'] ) ) {
            $preProfileType = CRM_Core_BAO_UFField::getProfileType( $values['custom_pre_id'] );
            if ( $preProfileType == 'Membership' && CRM_Utils_Array::value( 'membership_id', $values )  ) {
                $params['custom_pre_id'] = array( array( 'membership_id', '=', $values['membership_id'], 0, 0 ) );
            } else if ( $preProfileType == 'Contribution' && CRM_Utils_Array::value( 'contribution_id', $values ) ) {
                $params['custom_pre_id'] = array( array( 'contribution_id', '=', $values['contribution_id'], 0, 0 ) );
            }
            
            $gIds['custom_pre_id'] = $values['custom_pre_id'];
        }

        if ( isset( $values['custom_post_id'] ) ) {
            $postProfileType = CRM_Core_BAO_UFField::getProfileType( $values['custom_post_id'] );
            if ( $postProfileType == 'Membership' && CRM_Utils_Array::value( 'membership_id', $values ) ) {
                $params['custom_post_id'] = array( array( 'membership_id', '=', $values['membership_id'], 0, 0 ) );
            } else if ( $postProfileType == 'Contribution' && CRM_Utils_Array::value( 'contribution_id', $values ) ) {
                $params['custom_post_id'] = array( array( 'contribution_id', '=', $values['contribution_id'], 0, 0 ) );
            }
            
            $gIds['custom_post_id'] = $values['custom_post_id'];
        }
        
        //check whether it is a test drive
        if ( $isTest && !empty( $params['custom_pre_id'] ) ) {
            $params['custom_pre_id'][] = array( 'contribution_test', '=', 1, 0, 0 );
        }
        
        if ( $isTest && !empty( $params['custom_post_id'] ) ) {
            $params['custom_post_id'][] = array( 'contribution_test', '=', 1, 0, 0 );
        }
        if ( ! $returnMessageText ) {
            //send notification email if field values are set (CRM-1941)
            require_once 'CRM/Core/BAO/UFGroup.php';
            foreach ( $gIds as $key => $gId ) {
                $email = CRM_Core_DAO::getFieldValue( 'CRM_Core_DAO_UFGroup', $gId, 'notify' );
                if ( $email ) {
                    $val = CRM_Core_BAO_UFGroup::checkFieldsEmptyValues( $gId, $contactID, $params[$key] );
                    CRM_Core_BAO_UFGroup::commonSendMail($contactID, $val); 
                }
            }
        }

        if ( CRM_Utils_Array::value( 'is_email_receipt', $values )  || 
             CRM_Utils_Array::value( 'onbehalf_dupe_alert', $values ) || 
             $returnMessageText ) {
            $template =& CRM_Core_Smarty::singleton( );

            // get the billing location type
            if ( !array_key_exists('related_contact', $values) ) {
                $locationTypes =& CRM_Core_PseudoConstant::locationType( );
                $billingLocationTypeId = array_search( 'Billing',  $locationTypes );
            } else {
                // presence of related contact implies onbehalf of org case, 
                // where location type is set to default. 
                require_once 'CRM/Core/BAO/LocationType.php';
                $locType = CRM_Core_BAO_LocationType::getDefault();
                $billingLocationTypeId = $locType->id;
            }

            require_once 'CRM/Contact/BAO/Contact/Location.php';
            if ( !array_key_exists('related_contact', $values) ) {
                list( $displayName, $email ) = CRM_Contact_BAO_Contact_Location::getEmailDetails( $contactID, false, $billingLocationTypeId );
            }
            // get primary location email if no email exist( for billing location).
            if ( !$email ) {
                list( $displayName, $email ) = CRM_Contact_BAO_Contact_Location::getEmailDetails( $contactID );
            }
            
            //for display profile need to get individual contact id,  
            //hence get it from related_contact if on behalf of org true CRM-3767.
                       
            //CRM-5001 Contribution/Membership:: On Behalf of Organization,
            //If profile GROUP contain the Individual type then consider the
            //profile is of Individual ( including the custom data of membership/contribution )
            //IF Individual type not present in profile then it is consider as Organization data.
            require_once 'CRM/Core/BAO/UFGroup.php';
            $userID = $contactID;
            if ( $preID = CRM_Utils_Array::value( 'custom_pre_id', $values ) ) {
                if ( CRM_Utils_Array::value( 'related_contact', $values ) ) {
                    $preProfileTypes = CRM_Core_BAO_UFGroup::profileGroups( $preID );
                    if ( in_array('Individual', $preProfileTypes ) ) {
                        //Take Individual contact ID
                        $userID = CRM_Utils_Array::value( 'related_contact', $values );
                    }
                }
                self::buildCustomDisplay( $preID, 'customPre', $userID, $template, $params['custom_pre_id'] );
            }
            $userID = $contactID;    
            if ( $postID = CRM_Utils_Array::value( 'custom_post_id', $values ) ) {
                if ( CRM_Utils_Array::value( 'related_contact', $values ) ) {
                    $postProfileTypes = CRM_Core_BAO_UFGroup::profileGroups( $postID );
                    if ( in_array('Individual', $postProfileTypes ) ) {
                        //Take Individual contact ID
                        $userID = CRM_Utils_Array::value( 'related_contact', $values );
                    }
                }
                self::buildCustomDisplay( $postID, 'customPost', $userID, $template, $params['custom_post_id'] );
            }
            
            // set email in the template here
            $tplParams = array(
                'email'            => $email,
                'receiptFromEmail' => $values['receipt_from_email'],
                'contactID'        => $contactID,
                'contributionID'   => $values['contribution_id'],
                'membershipID'     => CRM_Utils_Array::value('membership_id', $values),
                'lineItem'         => CRM_Utils_Array::value('lineItem',      $values), // CRM-5095
                'priceSetID'       => CRM_Utils_Array::value('priceSetID',    $values), // CRM-5095
            );

            if ( $contributionTypeId = CRM_Utils_Array::value('contribution_type_id', $values ) ) {
                $tplParams['contributionTypeId']   = $contributionTypeId;
                $tplParams['contributionTypeName'] = CRM_Core_DAO::getFieldValue( 'CRM_Contribute_DAO_ContributionType',
                                                                                  $contributionTypeId );
            }
                        
            // address required during receipt processing (pdf and email receipt)
            if ( $displayAddress = CRM_Utils_Array::value('address', $values) ) {
                $tplParams['address'] = $displayAddress;
                $tplParams['contributeMode']= null;
            }

            // CRM-6976
            $originalCCReceipt = CRM_Utils_Array::value( 'cc_receipt' , $values );

            // cc to related contacts of contributor OR the one who
            // signs up. Is used for cases like - on behalf of
            // contribution / signup ..etc  
            if ( array_key_exists('related_contact', $values) ) {
                list( $ccDisplayName, $ccEmail ) = 
                    CRM_Contact_BAO_Contact_Location::getEmailDetails( $values['related_contact'] );
                $ccMailId = "{$ccDisplayName} <{$ccEmail}>";
                
                $values['cc_receipt'] = CRM_Utils_Array::value( 'cc_receipt' , $values ) ? 
                    ($values['cc_receipt'] . ',' . $ccMailId) : $ccMailId;
                
                // reset primary-email in the template
                $tplParams['email'] = $ccEmail;

                $tplParams['onBehalfName']  = $displayName;
                $tplParams['onBehalfEmail'] = $email;
            }
            
            // use either the contribution or membership receipt, based on whether it’s a membership-related contrib or not
            $sendTemplateParams = array(
                'groupName' => $tplParams['membershipID'] ? 'msg_tpl_workflow_membership' : 'msg_tpl_workflow_contribution',
                'valueName' => $tplParams['membershipID'] ? 'membership_online_receipt'   : 'contribution_online_receipt',
                'contactId' => $contactID,
                'tplParams' => $tplParams,
                'isTest'    => $isTest,
            	'PDFFilename' => 'civicrm.pdf',
            );

            require_once 'CRM/Core/BAO/MessageTemplates.php';

            if ( $returnMessageText ) {
                list ($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplates::sendTemplate($sendTemplateParams);
                return array( 'subject' => $subject,
                              'body'    => $message,
                              'to'      => $displayName,
                              'html'    => $html );
            }
            
            if ( $values['is_email_receipt'] ) {
                $sendTemplateParams['from']    = CRM_Utils_Array::value('receipt_from_name',$values) .' <'. $values['receipt_from_email']. '>';
                $sendTemplateParams['toName']  = $displayName;
                $sendTemplateParams['toEmail'] = $email;
                $sendTemplateParams['cc']      = CRM_Utils_Array::value('cc_receipt' , $values);
                $sendTemplateParams['bcc']     = CRM_Utils_Array::value('bcc_receipt', $values);
                list ($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplates::sendTemplate($sendTemplateParams);
            }

            // send duplicate alert, if dupe match found during on-behalf-of processing.
            if ( CRM_Utils_Array::value( 'onbehalf_dupe_alert', $values ) ) {
                $sendTemplateParams['groupName']                   = 'msg_tpl_workflow_contribution';
                $sendTemplateParams['valueName']                   = 'contribution_dupalert';
                $sendTemplateParams['from']                        = ts('Automatically Generated') . " <{$values['receipt_from_email']}>";
                $sendTemplateParams['toName']                      = CRM_Utils_Array::value('receipt_from_name',$values);
                $sendTemplateParams['toEmail']                     = $values['receipt_from_email'];
                $sendTemplateParams['tplParams']['onBehalfID']     = $contactID;
                $sendTemplateParams['tplParams']['receiptMessage'] = $message;
                
                // fix cc and reset back to original, CRM-6976
                $sendTemplateParams['cc']      = $originalCCReceipt;

                CRM_Core_BAO_MessageTemplates::sendTemplate($sendTemplateParams);
            }
        }
    }
    
    /**
     * Function to send the emails for Recurring Contribution Notication
     * 
     * @param string  $type         txnType 
     * @param int     $contactID    contact id for contributor
     * @param int     $pageID       contribution page id
     * @param object  $recur        object of recurring contribution table
     *
     * @return void
     * @access public
     * @static
     */
    static function recurringNofify( $type, $contactID, $pageID , $recur ) 
    {
        $value = array( );
        if ( $pageID ) {
            CRM_Core_DAO::commonRetrieveAll( 'CRM_Contribute_DAO_ContributionPage', 'id', 
                                             $pageID, $value, 
                                             array( 'title', 'is_email_receipt', 'receipt_from_name',
                                                    'receipt_from_email','cc_receipt','bcc_receipt' ) );
        }
        
        $isEmailReceipt = CRM_Utils_Array::value( 'is_email_receipt', $value[$pageID] );
        $isOfflineRecur = false;
        if ( !$pageID && $recur->id ) $isOfflineRecur = true; 
        if ( $isOfflineRecur || $isOfflineRecur ) {
            require_once 'CRM/Core/BAO/Domain.php';
            $domainValues = CRM_Core_BAO_Domain::getNameAndEmail( );
            $receiptFrom      = "$domainValues[0] <$domainValues[1]>";
            $receiptFromName  = $domainValues[0];
            $receiptFromEmail = $domainValues[1];
            if ( $pageID ) {
                $receiptFrom = '"' . CRM_Utils_Array::value('receipt_from_name',$value[$pageID]) . '" <' . $value[$pageID]['receipt_from_email'] . '>';
                
                $receiptFromName  = $value[$pageID]['receipt_from_name'];
                $receiptFromEmail = $value[$pageID]['receipt_from_email']; 
            }
            
            require_once 'CRM/Contact/BAO/Contact/Location.php';
            list( $displayName, $email ) = CRM_Contact_BAO_Contact_Location::getEmailDetails( $contactID, false );
            $templatesParams = array( 'groupName' => 'msg_tpl_workflow_contribution',
                                      'valueName' => 'contribution_recurring_notify',
                                      'contactId' => $contactID,
                                      'tplParams' => array( 'recur_frequency_interval' => $recur->frequency_interval,
                                                            'recur_frequency_unit'     => $recur->frequency_unit,
                                                            'recur_installments'       => $recur->installments,
                                                            'recur_start_date'         => $recur->start_date,
                                                            'recur_end_date'           => $recur->end_date,
                                                            'recur_amount'             => $recur->amount,
                                                            'recur_txnType'            => $type,
                                                            'displayName'              => $displayName,
                                                            'receipt_from_name'        => $receiptFromName,
                                                            'receipt_from_email'       => $receiptFromEmail ),
                                      'from'    => $receiptFrom,
                                      'toName'  => $displayName,
                                      'toEmail' => $email,
                                      );
            
            require_once 'CRM/Core/BAO/MessageTemplates.php';
            list ($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplates::sendTemplate( $templatesParams );
            
            if ($sent) {
                CRM_Core_Error::debug_log_message('Success: mail sent for recurring notification.');
            } else {
                CRM_Core_Error::debug_log_message('Failure: mail not sent for recurring notification.');
            }
        }
    }
    
    
    /**  
     * Function to add the custom fields for contribution page (ie profile)
     * 
     * @param int    $gid            uf group id
     * @param string $name 
     * @param int    $cid            contact id
     * @param array  $params         params to build component whereclause
     *   
     * @return void  
     * @access public
     * @static  
     */ 
    function buildCustomDisplay( $gid, $name, $cid, &$template, &$params ) 
    {
        if ( $gid ) {
            require_once 'CRM/Core/BAO/UFGroup.php';
            if ( CRM_Core_BAO_UFGroup::filterUFGroups($gid, $cid) ){
                $values = array( );
                $groupTitle = null;
                $fields = CRM_Core_BAO_UFGroup::getFields( $gid, false, CRM_Core_Action::VIEW );

                foreach ( $fields as $k => $v  ) {
                    if ( ! $groupTitle ) { 
                        $groupTitle = $v["groupTitle"];
                    }
                    // suppress all file fields from display
                    if ( CRM_Utils_Array::value( 'data_type', $v, '' ) == 'File' || CRM_Utils_Array::value( 'name', $v, '' ) == 'image_URL' ) {
                        unset( $fields[$k] );
                    }
                }

                if ( $groupTitle ) {
                    $template->assign( $name."_grouptitle", $groupTitle );
                }

                CRM_Core_BAO_UFGroup::getValues( $cid, $fields, $values , false, $params );

                if ( count( $values ) ) {
                    $template->assign( $name, $values );
                }
            }
        }
    }
  
    /**
     * This function is to make a copy of a contribution page, including
     * all the blocks in the page
     *
     * @param int $id the contribution page id to copy
     *
     * @return the copy object 
     * @access public
     * @static
     */
    static function copy( $id ) 
    {
        $fieldsFix = array('prefix' => array('title' => ts('Copy of') . ' '));
        $copy =& CRM_Core_DAO::copyGeneric( 'CRM_Contribute_DAO_ContributionPage', 
                                            array( 'id' => $id ), 
                                            null, 
                                            $fieldsFix );
        
        //copying all the blocks pertaining to the contribution page
        $copyPledgeBlock =& CRM_Core_DAO::copyGeneric( 'CRM_Pledge_DAO_PledgeBlock', 
                                                       array( 'entity_id'    => $id,
                                                              'entity_table' => 'civicrm_contribution_page'),
                                                       array( 'entity_id'    => $copy->id ) );
                
        $copyMembershipBlock =& CRM_Core_DAO::copyGeneric( 'CRM_Member_DAO_MembershipBlock', 
                                                           array( 'entity_id'    => $id,
                                                                  'entity_table' => 'civicrm_contribution_page'),
                                                           array( 'entity_id'    => $copy->id ) );
        
        $copyUFJoin =& CRM_Core_DAO::copyGeneric( 'CRM_Core_DAO_UFJoin', 
                                                  array( 'entity_id'    => $id,
                                                         'entity_table' => 'civicrm_contribution_page'),
                                                  array( 'entity_id'    => $copy->id ) );

        $copyWidget =& CRM_Core_DAO::copyGeneric( 'CRM_Contribute_DAO_Widget', 
                                                  array( 'contribution_page_id' => $id ),
                                                  array( 'contribution_page_id' => $copy->id ) );
        
        
        //copy option group and values 
        require_once "CRM/Core/BAO/OptionGroup.php";
        $copy->default_amount_id = CRM_Core_BAO_OptionGroup::copyValue('contribution', 
                                                                       $id, 
                                                                       $copy->id, 
                                                                       CRM_Core_DAO::getFieldValue( 'CRM_Contribute_DAO_ContributionPage', 
                                                                                                    $id, 
                                                                                                    'default_amount_id' ) );
        $copyTellFriend =& CRM_Core_DAO::copyGeneric( 'CRM_Friend_DAO_Friend', 
                                                      array( 'entity_id'    => $id,
                                                             'entity_table' => 'civicrm_contribution_page'),
                                                      array( 'entity_id'    => $copy->id ) );
        
        $copyPersonalCampaignPages =& CRM_Core_DAO::copyGeneric( 'CRM_Contribute_DAO_PCPBlock', 
                                                                 array( 'entity_id'    => $id,
                                                                        'entity_table' => 'civicrm_contribution_page'),
                                                                 array( 'entity_id'    => $copy->id ) );
        
        $copyPremium =& CRM_Core_DAO::copyGeneric( 'CRM_Contribute_DAO_Premium', 
                                                   array( 'entity_id'    => $id,
                                                          'entity_table' => 'civicrm_contribution_page'), 
                                                   array( 'entity_id'    => $copy->id ) );
        $premiumQuery = "        
SELECT id
FROM civicrm_premiums
WHERE entity_table = 'civicrm_contribution_page'
      AND entity_id ={$id}";
        
        $premiumDao = CRM_Core_DAO::executeQuery( $premiumQuery, CRM_Core_DAO::$_nullArray );
        while ( $premiumDao->fetch( ) ) {
            if ( $premiumDao->id ) {
                $copyPremiumProduct =& CRM_Core_DAO::copyGeneric( 'CRM_Contribute_DAO_PremiumsProduct', 
                                                                  array( 'premiums_id' => $premiumDao->id ), 
                                                                  array( 'premiums_id' => $copyPremium->id ) );
            }
        }
        
        $copy->save( );
        
        require_once 'CRM/Utils/Hook.php';
        CRM_Utils_Hook::copy( 'ContributionPage', $copy );

        return $copy;
    }

    /**
     * Function to check if contribution page contains payment
     * processor that supports recurring payment
     *
     * @param int $contributionPageId Contribution Page Id
     * 
     * @return boolean true if payment processor supports recurring
     *                 else false
     *
     * @access public
     * @static
     */
    static function checkRecurPaymentProcessor( $contributionPageId ) 
    {
        $sql = "
  SELECT pp.is_recur
  FROM   civicrm_contribution_page  cp,
         civicrm_payment_processor  pp
  WHERE  cp.payment_processor_id = pp.id
    AND  cp.id = {$contributionPageId}
";
        
        if ( $recurring =& CRM_Core_DAO::singleValueQuery( $sql, CRM_Core_DAO::$_nullArray ) ) {
            return true;
        }
        return false;
    }
    
    
    /**                                                           
     * Function to get info for all sections enable/disable.
     *
     * @return array $info info regarding all sections.
     * @access public 
     */
    function getSectionInfo( $contribPageIds = array( ) ) 
    {
        $info = array( );
        $whereClause = null;
        if ( is_array( $contribPageIds ) && !empty( $contribPageIds ) ) {
            $whereClause = 'WHERE civicrm_contribution_page.id IN ( '. implode( ', ', $contribPageIds ) . ' )';
        }
        
        $sections = array( 'settings',
                           'amount',
                           'membership',
                           'custom',
                           'thankYou',
                           'friend',
                           'pcp',
                           'widget',
                           'premium', 
                           );
        $query =  "
   SELECT  civicrm_contribution_page.id as id,
           civicrm_contribution_page.contribution_type_id as settings, 
           amount_block_is_active as amount, 
           civicrm_membership_block.id as membership,
           civicrm_uf_join.id as custom,
           civicrm_contribution_page.thankyou_title as thankYou,
           civicrm_tell_friend.id as friend,
           civicrm_pcp_block.id as pcp,
           civicrm_contribution_widget.id as widget,
           civicrm_premiums.id as premium
     FROM  civicrm_contribution_page
LEFT JOIN  civicrm_membership_block    ON ( civicrm_membership_block.entity_id = civicrm_contribution_page.id
                                            AND civicrm_membership_block.entity_table = 'civicrm_contribution_page'
                                            AND civicrm_membership_block.is_active = 1 )
LEFT JOIN  civicrm_uf_join             ON ( civicrm_uf_join.entity_id = civicrm_contribution_page.id 
                                            AND civicrm_uf_join.entity_table = 'civicrm_contribution_page' 
                                            AND civicrm_uf_join.is_active = 1 )
LEFT JOIN  civicrm_tell_friend         ON ( civicrm_tell_friend.entity_id = civicrm_contribution_page.id 
                                            AND civicrm_tell_friend.entity_table = 'civicrm_contribution_page'
                                            AND civicrm_tell_friend.is_active = 1)
LEFT JOIN  civicrm_pcp_block           ON ( civicrm_pcp_block.entity_id = civicrm_contribution_page.id 
                                            AND civicrm_pcp_block.entity_table = 'civicrm_contribution_page' 
                                            AND civicrm_pcp_block.is_active = 1 ) 
LEFT JOIN  civicrm_contribution_widget ON ( civicrm_contribution_widget.contribution_page_id = civicrm_contribution_page.id 
                                            AND civicrm_contribution_widget.is_active = 1 )
LEFT JOIN  civicrm_premiums            ON ( civicrm_premiums.entity_id = civicrm_contribution_page.id 
                                            AND civicrm_premiums.entity_table = 'civicrm_contribution_page' 
                                            AND civicrm_premiums.premiums_active = 1 )
           $whereClause";
        
        $contributionPage = CRM_Core_DAO::executeQuery( $query );
        while ( $contributionPage->fetch( ) ) {
            if ( !isset( $info[$contributionPage->id] ) || !is_array( $info[$contributionPage->id] ) ) {
                $info[$contributionPage->id] = array_fill_keys( array_values( $sections ), false );
            }
            foreach ( $sections as $section ) {
                if ( $contributionPage->$section ) {
                    $info[$contributionPage->id][$section] = true;
                }
            }
        }
        
        return $info;
    }
    
}

