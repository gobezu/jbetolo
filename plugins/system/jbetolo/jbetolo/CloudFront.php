<?php
//$Copyright$
/**
 * Original source:
 * https://github.com/subchild/CloudFront-PHP-Invalidator and
 * http://stevejenkins.com/blog/2011/09/simple-cloudfront-invalidation-of-a-single-file-via-http/
 */
class CloudFront {

        protected $secretKey, $accessKeyId, $distributionId;
        protected $responseCode;
        protected $responseBody;
        protected $debug;
        protected $cfVersion = '2010-11-01';
        protected $host = 'cloudfront.amazonaws.com';

        public function __construct($accessKeyId, $secretKey, $distributionId, $version = '2012-05-05') {
                $this->accessKeyId = $accessKeyId;
                $this->secretKey = $secretKey;
                $this->distributionId = $distributionId;
                $this->cfVersion = $version;
        }

        public function invalidate($files, $method = 'io') {
                if (empty($files)) 
                        return false;
                
                if (is_string($files) && strpos($files, '%%')) 
                        $files = explode('%%', $files);

                $files = (array) $files;

                $method = strtolower($method);
                $result = '';

                if ($method == 'io') {
                        $result = $this->invalidateIO($files);
                } else if ($method == 'curl') {
                        $result = $this->invalidateCurl($files);
                }
                
                return $result;
        }

        protected function invalidateIO($files) {
                $body = $this->getBody($files);
                $len = strlen($body);
                $date = gmdate('D, d M Y G:i:s T');
                
                $msg = "POST /{$this->cfVersion}/distribution/{$this->distributionId}/invalidation HTTP/1.0\r\n";
                $msg .= "Host: {$this->host}\r\n";
                $msg .= "Date: {$date}\r\n";
                $msg .= "Content-Type: text/xml; charset=UTF-8\r\n";
                $msg .= $this->auth($date)."\r\n";
                $msg .= "Content-Length: {$len}\r\n\r\n";
                $msg .= $body;
                
                $fp = fsockopen('ssl://'.$this->host, 443, $errno, $errstr, 30);
                
                if (!$fp) {
                        die("Connection failed: {$errno} {$errstr}\n");
                }
                
                fwrite($fp, $msg);
                $resp = '';
                
                while(! feof($fp)) {
                        $resp .= fgets($fp, 1024);
                }
                
                fclose($fp);                

                $this->responseBody = $this->debug = $resp;

                return strpos($resp, 'HTTP/1.1 201 Created') !== false;
        }

        protected function invalidateCurl($files) {
                $date = gmdate("D, d M Y G:i:s T");
                $requestUrl = "https://{$this->host}/{$this->cfVersion}/distribution/{$this->distributionId}/invalidation";
                $body = $this->getBody($files);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $requestUrl);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Date: $date",
                    $this->auth($date),
                    "Content-Type: text/xml; charset=UTF-8",
                    "Host: {$this->host}"
                        )
                );
                curl_setopt($ch, CURLOPT_HEADER, 1);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);

                $this->responseBody = curl_exec($ch);
                $this->responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);

                $er = array();
                array_push($er, "CloudFront: Invalidating Object: " . implode(", ", $files));
                array_push($er, $requestUrl);
                array_push($er, "Sent body: $body");
                array_push($er, "Response code: " . $this->responseCode);
                array_push($er, "Response: " . $this->responseBody);
                $this->debug = implode("\n", $er);

                return ($this->responseCode === 201);
        }

        protected function getBody($files) {
                $epoch = date('U');
                $paths = '';

                foreach ($files as $file) {
                        $file = strpos($file, '/') === 0 ? $file : '/' . $file;
                        $paths .= '<Path>' . $file . '</Path>';
                }

                $qty = count($files);

                if ($this->cfVersion == '2012-05-05') {
                        $body = <<<XML
<InvalidationBatch xmlns="http://cloudfront.amazonaws.com/doc/2012-05-05/">
        <Paths>
                <Quantity>{$qty}</Quantity>
                <Items>
                        {$paths}
                </Items>
        </Paths>
        <CallerReference>jbetolo{$epoch}</CallerReference>
</InvalidationBatch>
XML;
                } else {
                        $body = <<<XML
<InvalidationBatch>
    {$paths}
    <CallerReference>jbetolo{$epoch}</CallerReference>
</InvalidationBatch>       
XML;
                }

                return $body;
        }

        public function auth($date) {
                return "Authorization: AWS " .$this->accessKeyId . ":" . base64_encode(hash_hmac('sha1', $date, $this->secretKey, true));
        }

        public function get_debug() {
                return $this->debug;
        }
}

?>