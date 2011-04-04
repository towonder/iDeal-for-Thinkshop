<?php

/*

 * Ideal for Thinkshop (plugin)
 * Copyright 2011, To Wonder Multimedia
 *	
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright		To Wonder Multimedia
 * @link			http://www.getthinkshop.com Thinkshop Project
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 * @version			1.0 Stable

*/


class BankController extends IdealAppController {

	var $name = 'Bank';
	var $uses = array('Product', 'Metaterm', 'Metavalue', 'MetavaluesProduct', 'Extraterm', 'Extravalue', 'User', 'Order', 'OrdersProducts', 'Option', 'Photo', 'Video', 'PhotosProduct', 'VideosProduct', 'Category', 'Admin', 'Post', 'Staticpage', 'Cost', 'Setting');
	var $helpers = array('Html', 'Form', 'Number', 'Javascript', 'Crumb');
	var $components = array('Email');
	var $layout = "winkel";


	function beforeFilter(){
		$this->fetchSettings();
		$this->setCategoriesAndPages();
				
		App::import('Vendor', 'Ideal.ideal', array('file'=>'ideal'.DS.'iDEALConnector.php'));
	}
	
	
	
	function index(){
		
		$this->checkEmptyCart();
		
		$iDEALConnector = new iDEALConnector();
		
		$response = $iDEALConnector->GetIssuerList();		
			
		if($response->IsResponseError()){
			$errorMsg = $response->getErrorCode();
			$errorCode = $response->getErrorCode();
			$consumerMessage = $response->getConsumerMessage();
			$type = 0;
			Header('Location: '.HOME.'/ideal/responseError/'.$errorCode.'/'.$errorMsg.'/'.$consumerMessage.'/'.$type);
		}else{
			
			//calculate full amount in cart:
			$total = 0;
			$totalPrice = 0;
			$totalSendcost = 0;
			$amount = 0;
			$description = '';
			
			foreach($this->Session->read('Cart') as $item){
				
				$i = 0;
				foreach($item as $product){
					$i++;
					$total = $product['price'] + ($product['price'] * $product['vat']);
					$description = $product['name'];
					$totalPrice += $total;
					$totalSendcost += $product['sendcost'];
				}
				
				if($i > 1){
					$description = 'diverse';
				}
			}
			
			
			if(SENDCOST_PER_PRODUCT == 'false'){
				$totalSendcost = SENDCOST;
			}
			
			//ideal needs everything in cents:
			$amount = ($totalPrice + $totalSendcost) * 100;
			
			//everything in cents:
			$payment = array();
			$payment['amount'] = $amount;
			$payment['description'] = substr($description, 0, -2);
			$this->Session->write('payment', $payment);
			
			//create the issuerlist:
			$issuerList = $response->getIssuerFullList();
			$trans = array(" " => "&nbsp");
			
			foreach($issuerList as $issuerName => $entry){
				
					$issuerList = $issuerList . "<option value=\"" . $entry->getIssuerID() ."\">"
						. $entry->getIssuerName() . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
				
			}
			
			$issuerList .= "</option>\n";
			$this->set('issuerList', $issuerList);
		}
	}
	
	
	
	function sendTransactionRequest(){
		
		$this->checkEmptyCart();
					
		$entranceCode = $this->createCode(10);
		$payment = $this->Session->read('payment');
		
		$order = $this->Order->find('first', array('order' => 'Order.id DESC'));
		$purchaseId = $order['Order']['id'];
		$issuerId = $this->data['issuerId'];
		$amount = $payment['amount'];
		$description = $payment['description'];
		$expirationPeriod = '';
		$merchantReturnUrl = HOME .'/ideal/bankConfirm/'.$purchaseId;
		
		$iDEALConnector = new iDEALConnector();		
		
		$response = $iDEALConnector->RequestTransaction(
			$issuerId,
	        $purchaseId,
	        $amount,
	        $description,
	        $entranceCode,
	        $expirationPeriod,
	        $merchantReturnUrl
		); 
		
		
		if($response->IsResponseError()){
			$errorCode = $response->getErrorCode();
			$errorMsg = $response->getErrorMessage();
			$consumerMessage = $response->getConsumerMessage();
			$type = 1;

			Header('Location: '.HOME.'/ideal/responseError/'.$errorCode.'/'.$errorMsg.'/'.$consumerMessage.'/'.$type);
			exit();
			
		}else{
			
			$acquirerID = $response->getAcquirerID();
			$issuerAuthenticationURL = $response->getIssuerAuthenticationURL();
			$transactionID = $response->getTransactionID();
			
			$this->d['Order']['id'] = $order['Order']['id'];
			$this->d['Order']['ideal_id'] = $response->getTransactionID();
		
			if($this->Order->save($this->d)){
				Header('Location: '. $issuerAuthenticationURL);
				exit();
			}else{
				Header('Location: '.HOME.'/ideal/responseError/000/no_id_save/geen%20id%opgeslagen/iderror');
				exit();
			}
			
		}
	}
	
	
	function bankConfirm($id){
		
		$transactionID = $_GET["trxid"];
		$iDEALConnector = new iDEALConnector();		
		$response = $iDEALConnector->RequestTransactionStatus($transactionID);
		
		

		if ($response->IsResponseError()){
			// Een fout is opgetreden.
			$errorCode = $response->getErrorCode();
			$errorMsg = $response->getErrorMessage();
			$consumerMessage = $response->getConsumerMessage();
			$type = 3;
			
			Header('Location: '.HOME.'/ideal/responseError/'.$errorCode.'/'.$errorMsg.'/'.$consumerMessage.'/'.$type);
			exit();
			
		}else{
		
			$status = $response->getStatus();
		
			if($status == 1){
				
				$this->Session->destroy('payment');
				$this->data['Order']['id'] = $id;
				$this->data['Order']['paid'] = '1';
				if($this->Order->save($this->data)){
					$this->Session->write('method', 'ideal');
					Header('Location: '.HOME.'/winkel/bevestigOrder/'.$id.'/false');
					exit();
				}
				
			}else{
				
				$errorCode = '0';
				$errorMsg = 'Betaling niet aangekomen';
				$consumerMessage = $errorMsg;
				$type = 4;
				
				//Header('Location: '.HOME.'/ideal/responseError/'.$errorCode.'/'.$errorMsg.'/'.$consumerMessage.'/'.$type);					
				Header('Location: '.HOME.'/ideal/nopayment');
				exit();
			}
		}
	}
	
	function nopayment(){
		
	}
	
	
	
	function responseError($code, $msg, $consumerMessage, $type =null){
		//ladie-da.
		$this->set('code', $code);
		$this->set('msg', $msg);
		$this->set('type', $type);
	}
	
	
	
	//Admin functions:
	function settings(){
		
		$this->layout = 'admin';
		
	}
	
	
	//	Use cronjobs to auto-check any open payments:
	// >> */10 * * * * curl -o --url /dev/null [[YOUR URL]]/ideal/autoCheck
	
	function autoCheck(){
		$this->layout = '';
		$error = '';
		$now = date('Y-m-d H:i:s', strtotime('-10 minutes', time()));
		$orders = $this->Order->find('all', array('conditions' => array('Order.paid' => '0', 'Order.created >=' => $now, 'Order.method'=> 'ideal')));
		$iDEALConnector = new iDEALConnector();
		
		if(!empty($orders)){
			foreach($orders as $order){
				if($order['Order']['ideal_id'] != '0' && $order['Order']['ideal_id'] != ''):
				$response = $iDEALConnector->RequestTransactionStatus($order['Order']['ideal_id']);
				
				if(!empty($response)){
					$status = $response->getStatus();
				}else{
					$status = '';
				}
				
				if($status == 1){

					$this->data['Order']['id'] = $order['Order']['id'];
					$this->data['Order']['paid'] = '1';
										
					if($this->Order->save($this->data)){

						$this->set('order', $order);
						$this->set('method', 'ideal');
						$products = $this->getOrderProducts($order['Order']['id']);
						$this->set('products', $products);

						$this->Email->template = 'seller';
						$this->Email->to = CONTACT_EMAIL;
						//$this->Email->to = 'luc.princen@gmail.com';
						$this->Email->sendAs = 'both';
						$this->Email->from = 'noreply@'.strtolower(WEBSITE_TITLE).'.nl';
			        	$this->Email->subject = 'Een nieuwe bestelling'; 
						$this->Email->send();
						$this->Email->reset();
						
						$this->Email->template = 'buyer';
						$this->Email->to = $order['User']['email'];
						$this->Email->sendAs = 'both';
						$this->Email->from = 'noreply@'.strtolower(WEBSITE_TITLE).'.nl';
						$this->Email->subject = 'Bedankt voor uw bestelling';
						$this->Email->send();	
						
						$error .= ', saved';	
						
					}else{
						
						$products = $this->getOrderProducts($order_id);
						$this->set('products', $products);

						$this->Email->template = 'seller';
				        $this->Email->to = CONTACT_EMAIL;
						$this->Email->sendAs = 'both';
						$this->Email->from = 'noreply@'.strtolower(WEBSITE_TITLE).'.nl';
			        	$this->Email->subject = 'Een nieuwe bestelling'; 
						$this->Email->send();
						$this->Email->reset();
						
						$this->Email->template = 'buyer';
						$this->Email->to = $order['User']['email'];
						$this->Email->sendAs = 'both';
						$this->Email->from = 'noreply@'.strtolower(WEBSITE_TITLE).'.nl';
						$this->Email->subject = 'Bedankt voor uw bestelling';
						$this->Email->send();		
						
						$error .= ', notsaved';
					}


				}else{
					$this->set('order', $order);
					$this->Email->template = 'buyererror';
					$this->Email->to = $order['User']['email'];
					$this->Email->sendAs = 'both';
					$this->Email->from = 'noreply@'.strtolower(WEBSITE_TITLE).'.nl';
					$this->Email->subject = 'Uw bestelling is mislukt...';
					$this->Email->send();		
					
					
					$op = $this->OrdersProducts->find('all', array('conditions' => array('OrdersProducts.order_id' => $order['Order']['id'])));
					foreach($op as $p){
						$this->OrdersProducts->delete($p['OrdersProducts']['id']);
					}
					
					$this->Order->delete($order['Order']['id']);
					$error .= ', no order';
				}	
				
				else:
					$this->set('order', $order);
					$this->Email->template = 'buyererror';
					$this->Email->to = $order['User']['email'];
					$this->Email->sendAs = 'both';
					$this->Email->from = 'noreply@'.strtolower(WEBSITE_TITLE).'.nl';
					$this->Email->subject = 'Uw bestelling is mislukt...';
					$this->Email->send();		
					
					
					$op = $this->OrdersProducts->find('all', array('conditions' => array('OrdersProducts.order_id' => $order['Order']['id'])));
					foreach($op as $p){
						$this->OrdersProducts->delete($p['OrdersProducts']['id']);
					}
					
					$this->Order->delete($order['Order']['id']);
					$error .= ', no order';
				endif;		
			}
		}
		
		$this->pa($error);
	}
	
		
	function generatePrivateKey(){
		$this->layout = '';
		$this->set('code', $this->createCode(10));
	}
	

}
?>