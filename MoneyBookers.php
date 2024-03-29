<?php
/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
 class Payment_Adapter_MoneyBookers
 {
  private $config = array();
	
	public function __construct($config)
    {
        $this->config = $config;
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  true,
			'description'     => 'Enter your MoneyBookers email and Secret Words to start accepting payment by MoneyBookers.',
            'form'  => array(
                'MBEmail' => array('text', array(
                            'label' => 'Email ID',
                    ),
				 ),
				'secretwords' => array('text', array(
							'label' => 'Secret Words',
					),
                 ),
            ),
        );
    }
	
    /**
     * 
     * @param type $api_admin
     * @param type $invoice_id
     * @param type $subscription
     * @see https://secure.assurebuy.com/BluePay/BluePay_bp10emu/BluePay%201-0%20Emulator.txt
     * @return string
     */
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
        $buyer = $invoice['buyer'];
        
        $p = array(
            ':id'=>sprintf('%05s', $invoice['nr']), 
            ':serie'=>$invoice['serie'], 
            ':title'=>$invoice['lines'][0]['title']
        );
        $title = __('Payment for invoice :serie:id [:title]', $p);
		
		$mode           = ($this->config['test_mode']) ? "TEST" : "LIVE";
        $type           = 'SALE';
        $amount         = $invoice['total'];
        $MBEmail     = $this->config['MBEmail'];
        $secretwords     = $this->config['secretwords'];
        $tps            = md5($secretwords.$MBEmail.$amount.$mode);
		
		$message = '';
        if(isset($_GET['Result'])) {
            $format = '<h2 style="text-align: center; color:red;">%s</h2>';
            switch ($_GET['Result']) {
                case 'APPROVED':
                    $message = sprintf($format, $_GET['MESSAGE']);
                    break;
                
                case 'ERROR':
                case 'DECLINED':
                case 'MISSING':
                default:
                    $message = sprintf($format, $_GET['MESSAGE']);
                    break;
            }
        }
		
		$html = '
				<form action="https://www.moneybookers.com/app/payment.pl" method=POST>
				<input type=hidden name=MERCHANT value="'.$MBEmail.'">
                <input type=hidden name=TRANSACTION_TYPE value="'.$type.'">
                <input type=hidden name=TAMPER_PROOF_SEAL value="'.$tps.'">
                <input type=hidden name=TPS_DEF value="MERCHANT AMOUNT MODE">
                <input type=hidden name=AMOUNT value="'.$amount.'">
                <input type=hidden name=APPROVED_URL value="'.$this->config['redirect_url'].'">
                <input type=hidden name=DECLINED_URL value="'.$this->config['cancel_url'].'">
                <input type=hidden name=MISSING_URL value="'.$this->config['return_url'].'">
                <input type=hidden name=COMMENT value="'.$title.'">
                <input type=hidden name=MODE         value="'.$mode.'">
                <input type=hidden name=AUTOCAP      value="0">
                <input type=hidden name=REBILLING    value="">
                <input type=hidden name=REB_CYCLES   value="">
                <input type=hidden name=REB_AMOUNT   value="">
                <input type=hidden name=REB_EXPR     value="">
                <input type=hidden name=REB_FIRST_DATE value="">
                <input type=hidden name=ORDER_ID value="'.$invoice['id'].'">
                <input type=hidden name=CUSTOM_ID  value="'.$invoice['id'].'">
                <input type=hidden name=INVOICE_ID  value="'.$invoice['id'].'">
    
                '.$message.'
                
                <table>
                    <tr>
                        <td>'.__('Card number').'</td>
                        <td>
                            <input type=text name=CC_NUM value="">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('CVV2').'</td>
                        <td>
                            <input type=text name=CVCCVV2 value="">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Expiration Date').'</td>
                        <td>
                            <input type=text name=CC_EXPIRES value="" placeholder="MM/YY">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Name').'</td>
                        <td>
                            <input type=text name=NAME value="'.$buyer['first_name'].' '. $buyer['last_name'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Address').'</td>
                        <td>
                            <input type=text name=Addr1 value="'.$buyer['address'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('City').'</td>
                        <td>
                            <input type=text name=CITY value="'.$buyer['city'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('State').'</td>
                        <td>
                            <input type=text name=STATE value="'.$buyer['state'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Zipcode').'</td>
                        <td>
                            <input type=text name=ZIPCODE value="'.$buyer['zip'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Phone').'</td>
                        <td>
                            <input type=text name=PHONE value="'.$buyer['phone'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Email').'</td>
                        <td>
                            <input type=text name=EMAIL value="'.$buyer['email'].'">
                        </td>
                    </tr>
                    
                    <tfoot>
                    <tr>
                        <td colspan=2>
                            <input type=SUBMIT value="'.__('Pay now').'" name=SUBMIT class="bb-button bb-button-submit bb-button-big">
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </form>
		';
		return $html;

	public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        if(APPLICATION_ENV != 'testing' && !$this->_isIpnValid($data)) {
            throw new Exception('IPN is not valid');
        }
        
        $ipn = $data['post'];
        
        $tx = $api_admin->invoice_transaction_get(array('id'=>$id));
        
        
        $d = array(
            'id'        => $id, 
            'error'     => '',
            'error_code'=> '',
            'status'    => 'processed',
            'updated_at'=> date('c'),
        );
        $api_admin->invoice_transaction_update($d);
    }
}

