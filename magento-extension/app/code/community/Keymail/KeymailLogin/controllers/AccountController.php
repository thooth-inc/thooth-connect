<?php

# Controllers are not autoloaded so you will have to do it manually:
include_once("Mage/Customer/controllers/AccountController.php");

class Keymail_KeymailLogin_AccountController extends Mage_Customer_AccountController
{

	/**
	 * Customer login form page
	 */
	public function loginAction()
	{
		$params = $this->getRequest()->getParams();
		$session = $this->_getSession();
		$session->setBeforeAuthUrl($this->_getRefererUrl()) ;
		if(isset($params['u'])){
			$destination =  Mage::getBaseUrl(). urldecode($params['destination']);
			$session->setBeforeAuthUrl($destination) ;
			$afterLoginPassword = $this->generatePassword();
			$customer = Mage::getModel('customer/customer');
			$customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->load($params['u']);
			$username = $customer->getEmail();
			try{
				Mage::getSingleton('customer/session')->login($username, base64_decode($params['s']));
			} catch (Exception $e){
				Mage::getSingleton('customer/session')->addError("Invalid keymail link, Please check your email.");
			}
			$customer->setPassword($afterLoginPassword);
			$customer->save();

		}
			
		if ($this->_getSession()->isLoggedIn()) {
			if(isset($params['destination'])){
				// send email:
				$timeStamp = strtotime(date("Y-m-d H:i:s"));
				$destination =  $params['destination'];
				$keymail_link = Mage::getUrl('customer/account')."login/u/".Mage::getSingleton('customer/session')->getCustomer()->getId()."/t/".$timeStamp."/s/".$this->base64_encode_without_last_character($afterLoginPassword)."?destination=".$destination;
				$this->sendEmail(Mage::getSingleton('customer/session')->getCustomer()->getEmail(), "Keymail Login link  after a successful login.",$keymail_link);
				$this->_redirectUrl($session->getBeforeAuthUrl(true));
			}
			return;
		}
		$this->getResponse()->setHeader('Login-Required', 'true');
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->_initLayoutMessages('catalog/session');
		$this->renderLayout();
	}
	 //  Remove last character from the encoded string;
	protected function base64_encode_without_last_character($pass){
		return substr(base64_encode($pass),0,-1);
	}
	
	public function generatePassword(){
		$chars = "abcdefghijkmnopqrstuvwxyz023456789";
		srand((double)microtime()*1000000);
		$i = 0;
		$pass = '' ;
		while ($i <= 7) {
			$num = rand() % 33;
			$tmp = substr($chars, $num, 1);
			$pass = $pass . $tmp;
			$i++;
		}	
		return $pass;
	}

	public function loginPostAction()
	{
		$session = $this->_getSession();
		if ($session->isLoggedIn()) {
			$this->_redirect('*/*/');
			return;
		}
		$session->setEscapeMessages(true); // prevent XSS injection in user input
		if ($this->getRequest()->isPost()) {
			$customer = Mage::getModel('customer/customer');
			$curr_date = date('Y-m-d H:i:s');
			$password = $this->generatePassword();
			$email = $this->getRequest()->getPost('login');
			$email = $email['username'];
			$customer->setWebsiteId(Mage::app()->getWebsite()->getId());
			$customer->loadByEmail($email);

			$timeStamp = strtotime(date("Y-m-d H:i:s"));
			$uri = "customer/account";
			if(!$customer->getId()) {
				$customer->setEmail($email);
				if( Mage::getStoreConfig('customer/keymail/defaultname')) {
					$defaultName = explode(" ", Mage::getStoreConfig('customer/keymail/defaultname'));
					$customer->setFirstname($defaultName[0]);
					$customer->setLastname($defaultName[1]);
				} else {
					$customer->setFirstname("KeyMail");
					$customer->setLastname("User");
				}
				$customer->setPassword($password);
				$store_id = Mage::app()->getStore()->getId();
				$customer->save();
                if($session->getBeforeAuthUrl() != Mage::getBaseUrl() ){
					$beforeAuthUrl = $session->getBeforeAuthUrl();
					if($beforeAuthUrl[strlen($beforeAuthUrl)-1] == "/") {
						$beforeAuthUrl = substr($beforeAuthUrl, 0, -1);
					}
				$destination = str_replace(Mage::getBaseUrl(), "", $beforeAuthUrl);	
				$destination = urlencode($destination);
				} else {
					$beforeAuthUrl = Mage::getBaseUrl();
					$destination = "";
				}
				$keymail_link = Mage::getUrl('customer/account')."login/u/".$customer->getId()."/t/".$timeStamp."/s/".$this->base64_encode_without_last_character($password)."?destination=".$destination;
		
			    } else {
				$customer->setPassword($password);
				$customer->save();
				if($session->getBeforeAuthUrl() != Mage::getBaseUrl() ){
					$beforeAuthUrl = $session->getBeforeAuthUrl();
					if($beforeAuthUrl[strlen($beforeAuthUrl)-1] == "/") {
						$beforeAuthUrl = substr($beforeAuthUrl, 0, -1);
					}
				$destination = str_replace(Mage::getBaseUrl(), "", $beforeAuthUrl);	
				$destination = urlencode($destination);
				} else {
					$beforeAuthUrl = Mage::getBaseUrl();
					$destination = "";
				}
				$keymail_link = Mage::getUrl('customer/account')."login/u/".$customer->getId()."/t/".$timeStamp."/s/".$this->base64_encode_without_last_character($password)."?destination=".$destination;
			}
			$this->sendEmail($email, "Keymail Login link a request",$keymail_link);
			// $this->getResponse()->setRedirect($this->_getRefererUrl());

		}
	}
	/**
	 * Customer logout action
	 */
	public function logoutAction()
	{
		$timeStamp = strtotime(date("Y-m-d H:i:s"));
		// Generate new password
		$afterLogoutPassword = $this->generatePassword();
		$to = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
		// save new generated Password
		$customer = Mage::getModel('customer/customer');
		$customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($to);
		$customer->setPassword($afterLogoutPassword);
		$customer->save();
		$beforeAuthUrl = $this->_getRefererUrl();
		if($beforeAuthUrl[strlen($beforeAuthUrl)-1] == "/") {
			$beforeAuthUrl = substr($beforeAuthUrl, 0, -1);
		}
		$destination =  str_replace(Mage::getBaseUrl(), "", $beforeAuthUrl);
		$keymailLink = Mage::getUrl('customer/account')."login/u/".Mage::getSingleton('customer/session')->getCustomer()->getId()."/t/".$timeStamp."/s/".$this->base64_encode_without_last_character($afterLogoutPassword)."?destination=".urlencode($destination);
		$this->sendEmail(Mage::getSingleton('customer/session')->getCustomer()->getEmail(), "Keymail Login link after a successfull logout",$keymailLink);
		$this->_getSession()->logout()->setBeforeAuthUrl(Mage::getUrl());
		$this->_redirect('*/*/logoutSuccess');
	}
	protected function sendEmail($recipent,$subject,$link) {

		$templateId  = Mage::getStoreConfig('customer/keymail/email_template');
		$sender = Array('name'  => Mage::getStoreConfig('trans_email/ident_general/name'),
                  'email' => Mage::getStoreConfig('trans_email/ident_general/email'));
		$name = Mage::getStoreConfig('trans_email/ident_general/name');
			
		$vars = Array();
		$vars = Array('subject'=>$subject,
		               'link' =>$link
		);
		$storeId = Mage::app()->getStore()->getId();
		$translate  = Mage::getSingleton('core/translate');
		$mail = Mage::getModel('core/email_template')
		->setTemplateSubject($subject)
		->sendTransactional($templateId, $sender, $recipent, $name, $vars, $storeId);
		if (!$mail->getSentSuccess()) {
			throw new Exception();
		}
		$translate->setTranslateInline(true);
		Mage::getSingleton('customer/session')->addSuccess(Mage::helper('customer')->__('Please check your email for your Keymail login link.'));
		$this->_redirect('*/*/');
	}

	protected function strContains ($s1, $s2)
	{
		$pos = strpos(strtolower($s1), strtolower($s2));
		if ($pos !== false) {
			$answer = true;
		} else {
			$answer = false;
		}
		return $answer;
	}
}
