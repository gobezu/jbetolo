<?php

/**
 * A PHP5 class for invalidating Amazon CloudFront objects via its API.
 *
 * Original source:
 * https://github.com/subchild/CloudFront-PHP-Invalidator
 *
 * codebynumbers - Remove dependencies by replacing PEAR/Http_request with Curl
 */

class CloudFront {

	protected $serviceUrl;
	protected $accessKeyId;
	protected $responseBody;
	protected $responseCode;
	protected $distributionId;
	protected $debug;


	/**
	 * Constructs a CloudFront object and assigns required account values
	 * @param $accessKeyId		{String} AWS access key id
	 * @param $secretKey		{String} AWS secret key
	 * @param $distributionId	{String} CloudFront distribution id
	 * @param $serviceUrl 		{String} Optional parameter for overriding cloudfront api URL
	 */
	public function __construct($accessKeyId, $secretKey, $distributionId, $serviceUrl="https://cloudfront.amazonaws.com/"){
		$this->accessKeyId    = $accessKeyId;
		$this->secretKey      = $secretKey;
		$this->distributionId = $distributionId;
		$this->serviceUrl     = $serviceUrl;
	}


	/**
	 * Invalidates object with passed key on CloudFront
	 * @param $key 	{String|Array} Key of object to be invalidated, or set of such keys
	 */
	public function invalidate($keys){
		if (!is_array($keys)){
			$keys = array($keys);
		}
		$date       = gmdate("D, d M Y G:i:s T");
		$requestUrl = $this->serviceUrl."2010-08-01/distribution/" . $this->distributionId . "/invalidation";
		// assemble request body
		$body  = "<InvalidationBatch>";
		foreach($keys as $key){
			$key   = (preg_match("/^\//", $key)) ? $key : "/" . $key;
			$body .= "<Path>".$key."</Path>";
		}
		$body .= "<CallerReference>".time()."</CallerReference>";
		$body .= "</InvalidationBatch>";
		// make and send request

		$cURL_Session = curl_init();
		curl_setopt($cURL_Session, CURLOPT_URL, $requestUrl);
		curl_setopt($cURL_Session, CURLOPT_HTTPHEADER,
				array(
					"Date: $date",
					'Authorization: '.$this->makeKey($date),
					"Content-Type: text/xml"
					)
				);


		curl_setopt($cURL_Session, CURLOPT_USERAGENT, 'Mozilla/5.0');
		curl_setopt($cURL_Session, CURLOPT_POST, 1);
		curl_setopt($cURL_Session, CURLOPT_POSTFIELDS, $body);
		curl_setopt($cURL_Session, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($cURL_Session, CURLOPT_FOLLOWLOCATION, 1);

		$this->responseBody = curl_exec($cURL_Session);
		$this->responseCode = curl_getinfo($cURL_Session, CURLINFO_HTTP_CODE);

		$er = array();
		array_push($er, "CloudFront: Invalidating Object: $key");
		array_push($er, $requestUrl);
		array_push($er, "body: $body");
		array_push($er, "response: $response");
		array_push($er, "response string: " . $this->responseBody);
		array_push($er, "");
		array_push($er, "response code: " . $this->responseCode);
		array_push($er, "");
		$this->debug = implode("\n",$er);

		return ($this->responseCode === 201);

	}


	/**
	 * Returns header string containing encoded authentication key
	 * @param 	$date 		{Date}
	 * @return 	{String}
	 */
	public function makeKey($date){
		return "AWS " . $this->accessKeyId . ":" . base64_encode($this->hmacSha1($this->secretKey, $date));
	}


	/**
	 * Returns HMAC string
	 * @param 	$key 		{String}
	 * @param 	$date		{Date}
	 * @return 	{String}
	 */
	public function hmacSha1($key, $date){
		$blocksize = 64;
		$hashfunc  = 'sha1';
		if (strlen($key)>$blocksize){
			$key = pack('H*', $hashfunc($key));
		}
		$key  = str_pad($key,$blocksize,chr(0x00));
		$ipad = str_repeat(chr(0x36),$blocksize);
		$opad = str_repeat(chr(0x5c),$blocksize);
		$hmac = pack('H*', $hashfunc( ($key^$opad).pack('H*',$hashfunc(($key^$ipad).$date)) ));
		return $hmac;
	}

	/**
     * Returns debugging info
	 * @return {String}
     */
	public function get_debug() {
		return $this->debug;
	}

}
?>
