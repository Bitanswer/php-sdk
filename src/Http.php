<?php
namespace Bit;

use \Exception;

class Http {
    const ERROR_INIT_CURL = 0x1;
    const ERROR_CURL_EXEC = 0x2;
    const ERROR_MAX       = 0x32;

    private $_url;
    private $_header = [];
    private $_content_type;
    private $_result;
    private $_http_info;

    function __construct($url) {
        $this->_content_type = 'application/json';
        $this->_url = $url;
    }

    function setHeader($header) {
        $this->_header = $header;
    }

    function setContentType($type) {
        $this->_content_type = $type;
    }

    function send($method_str, $data = [], $timeout = 30000) {
        $this->_http_info = [];
        $this->_result = null;
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_HTTP_VERSION => 1,
            CURLOPT_NOSIGNAL => true
        ];
        $header = array_merge(['Content-Type' => $this->_content_type], $this->_header);

        $method = strtoupper($method_str);
        $url = $this->_url;
        if ($method == 'GET' && !empty($data)) {
            if (strpos($url, '?') === FALSE) {
                $url .= '?';
            }
            if (!in_array($url[strlen($url) - 1], ['?', '&'])) {
                $url .= '&';
            }
            $url .= http_build_query($data);
            $data = [];
        }

        if ($method == 'POST') {
            $opts[CURLOPT_POST] = true;
        }

        if (!empty($data)) {
            switch ($this->_content_type) {
                case 'application/json':
                    $data = is_array($data) ? json_encode($data) : $data;
                    $header['Content-Length'] = strlen($data);
                    break;
                default :
                    $data = is_array($data) ? http_build_query($data) : $data;
                    break;
            }
        }
        foreach ($header as $key => $val) {
            $opts[CURLOPT_HTTPHEADER][] = "{$key}: {$val}";
        }

        $opts[CURLOPT_URL] = $url;
        if (!empty($data)) {
            $opts[CURLOPT_POSTFIELDS] = $data;
        }
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        
        $opts[CURLOPT_CONNECTTIMEOUT_MS] = 3000;
        $opts[CURLOPT_TIMEOUT_MS] = $timeout;
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = false;

        $ch = curl_init();
        if (empty($ch)) {
            throw new Exception('Init curl faild', );
        }
        try {
            if (!curl_setopt_array($ch, $opts)) {
                throw new Exception('Curl setopt faild', static::ERROR_INIT_CURL);
            }
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
            $result = curl_exec($ch);
            if ($result === false) {
                throw new Exception('Curl exec faild', static::ERROR_CURL_EXEC);
            }
            $this->_result = $result;

            $error_code = curl_errno($ch);
            if ($error_code !== 0) {
                $error_msg = curl_error($ch);
                throw new Exception("curl exec error, code:{$error_code},msg:{$error_msg}", static::ERROR_CURL_EXEC);
            }
            $http_info = curl_getinfo($ch);
            $this->_http_info = $http_info;
            if (empty($http_info['http_code']) || !in_array($http_info['http_code'], [200, 201])) {
                throw new Exception("http error, code:{$http_info['http_code']}, url: {$http_info['url']}, result: {$result}", $http_info['http_code']);
            }
            return $result;
        } catch (Exception $e) {
            throw $e;
        } finally {
            curl_close($ch);
        }
    }

    function get($data) {
        return $this->send('get', $data);
    }

    function post($data) {
        return $this->send('post', $data);
    }

    function getResult() {
        return $this->_result;
    }

    function getHttpInfo() {
        return $this->_http_info;
    }
}
