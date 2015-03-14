<?php
namespace replyPUSH\replybyemail\vendor;

/**
 * @@ ReplyPushError @@
 *
 * Custom error used in validation
 * of credentials, data and hash method
 */

class ReplyPushError extends \Exception{
	
	/**
	 * The item associated with the error
	 * 
	 * @var string
	 */
	protected $item = '';
	
	public function __construct($item, $message){
		parent::__construct($message);
		$this->item = $item;
	}
	
	/**
	 * @@ getItem @@
	 * 
	 * Getter for item
	 * 
	 * @return string
	 */
	
	public function getItem(){
		return $this->item;
	}
}

/**
 * @@ ReplyPush @@
 *
 * API Class for creating and checking
 * ReplyPush authentication references
 * 
 * Including 56 bytes of data
 * for you own verification 
 */

class ReplyPush{
	
   /**
	* The API Account associated with the notification url 
	* 
	* @var string
	*/
	private $accountNo;
	
   /**
	* The API ID used for salting the data
	* 
	* @var string
	*/
	private $secretID;
	
	/**
	 * The API Key used as a hmac key
	 * 
	 * @var string
	 */
	private $secretKey;
	
	/**
	 * raw 56 byte reference data (only set for comparison)
	 * 
	 * @var string
	 */
	public $referenceData;
	
	/**
	 * Data salted with ID and email
	 * 
	 * @var string
	 */
	private $securedData;
	
	/**
	 * HMAC hash of secured (not obscured) data
	 * 
	 * @var string
	 */
	private $securedHash;
	
	
	/**
	 * HMAC hash for comaprision
	 * 
	 * @var string
	 */
	private $hashCompare;
	
	/**
	 * Full base64 identifier of data appended to securedHash
	 * 
	 * @var string
	 */
	public $identifier;
	
	
	/**
	 * The constructor
	 * 
	 * Set up credentials and identifier
	 * 
	 * @param string $accountNo API Account No
	 * @param string $secretID API ID
	 * @param string $secretKey API Key
	 * @param string $email valid email used for salting
	 * @param string $data 56 byte reference data (custom verification)
	 * @param string $hashMethod optional; valid hash algorithm used with HMAC, default to 'sha1'
	 * 
	 */
	function __construct($accountNo, $secretID, $secretKey, $email, $data, $hashMethod = 'sha1'){
		
		//if a reference get data and hash method from that
		if(self::isReference($data)){
			extract(self::explodeReference($data));
			if(isset($hashCompare)){
				$this->hashCompare = $hashCompare;
				$this->referenceData = $data;
			}
			
		}else{
			//if only custom bytes prepend accountNo and hashMethod
			if(strlen($data)==40){
				$data = sprintf("%-8s%-8s",$accountNo,$hashMethod).$data;
			}
		}

		self::validateCredentials($accountNo, $secretID, $secretKey);
		
		$this->secretID = $secretID;
		$this->secretKey = $secretKey;
		$this->accountNo = $accountNo;
		$this->createIdentifier($email, $data, $hashMethod);
	}
	
	/**
	 * @@ createIdentifier @@
	 *
	 * Creates base64 identifier of data
	 * appended to securedHash
	 * 
	 * @param string $email used to salt data
	 * @param string $data any 56 byte reference data that you wish verify on return 
	 * @param string $hashMethod the hash algorithm used with HMAC
	 *
	 * @return void
	 */
	
	protected function createIdentifier($email, $data, $hashMethod){
		
		if(strlen($data)!=56)
			throw new ReplyPushError('data', 'You need to supply a 56 byte string as data (40 custom bytes)');
			

		if((!function_exists('hash_hmac') &&
			!(in_array($hashMethod,array('md5','sha1')) &&
			function_exists($hashMethod))) ||
			!in_array($hashMethod,hash_algos())){
				throw new ReplyPushError('hashMethod', 'Hash algorithm supplied is not supported by your setup');
		}
			
		
		$this->securedData = "{$this->secretID}{$email}{$data}";
		$this->securedHash = $this->hmac($hashMethod);
		$this->identifier = base64_encode($data.$this->securedHash);
		
	}
	
	/**
	 * @@ hmac @@
	 *
	 * Wrapper around hash_hmac or alternative
	 * to hash pre-salted data
	 * 
	 * @param string $hashMethod md5, sha1, etc
	 *
	 * @return string
	 */
	
	protected function hmac($hashMethod){
		if(function_exists('hash_hmac')){
			return hash_hmac($hashMethod, $this->securedData, $this->secretKey, true);
		}else{
			
			$blocksize = 64;
			$secretKey = $this->secretKey;
			$securedData = $this->securedData;
			if(strlen($secretKey) > $blocksize)
				$secretKey = $hashMethod($secretKey, true);
				
			$secretKey = $secretKey . str_repeat(chr(0), $blocksize - strlen($secretKey));
			

			$keyPadOuter = substr($secretKey, 0, 256) ^ str_repeat(chr(0x5C), 256);
			$keyPadInner = substr($secretKey, 0, 256) ^ str_repeat(chr(0x36), 256);

			return $hashMethod($keyPadOuter . $hashMethod($keyPadInner . $securedData, true), true);
		}
	}
	
	/**
	 * @@ hashCheck @@
	 *
	 * Compares hash with data
	 *
	 * @return bool
	 */
	
	public function hashCheck(){
		return (isset($this->hashCompare)) ? $this->hashCmp($this->securedHash, $this->hashCompare): false;
	}
	
	/**
	 * @@ hashCmp @@
	 *
	 * Timing neutral string comparison
	 * 
	 * @param string $a
	 * @param string $b
	 * 
	 * @return bool
	 */
	
	protected function hashCmp($a, $b){
		if (strlen($a) != strlen($b))
			return FALSE;
		$result = 0;
		foreach(array_combine(str_split($a), str_split($b)) as $x => $y){
			$result |= ord($y) ^ ord($y);
		}
		return $result == 0;
	}
	
	/**
	 * @@ hashCheck @@
	 *
	 * Email friendly reference of identifier
	 * 
	 * @param bool $withBrakets optional; whether is encased in angle brackets, defaults to true
	 * 
	 * @return string
	 */
	public function reference($withBrakets = true){
		if($withBrakets)
			return '<' .$this->identifier.'@replypush.com>';
		else
			return $this->identifier.'@replypush.com';
	}
	
	/**
	 * @@ isReference @@
	 *
	 * validate incoming reference
	 * 
	 * @param $reference
	 * 
	 * @return bool
	 */    
	
	public static function isReference($reference){
		return preg_match('`^<([A-Za-z0-9+/=]{92,})@replypush\.com>$`i', $reference);
	}
	
	/**
	 * @@ explodeReference @@
	 *
	 * break incoming reference into component parts
	 * 
	 * @param $reference
	 * 
	 * @return array[string]string
	 */    
	public static function explodeReference($reference){
		$data = base64_decode(str_ireplace('@replypush.com','',substr($reference,1,-1)));
	   
		return array(
			'data' => substr($data,0,56),
			'hashCompare' => substr($data,56),
			'hashMethod' => trim(substr($data,8,8))
		);
	}
	
	
	/**
	 * @@ hashCheck @@
	 *
	 * Static helper to validate credentials
	 * before storing for reference
	 * 
	 * Use in try/catch process ReplyPushError
	 * 
	 * @return void
	 */
	public static function validateCredentials($accountNo, $secretID, $secretKey){
		
		$punct =  preg_quote('!"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~','`');
		
		if(!preg_match('`^[a-f0-9]{8}$`i',$accountNo)){
			throw new ReplyPushError('accountNo', 'Account No should be 8 character long hexadecimal');
		}
		if(!preg_match('`^[a-z0-9'.$punct.']{32}$`i',$secretID)){
			throw new ReplyPushError('secretID', 'Secret ID should be 32 characters long with alphanumeric and punctuation characters');
		}
		if(!preg_match('`^[a-z0-9'.$punct.']{32}$`i',$secretKey)){
			throw new ReplyPushError('secretKey', 'Secret Key should be 32 characters long with alphanumeric and punctuation characters');
		}
	}
}
