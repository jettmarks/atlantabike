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

class CRM_Contribute_BAO_Contribution_Utils {

    /**
     * Function to process payment after confirmation
     * 
     * @param object  $form   form object  
     * @param array   $paymentParams   array with payment related key
     * value pairs  
     * @param array   $premiumParams   array with premium related key
     * value pairs  
     * @param int     $contactID       contact id  
     * @param int     $contributionTypeId   contribution type id  
     * @param int     $component   component id  
     * 
     * @return array associated array
     *
     * @static
     * @access public
     */
    static function processConfirm( &$form, 
                                    &$paymentParams,
                                    &$premiumParams,
                                    $contactID,
                                    $contributionTypeId,
                                    $component = 'contribution' )
    { 
        require_once 'CRM/Core/Payment/Form.php';
        CRM_Core_Payment_Form::mapParams( $form->_bltID, $form->_params, $paymentParams, true );
        
        require_once 'CRM/Contribute/DAO/ContributionType.php';
        $contributionType = new CRM_Contribute_DAO_ContributionType( );
        if( isset( $paymentParams['contribution_type'] ) ) {
            $contributionType->id = $paymentParams['contribution_type'];
        } else {
            $contributionType->id = $contributionTypeId;
        }
        if ( ! $contributionType->find( true ) ) {
            CRM_Core_Error::fatal( "Could not find a system table" );
        }
        
        // add some contribution type details to the params list
        // if folks need to use it
        $paymentParams['contributionType_name']                = 
            $form->_params['contributionType_name']            = $contributionType->name;
        $paymentParams['contributionType_accounting_code']     = 
            $form->_params['contributionType_accounting_code'] = $contributionType->accounting_code;
        $paymentParams['contributionPageID']                   =
            $form->_params['contributionPageID']               = $form->_values['id'];
        
        
        if ( $form->_values['is_monetary'] && $form->_amount > 0.0 && is_array( $form->_paymentProcessor ) ) {
            require_once 'CRM/Core/Payment.php';
            $payment =& CRM_Core_Payment::singleton( $form->_mode, $form->_paymentProcessor, $form );
        }
        
        //fix for CRM-2062
        $now = date( 'YmdHis' );

        $result = null;
        if ( $form->_contributeMode == 'notify' ||
             $form->_params['is_pay_later'] ) {
            // this is not going to come back, i.e. we fill in the other details
            // when we get a callback from the payment processor
            // also add the contact ID and contribution ID to the params list
            $paymentParams['contactID'] = $form->_params['contactID'] = $contactID;
            $contribution =
                CRM_Contribute_Form_Contribution_Confirm::processContribution(
                                                                              $form,
                                                                              $paymentParams,
                                                                              null,
                                                                              $contactID,
                                                                              $contributionType, 
                                                                              true, true, true );
            $form->_params['contributionID'    ] = $contribution->id;
            $form->_params['contributionTypeID'] = $contributionType->id;
            $form->_params['item_name'         ] = $form->_params['description'];
            $form->_params['receive_date'      ] = $now;
            
            if ( $form->_values['is_recur'] &&
                 $contribution->contribution_recur_id ) {
                $form->_params['contributionRecurID'] = $contribution->contribution_recur_id;
            }
            
            $form->set( 'params', $form->_params );
            $form->postProcessPremium( $premiumParams, $contribution );
          
            if ( $form->_values['is_monetary'] && $form->_amount > 0.0 ) {
                // add qfKey so we can send to paypal
                $form->_params['qfKey'] = $form->controller->_key;
                if ( $component == 'membership' ) {
                    $membershipResult = array( 1 => $contribution );
                    return $membershipResult;
                } else {
                    if ( ! $form->_params['is_pay_later'] ) {
                        $result =& $payment->doTransferCheckout( $form->_params, 'contribute' );
                    } else {
                        // follow similar flow as IPN
                        // send the receipt mail
                        $form->set( 'params', $form->_params );
                        if ( $contributionType->is_deductible ) {
                            $form->assign('is_deductible',  true );
                            $form->set('is_deductible',  true);
                        }
                        if( isset( $paymentParams['contribution_source'] ) ) {
                            $form->_params['source'] = $paymentParams['contribution_source'];
                        }
                        
                        // get the price set values for receipt.
                        if ( $form->_priceSetId && $form->_lineItem ) {
                            $form->_values['lineItem']   = $form->_lineItem;
                            $form->_values['priceSetID'] = $form->_priceSetId;
                        }
                        
                        require_once "CRM/Contribute/BAO/ContributionPage.php";
                        $form->_values['contribution_id'] = $contribution->id;
                        CRM_Contribute_BAO_ContributionPage::sendMail( $contactID,
                                                                       $form->_values,
                                                                       $contribution->is_test );
                        return;
                    }
                }
            }
        } elseif ( $form->_contributeMode == 'express' ) {
            if ( $form->_values['is_monetary'] && $form->_amount > 0.0 ) {
				
				//LCD determine if express + recurring and direct accordingly
				if ( $paymentParams['is_recur'] == 1 ) {
					$result =& $payment->createRecurringPayments( $paymentParams );
				} else {
                $result =& $payment->doExpressCheckout( $paymentParams );
				}
				
            }
        } elseif ( $form->_values['is_monetary'] && $form->_amount > 0.0 ) {
           
            if ( $paymentParams['is_recur']  && $form->_contributeMode == 'direct' ) {

                // For recurring contribution, create Contribution Record first.
                // Contribution ID, Recurring ID and Contact ID needed 
                // When we get a callback from the payment processor

                $paymentParams['contactID'] =  $contactID;
                $contribution = CRM_Contribute_Form_Contribution_Confirm::processContribution(
                                                                                              $form,
                                                                                              $paymentParams,
                                                                                              null,
                                                                                              $contactID,
                                                                                              $contributionType, 
                                                                                              true, true, true );
                
                $paymentParams['contributionID'    ] = $contribution->id;
                $paymentParams['contributionTypeID'] = $contribution->contribution_type_id;
                $paymentParams['contributionPageID'] = $contribution->contribution_page_id;
                
                if ( $form->_values['is_recur'] && $contribution->contribution_recur_id ) {
                    $paymentParams['contributionRecurID'] = $contribution->contribution_recur_id;
                }
            }
            
            $result =& $payment->doDirectPayment( $paymentParams );
        }
        
        if ( $component == 'membership' ) {
            $membershipResult = array( );
        } 
        
        if ( is_a( $result, 'CRM_Core_Error' ) ) {
            
            //make sure to cleanup db for recurring case.
            if ( CRM_Utils_Array::value( 'contributionID', $paymentParams ) ) {
                CRM_Contribute_BAO_Contribution::deleteContribution( $paymentParams['contributionID'] );
            }
            if ( CRM_Utils_Array::value( 'contributionRecurID', $paymentParams ) ) {
                CRM_Contribute_BAO_ContributionRecur::deleteRecurContribution( $paymentParams['contributionRecurID'] );
            }
            
            if ( $component !== 'membership' ) {
                CRM_Core_Error::displaySessionError( $result );
                CRM_Utils_System::redirect( CRM_Utils_System::url( 'civicrm/contribute/transact', 
                                                                   "_qf_Main_display=true&qfKey={$form->_params['qfKey']}" ) );
            }
            $membershipResult[1] = $result;
        } else {
            if ( $result ) {
                $form->_params = array_merge( $form->_params, $result );
            }
            $form->_params['receive_date'] = $now;
            $form->set( 'params', $form->_params );
            $form->assign( 'trxn_id', $result['trxn_id'] );
            $form->assign( 'receive_date',
                           CRM_Utils_Date::mysqlToIso( $form->_params['receive_date']) );
        
            // result has all the stuff we need
            // lets archive it to a financial transaction
            if ( $contributionType->is_deductible ) {
                $form->assign('is_deductible',  true );
                $form->set('is_deductible',  true);
            }

            if ( isset( $paymentParams['contribution_source'] ) ) {
                $form->_params['source'] = $paymentParams['contribution_source'];
            }
            
            // check if pending was set to true by payment processor
            $pending = false;
            if ( CRM_Utils_Array::value( 'contribution_status_pending',
                                         $form->_params ) ) {
                $pending = true;
            }
            if ( !($paymentParams['is_recur'] && $form->_contributeMode == 'direct') ) {
                $contribution =
                    CRM_Contribute_Form_Contribution_Confirm::processContribution( $form,
                                                                                   $form->_params, $result,
                                                                                   $contactID, $contributionType,
                                                                                   true, $pending, true );
            }
            $form->postProcessPremium( $premiumParams, $contribution );
            
            $membershipResult[1] = $contribution;
        }
        
        if ( $component == 'membership' ) {
            return $membershipResult;
        }
        //Do not send an email if Recurring contribution is done via Direct Mode
        //Email will we send once the IPN will receive.
        if ( $paymentParams['is_recur'] && $form->_contributeMode == 'direct' ) {
            return true;
        }
        
        // get the price set values for receipt.
        if ( $form->_priceSetId && $form->_lineItem ) {
            $form->_values['lineItem']   = $form->_lineItem;
            $form->_values['priceSetID'] = $form->_priceSetId;
        }
        
        // finally send an email receipt
        require_once "CRM/Contribute/BAO/ContributionPage.php";
        $form->_values['contribution_id'] = $contribution->id;
        CRM_Contribute_BAO_ContributionPage::sendMail( $contactID, $form->_values, $contribution->is_test );
    }

    /**
     * Function to get the contribution details by month
     * of the year
     * @param int     $param year
     * 
     * @return array associated array
     *
     * @static
     * @access public
     */
    static function contributionChartMonthly( $param ) 
    {
        if ( $param ) {
            $param = array( 1 => array( $param, 'Integer' ) );
        } else {
            $param = date("Y");
            $param = array( 1 => array( $param, 'Integer' ) );
        }
        
        $query = "
    SELECT   sum(contrib.total_amount) AS ctAmt,
             MONTH( contrib.receive_date) AS contribMonth
      FROM   civicrm_contribution AS contrib
INNER JOIN   civicrm_contact AS contact ON ( contact.id = contrib.contact_id ) 
     WHERE   contrib.contact_id = contact.id
       AND   ( contrib.is_test = 0 OR contrib.is_test IS NULL )
       AND   contrib.contribution_status_id = 1
       AND   date_format(contrib.receive_date,'%Y') = %1 
       AND   contact.is_deleted = 0 
  GROUP BY   contribMonth
  ORDER BY   month(contrib.receive_date)";
        
        $dao = CRM_Core_DAO::executeQuery( $query, $param );
        
        $params = null;
        while ( $dao->fetch( ) ) {
            if ( $dao->contribMonth ) {
                $params['By Month'][$dao->contribMonth] = $dao->ctAmt;
            }
        } 
        return $params;
        
    }

    /**
     * Function to get the contribution details by year
     *
     * @return array associated array
     *
     * @static
     * @access public
     */
    static function contributionChartYearly( ) 
    {
        $query = '
    SELECT   sum(contrib.total_amount) AS ctAmt,
             year(contrib.receive_date) as contribYear
      FROM   civicrm_contribution AS contrib
INNER JOIN   civicrm_contact contact ON ( contact.id = contrib.contact_id ) 
     WHERE   ( contrib.is_test = 0 OR contrib.is_test IS NULL )
       AND   contrib.contribution_status_id = 1
       AND   contact.is_deleted = 0 
  GROUP BY   contribYear
  ORDER BY   contribYear';
        $dao = CRM_Core_DAO::executeQuery( $query );
        
        $params = null;
        while ( $dao->fetch( ) ) {
            if ( ! empty( $dao->contribYear ) ) {
                $params['By Year'][$dao->contribYear] = $dao->ctAmt;
            }
        }
        return $params;
    }

    static function createCMSUser( &$params, $contactID, $mail ) {
        // lets ensure we only create one CMS user
        static $created = false;

        if ( $created ) {
            return;
        }
        $created = true;

        if ( CRM_Utils_Array::value( 'cms_create_account', $params ) ) {
            $params['contactID'] = $contactID;
            require_once "CRM/Core/BAO/CMSUser.php";
            if ( ! CRM_Core_BAO_CMSUser::create( $params, $mail ) ) {
                CRM_Core_Error::statusBounce( ts('Your profile is not saved and Account is not created.') );
            }
        }
    }

    static function _fillCommonParams( &$params, $type = 'paypal' ) {
        if ( array_key_exists('transaction', $params) ) {
            $transaction =& $params['transaction']; 
        } else {
            $transaction =& $params;
        }
        
        $params['contact_type'] = 'Individual';
        
        $billingLocTypeId  = CRM_Core_DAO::getFieldValue( 'CRM_Core_DAO_LocationType', 'Billing', 'id', 'name' );
        if ( !$billingLocTypeId )  $billingLocTypeId = 1;
        if ( !CRM_Utils_System::isNull( $params['address'] ) ) {
            $params['address'][1]['is_primary']       = 1;
            $params['address'][1]['location_type_id'] = $billingLocTypeId;
        }
        if ( !CRM_Utils_System::isNull( $params['email'] ) ) {
            $params['email'] = array( 1 => array( 'email'            => $params['email'],
                                                  'location_type_id' => $billingLocTypeId ) );
        }
        
        if ( isset( $transaction['trxn_id'] ) ) {
            // set error message if transaction has already been processed.
            require_once 'CRM/Contribute/DAO/Contribution.php';
            $contribution = new CRM_Contribute_DAO_Contribution();
            $contribution->trxn_id = $transaction['trxn_id'];
            if ( $contribution->find(true) ) {
                $params['error'][] = ts( 'transaction already processed.' );
            }
        } else {
            // generate a new transaction id, if not already exist 
            $transaction['trxn_id'] = md5( uniqid( rand( ), true ) );
        }

        if ( ! isset( $transaction['contribution_type_id'] ) ) {
            require_once 'CRM/Contribute/PseudoConstant.php';
            $contributionTypes = array_keys( CRM_Contribute_PseudoConstant::contributionType( ) );
            $transaction['contribution_type_id'] = $contributionTypes[0];
        }

        if ( ($type == 'paypal') && (!isset( $transaction['net_amount'] )) ) {
            $transaction['net_amount'] = $transaction['total_amount'] - 
                CRM_Utils_Array::value( 'fee_amount', $transaction, 0 );
        }
        
        if ( ! isset( $transaction['invoice_id'] ) ) {
            $transaction['invoice_id'] = $transaction['trxn_id'];
        }

        $source = ts( 'ContributionProcessor: %1 API', 
                      array( 1 => ucfirst($type) ) );
        if ( isset( $transaction['source'] ) ) {
            $transaction['source'] = $source . ':: ' . $transaction['source'];
        } else {
            $transaction['source'] = $source;
        }

        return true;
    }

    static function formatAPIParams( $apiParams, $mapper, $type = 'paypal', $category = true ) {
        $type = strtolower($type);

        if ( ! in_array($type, array('paypal', 'google', 'csv')) ) {
            // return the params as is
            return $apiParams;
        }
        $params = $transaction = array( );
        
        if ( $type == 'paypal') {
            foreach ( $apiParams as $detail => $val ) {
                if ( isset($mapper['contact'][$detail]) ) {
                    $params[$mapper['contact'][$detail]] = $val;
                } else if ( isset($mapper['location'][$detail]) ) {
                    $params['address'][1][$mapper['location'][$detail]] = $val;
                } else if ( isset($mapper['transaction'][$detail]) ) {
                    switch ( $detail ) { 
                        case 'l_period2':
                            // Sadly, PayPal seems to send two distinct data elements in a single field,
                            // so we break them out here.  This is somewhat ugly and tragic.
                            $freqUnits = array (
                                'D' => 'day',
                                'W' => 'week',
                                'M' => 'month',
                                'Y' => 'year'
                            );
                            list($frequency_interval, $frequency_unit) = explode(' ', $val);
                            $transaction['frequency_interval'] = $frequency_interval;
                            $transaction['frequency_unit'] = $freqUnits[$frequency_unit];
                            break;
                        case 'subscriptiondate':
                        case 'timestamp':
                            // PayPal dates are in  ISO-8601 format.  We need a format that
                            // MySQL likes
                            $unix_timestamp = strtotime($val);
                            $transaction[$mapper['transaction'][$detail]] = date('YmdHis', $unix_timestamp);
                            break;
                        default:
                            $transaction[$mapper['transaction'][$detail]] = $val;
                    }
                }
            }

            if ( !empty($transaction) && $category ) {
                $params['transaction'] = $transaction;
            } else {
                $params += $transaction;
            }
            
            self::_fillCommonParams( $params, $type );
            
            return $params;
        }

        if ( $type == 'csv') {
            $header = $apiParams['header'];
            unset( $apiParams['header'] );
            foreach ( $apiParams as $key => $val ) {
                if ( isset($mapper['contact'][$header[$key]]) ) {
                    $params[$mapper['contact'][$header[$key]]] = $val;
                } else if ( isset($mapper['location'][$header[$key]]) ) {
                    $params['address'][1][$mapper['location'][$header[$key]]] = $val;
                } else if ( isset($mapper['transaction'][$header[$key]]) ) {
                    $transaction[$mapper['transaction'][$header[$key]]] = $val;
                } else {
                    $params[$header[$key]] = $val;
                }
            }

            if ( !empty($transaction) && $category ) {
                $params['transaction'] = $transaction;
            } else {
                $params += $transaction;
            }
            
            self::_fillCommonParams( $params, $type );
            
            return $params;
        }

        if ( $type == 'google' ) {
            // return if response smell invalid
            if ( ! array_key_exists('risk-information-notification', $apiParams[1][$apiParams[0]]['notifications']) ) {
                return false;
            }
            $riskInfo =& $apiParams[1][$apiParams[0]]['notifications']['risk-information-notification'];

            if ( array_key_exists('new-order-notification', $apiParams[1][$apiParams[0]]['notifications']) ) {
                $newOrder =& $apiParams[1][$apiParams[0]]['notifications']['new-order-notification'];
            }

            if ( $riskInfo['google-order-number']['VALUE'] == $apiParams[2]['google-order-number']['VALUE'] ) {
                foreach ( $riskInfo['risk-information']['billing-address'] as $field => $info ) {
                    if ( CRM_Utils_Array::value( $field, $mapper['location'] ) ) {
                        $params['address'][1][$mapper['location'][$field]] = $info['VALUE'];
                    } else if ( CRM_Utils_Array::value( $field, $mapper['contact'] ) ) {
                        if ( $newOrder && CRM_Utils_Array::value('structured-name', $newOrder['buyer-billing-address']) ) {
                            foreach ( $newOrder['buyer-billing-address']['structured-name'] as $namePart => $nameValue ) {
                                $params[$mapper['contact'][$namePart]] = $nameValue['VALUE'];  
                            }
                        } else {
                            $params[$mapper['contact'][$field]] = $info['VALUE'];
                        }
                    } else if ( CRM_Utils_Array::value( $field, $mapper['transaction'] ) ) {
                        $transaction[$mapper['transaction'][$field]] = $info['VALUE'];
                    }
                }
                
                // Response is an huge array. Lets pickup only those which we ineterested in 
                // using a local mapper, rather than traversing the entire array.
                $localMapper = 
                    array( 'google-order-number' => $riskInfo['google-order-number']['VALUE'],
                           'total-charge-amount' => $apiParams[2]['total-charge-amount']['VALUE'],
                           'currency'            => $apiParams[2]['total-charge-amount']['currency'],
                           'item-name'           => $newOrder['shopping-cart']['items']['item']['item-name']['VALUE'],
                           'timestamp'           => $apiParams[2]['timestamp']['VALUE'],
                           );
                if ( array_key_exists('latest-charge-fee', $apiParams[2]) ) {
                    $localMapper['latest-charge-fee'] = $apiParams[2]['latest-charge-fee']['total']['VALUE'];
                    $localMapper['net-amount'] = $localMapper['total-charge-amount'] - $localMapper['latest-charge-fee'];
                }
                foreach ( $localMapper as $localKey => $localVal ) {
                    if ( CRM_Utils_Array::value($localKey, $mapper['transaction']) ) {
                        $transaction[$mapper['transaction'][$localKey]] = $localVal;
                    }
                }

                if ( empty($params) && empty($transaction) ) {
                    continue;
                }
                
                if ( !empty($transaction) && $category ) {
                    $params['transaction'] = $transaction;
                } else {
                    $params += $transaction;
                }
                
                self::_fillCommonParams( $params, $type );
                
            }
            return $params;
        }
    }

    static function processAPIContribution( $params ) {
        if ( empty($params) || array_key_exists('error', $params) ) {
            return false;
        }

        // add contact using dedupe rule
        require_once 'CRM/Dedupe/Finder.php';
        $dedupeParams = CRM_Dedupe_Finder::formatParams ($params      , 'Individual');
        $dedupeParams['check_permission'] = false;
        $dupeIds      = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual');
        // if we find more than one contact, use the first one
        if ( CRM_Utils_Array::value( 0, $dupeIds ) ) {
            $params['contact_id'] = $dupeIds[0];
        }
        require_once 'CRM/Contact/BAO/Contact.php';
        $contact = CRM_Contact_BAO_Contact::create( $params );
        if ( ! $contact->id ) {
            return false;
        }

        // only pass transaction params to contribution::create, if available
        if ( array_key_exists('transaction', $params) ) {
            $params = $params['transaction'];
            $params['contact_id'] = $contact->id;
        }

        // handle contribution custom data
        $customFields = 
            CRM_Core_BAO_CustomField::getFields  ( 'Contribution',
                                                   false,
                                                   false, 
                                                   CRM_Utils_Array::value('contribution_type_id',
                                                                          $params ) );
        $params['custom'] = 
            CRM_Core_BAO_CustomField::postProcess( $params,
                                                   $customFields,
                                                   CRM_Utils_Array::value( 'id', $params, null ),
                                                   'Contribution' );
        // create contribution
        
        // if this is a recurring contribution then process it first
        if ( $params['trxn_type'] == 'subscrpayment' ) {
            // see if a recurring record already exists
            require_once 'CRM/Contribute/BAO/ContributionRecur.php';
            $recurring = new CRM_Contribute_BAO_ContributionRecur;
            $recurring->processor_id = $params['processor_id'];
            if ( ! $recurring->find( true ) ) {
                $recurring = new CRM_Contribute_BAO_ContributionRecur;
                $recurring->invoice_id = $params['invoice_id'];
                $recurring->find( true );
            }
            
            // This is the same thing the CiviCRM IPN handler does to handle
            // subsequent recurring payments to avoid duplicate contribution
            // errors due to invoice ID. See:
            // ./CRM/Core/Payment/PayPalIPN.php:200
            if ( $recurring->id ) {
                $params['invoice_id'] = md5( uniqid( rand( ), true ) );
            }
            
            $recurring->copyValues( $params );
            $recurring->save( );
            if ( is_a( $recurring, 'CRM_Core_Error' ) ) {
                return false;
            } else {
                $params['contribution_recur_id'] = $recurring->id;
            }
        }

        require_once 'CRM/Contribute/BAO/Contribution.php';
        $contribution =& CRM_Contribute_BAO_Contribution::create( $params,
                                                                  CRM_Core_DAO::$_nullArray );
        if ( ! $contribution->id ) {
            return false;
        }
        
        return true;
    }

}
