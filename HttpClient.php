<?php

namespace Moop\FrameworkBundle\Helper\Util;

use Symfony\Component\HttpFoundation\Response;

/**
 * Basic wrapper around cURL.
 * 
 * @author Austin Shinpaugh <ashinpaugh@gmail.com>
 */
class HttpClient
{
    /**
     * The cURL handle.
     * 
     * @var resource
     */
    protected $handle;

    /**
     * The Response.
     * 
     * @var Response
     */
    protected $response;
    
    /**
     * Constructor.
     * 
     * @param String $url
     */
    public function __construct($url = null)
    {
        $this->handle = curl_init();
        
        if (null !== $url) {
            $this->setEndpoint($url);
        }
        
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * The URI to make a Rest call to.
     * 
     * @param String $url
     * 
     * @return HttpClient
     */
    public function setEndpoint($url)
    {
        $url_parts = parse_url($url);
        if (true === array_key_exists('port', $url_parts)) {
            curl_setopt($this->handle, CURLOPT_PORT, $url_parts['port']);
            
            unset($url_parts['port']);
        }
        
        curl_setopt($this->handle, CURLOPT_URL, $this->unparseURL($url_parts));
        return $this;
    }

    /**
     * Set the Request method to use.
     * 
     * @param String $method
     * 
     * @return HttpClient
     */
    public function setMethod($method)
    {
        $opt = null;
        switch (strtoupper($method)) {
            case 'POST':
                $opt = CURLOPT_POST;
                break;
            case 'PUT':
                $opt = CURLOPT_PUT;
                break;
            default:
                $opt = CURLOPT_HTTPGET;
        }
        
        curl_setopt($this->handle, $opt, true);
        
        return $this;
    }
    
    /**
     * Set the time it takes to timeout (in seconds).
     * 
     * @param integer $timeout
     * 
     * @return HttpClient
     */
    public function setTimeout($timeout)
    {
        curl_setopt($this->handle, CURLOPT_TIMEOUT, $timeout);
        return $this;
    }

    /**
     * Set a curl option.
     * 
     * @param integer $curl_opt
     * @param mixed   $value
     * 
     * @return HttpClient
     */
    public function setOption($curl_opt, $value)
    {
        curl_setopt($this->handle, $curl_opt, $value);
        return $this;
    }

    /**
     * Set several curl options at once.
     * 
     * @param array $options
     * 
     * return HttpClient
     */
    public function setOptions(array $options)
    {
        foreach ($options as $curl_opt => $value) {
            $this->setOption($curl_opt, $value);
        }
        
        return $this;
    }

    /**
     * Set the params to send with the request.
     * 
     * @param array $params
     * 
     * @return HttpClient
     */
    public function setHttParams(array $params)
    {
        $query = http_build_query($params);
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, $query);
        
        return $this;
    }
    
    /**
     * Perform the request.
     * 
     * @return Response
     * @throws \ErrorException
     */
    public function execute()
    {
        curl_setopt($this->handle, CURLOPT_HEADER, true);
        
        if ($result = curl_exec($this->handle)) {
            return ($this->response = $this->createResponse($result));
        }
        
        throw new \ErrorException(
            curl_error($this->handle),
            curl_errno($this->handle)
        );
    }

    /**
     * Return the Response.
     * 
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Creates a standard Symfony Response based on the results of the
     * cURL request.
     * 
     * @param String $result
     * 
     * @return Response
     */
    protected function createResponse($result)
    {
        /* This will break if:
         * A) The request ends up using a proxy.
         * B) The proxy modifies the headers.
         */
        $header_size = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
        $header      = substr($result, 0, $header_size);
        $body        = substr($result, $header_size);
        
        $headers    = array();
        $header_arr = explode("\n", $header);
        
        foreach ($header_arr as $header) {
            if (false === strpos($header, ':')) {
                continue;
            }
            
            list($type, $value) = explode(':', $header);
            $headers[$type]     = trim($value);
        }
        
        return new Response(
            $body,
            curl_getinfo($this->handle, CURLINFO_HTTP_CODE),
            $headers
        );
    }

    /**
     * Takes a parsed URL and returns a string.
     * 
     * @see http://www.php.net/manual/en/function.parse-url.php#106731
     * 
     * @param array $parsed_url
     * 
     * @return string
     */
    protected function unparseURL(array $parsed_url)
    {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        
        return "$scheme$user$pass$host$port$path$query$fragment";
    }
    
    public function __destruct()
    {
        curl_close($this->handle);
    }
}
