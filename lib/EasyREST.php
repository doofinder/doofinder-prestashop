<?php
/**
 * Class EasyREST
 * Wraps HTTP calls using cURL, aimed for accessing and testing RESTful webservice.
 * Original RestClient By Diogo Souza da Silva <manifesto@manifesto.blog.br> and modified by
 * Daniel Martin <dmartin@webimpacto.es>
 * @author    Doofinder
 * @copyright Doofinder
 * @license   GPLv3
 */

class EasyREST
{
    private $curl;
    public $url;
    public $response = "";
    public $headers = array();
    public $originalResponse = "";

    public $method = "GET";
    public $params = null;
    private $contentType = null;
    private $httpHeaders = null;
    private $file = null;

    public function __construct($followLocation = true)
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_ENCODING, "");
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_AUTOREFERER, true); // This make sure will follow redirects
        if ($followLocation) {
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true); // This too
        }
        curl_setopt($this->curl, CURLOPT_HEADER, true); // THis verbose option for extracting the headers
        global $debug_response;
        if ($debug_response) {
            curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, 5);
        }
    }

    /**
     * Execute the call to the webservice
     * @return EasyREST
     */
    public function execute()
    {
        if ($this->method === "POST") {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->params);
        } elseif ($this->method == "GET") {
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
            $this->treatURL();
        } elseif ($this->method === "PUT") {
            curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "PUT");
            if (is_object($this->params) || is_array($this->params)) {
                $params = http_build_query($this->params);
            } else {
                $params = $this->params;
            }
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $params);
        } else {
            curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $this->method);
        }
        $headers = array();
        if ($this->contentType != null) {
            array_push($headers, "Content-Type: " . $this->contentType);
        }
        if ($this->httpHeaders != null) {
            $headers = array_merge($headers, $this->httpHeaders);
        }
        if (!empty($headers)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($this->curl, CURLOPT_URL, $this->url);
        $r = curl_exec($this->curl);
        $this->originalResponse = $r;
        $this->treatResponse($r); // Extract the headers and response
        return $this;
    }

    /**
     * Treats URL
     */
    private function treatURL()
    {
        if (is_array($this->params) && count($this->params) >= 1) { // Transform parameters in key/value pars in URL
            if (!strpos($this->url, '?')) {
                $this->url .= '?';
            }
            foreach ($this->params as $k => $v) {
                $this->url .= "&" . urlencode($k) . "=" . urlencode($v);
            }
        }
        return $this->url;
    }

    /*
      * Treats the Response for extracting the Headers and Response
      */
    private function treatResponse($r)
    {
        if ($r == null or strlen($r) < 1) {
            return;
        }
        // HTTP packets define that Headers end in a blank line (\n\r) where starts the body
        $parts  = explode("\n\r", $r);
        while (preg_match('@HTTP/1.[0-1] 100 Continue@', $parts[0]) or preg_match("@Moved@", $parts[0])) {
            // Continue header must be bypass
            for ($i = 1; $i < count($parts); $i++) {
                $parts[$i - 1] = trim($parts[$i]);
            }
            unset($parts[count($parts) - 1]);
        }

        // This extract the content type
        preg_match("@Content-Type: ([a-zA-Z0-9-]+/?[a-zA-Z0-9-]*)@", $parts[0], $reg);

        if (isset($reg[1])) {
            $this->headers['content-type'] = $reg[1];
        }

        // This extracts the response header Code and Message
        preg_match("@HTTP/1.[0-1] ([0-9]{3}) ([a-zA-Z ]+)@", $parts[0], $reg);

        $this->headers['code'] = @$reg[1];
        $this->headers['message'] = @$reg[2];
        if ($this->headers['code'] == null) {
            $this->headers['code'] = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        }
        $this->response = "";
        for ($i = 1; $i < count($parts); $i++) { //This make sure that exploded response get back togheter
            if ($i > 1) {
                $this->response .= "\n\r";
            }
            $this->response .= $parts[$i];
        }
    }

    /*
      * @return array
      */
    public function getHeaders()
    {
        return $this->headers;
    }

    /*
      * @return string
      */
    public function getResponse()
    {
        return $this->response;
    }

    /*
      * @return string
      */
    public function getOriginalResponse()
    {
        return $this->originalResponse;
    }

    /*
      * HTTP response code (404,401,200,etc)
      * @return int
      */
    public function getResponseCode()
    {
        return (int) $this->headers['code'];
    }

    /*
      * HTTP response message (Not Found, Continue, etc )
      * @return string
      */
    public function getResponseMessage()
    {
        return $this->headers['message'];
    }

    /*
      * Content-Type (text/plain, application/xml, etc)
      * @return string
      */
    public function getResponseContentType()
    {
        return $this->headers['content-type'];
    }

    /**
     * This sets that will not follow redirects
     * @return EasyREST
     */
    public function setNoFollow()
    {
        curl_setopt($this->curl, CURLOPT_AUTOREFERER, false);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, false);
        return $this;
    }

    /**
     * This closes the connection and release resources
     * @return EasyREST
     */
    public function close()
    {
        curl_close($this->curl);
        $this->curl = null;
        if ($this->file != null) {
            fclose($this->file);
        }
        return $this;
    }

    /**
     * Sets the URL to be Called
     * @return EasyREST
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set the Content-Type of the request to be send
     * Format like "application/xml" or "text/plain" or other
     * @param string $contentType
     * @return EasyREST
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Set the Http Headers of the request to be send
     * @param array $httpHeaders
     * @return EasyREST
     */
    public function setHttpHeaders($httpHeaders)
    {
        $this->httpHeaders = $httpHeaders;
        return $this;
    }

    /**
     * Set the Credentials for BASIC Authentication
     * @param string $user
     * @param string $pass
     * @return EasyREST
     */
    public function setCredentials($user, $pass)
    {
        if ($user != null) {
            curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($this->curl, CURLOPT_USERPWD, "{$user}:{$pass}");
        }
        return $this;
    }

    /**
     * Set the Request HTTP Method
     * For now, only accepts GET and POST
     * @param string $method
     * @return EasyREST
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Set Parameters to be send on the request
     * It can be both a key/value par array (as in array("key"=>"value"))
     * or a string containing the body of the request, like a XML, JSON or other
     * Proper content-type should be set for the body if not a array
     * @param mixed $params
     * @return EasyREST
     */
    public function setParameters($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Creates the RESTClient
     * @param string $url=null [optional]
     * @return EasyREST
     */
    public static function createClient($url = null)
    {
        $client = new EasyREST(false);
        if ($url != null) {
            $client->setUrl($url);
        }
        return $client;
    }

    /**
     * Convenience method wrapping a commom POST call
     * @param string $url
     * @param mixed params
     * @param string $user=null [optional]
     * @param string $password=null [optional]
     * @param string $contentType="multpary/form-data" [optional] commom post (multipart/form-data) as default
     * @return EasyREST
     */
    public static function post(
        $url,
        $params = null,
        $user = null,
        $pwd = null,
        $contentType = "multipart/form-data",
        $httpHeaders = null
    ) {
        return self::call("POST", $url, $params, $user, $pwd, $contentType, $httpHeaders);
    }

    /**
     * Convenience method wrapping a commom PUT call
     * @param string $url
     * @param string $body
     * @param string $user=null [optional]
     * @param string $password=null [optional]
     * @param string $contentType=null [optional]
     * @return EasyREST
     */
    public static function put($url, $body, $user = null, $pwd = null, $contentType = null)
    {
        return self::call("PUT", $url, $body, $user, $pwd, $contentType);
    }

    /**
     * Convenience method wrapping a commom GET call
     * @param string $url
     * @param array params
     * @param string $user=null [optional]
     * @param string $password=null [optional]
     * @return EasyREST
     */
    public static function get(
        $url,
        array $params = null,
        $user = null,
        $pwd = null,
        $contentType = "multipart/form-data",
        $httpHeaders = null
    ) {
        return self::call("GET", $url, $params, $user, $pwd, $contentType, $httpHeaders);
    }

    /**
     * Convenience method wrapping a commom delete call
     * @param string $url
     * @param array params
     * @param string $user=null [optional]
     * @param string $password=null [optional]
     * @return EasyREST
     */
    public static function delete($url, array $params = null, $user = null, $pwd = null)
    {
        return self::call("DELETE", $url, $params, $user, $pwd);
    }

    /**
     * Convenience method wrapping a commom custom call
     * @param string $method
     * @param string $url
     * @param string $body
     * @param string $user=null [optional]
     * @param string $password=null [optional]
     * @param string $contentType=null [optional]
     * @param array  $httpHeaders=null [optional]
     * @return EasyREST
     */
    public static function call(
        $method,
        $url,
        $body,
        $user = null,
        $pwd = null,
        $contentType = null,
        $httpHeaders = null
    ) {
        return self::createClient($url)
            ->setParameters($body)
            ->setMethod($method)
            ->setCredentials($user, $pwd)
            ->setContentType($contentType)
            ->setHttpHeaders($httpHeaders)
            ->execute()
            ->close();
    }
}
