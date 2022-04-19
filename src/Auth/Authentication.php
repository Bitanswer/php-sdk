<?php

namespace Bit\Auth;

use \Exception;
use \Bit\Http;

class Authentication {

    private $_app_id;
    private $_app_secret;
    private $_app_host;
    private $_protocol;
    private $_redirect_uri;

    function __construct($app_id, $app_secret, $app_host, $protocol, $redirect_uri = null) {
        $this->_app_id = $app_id;
        $this->_app_secret = $app_secret;
        $this->_app_host = $app_host;
        $this->_protocol = $protocol;
        $this->_redirect_uri = $redirect_uri;
    }

    function getAuthorizeUrl(array $options = []) {
        if ($this->_protocol == 'oidc') {
            return $this->getAuthorizeOidcUrl($options);
        }
        throw new Exception('Not support protocol');
    }

    function getLogoutUrl(array $options = []) {
        if ($this->_protocol == 'oidc') {
            return $this->getOidcLogoutUrl($options);
        }
        throw new Exception('Not support protocol');
    }

    function getAccessTokenByCode($code) {
        if (empty($this->_app_secret)) {
            throw new Exception('Not found secret');
        }
        if (empty($this->_app_id)) {
            throw new Exception('Not found appid');
        }
        if ($this->_protocol == 'oidc') {
            return $this->getOidcAccessTokenByCode($code);
        }
        throw new Exception('Not support protocol');
    }

    function getBitUserInfo($access_token) {
        if ($this->_protocol != 'oidc') {
            throw new Exception('Not support protocol');
        }
        return $this->getBitUserInfoByOidc($access_token);
    }

    function bitUpdateUser($access_token, $param) {
        if ($this->_protocol != 'oidc') {
            throw new Exception('Not support protocol');
        }
        $data = [];
        $field = ['login_name', 'name', 'logo'];
        foreach ($field as $item) {
            if (key_exists($item, $param)) {
                $data[$item] = $param[$item];
            }
        }
        if (empty($data)) {
            throw new Exception('Not found data');
        }
        $data['access_token'] = $access_token;

        $url = $this->_app_host . '/oidc/bit/user/update';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');
        return $this->checkResult($http, function() use ($http, $data) {
            return $http->post($data);
        });
    }

    function bitUpdatePassword($access_token, $old_password, $new_password) {
        if ($this->_protocol != 'oidc') {
            throw new Exception('Not support protocol');
        }
        $data = [
            'access_token' => $access_token,
            'old' => $old_password,
            'new' => $new_password
        ];

        $url = $this->_app_host . '/oidc/bit/user/update/password';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');
        return $this->checkResult($http, function() use ($http, $data) {
            return $http->post($data);
        });
    }

    function bitUpdateEmail($access_token, $email) {
        if ($this->_protocol != 'oidc') {
            throw new Exception('Not support protocol');
        }
        $data = [
            'access_token' => $access_token,
            'email' => $email
        ];

        $url = $this->_app_host . '/oidc/bit/user/update/email';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');
        return $this->checkResult($http, function() use ($http, $data) {
            return $http->post($data);
        });
    }

    private function getAuthorizeOidcUrl(array $options = []) {
        $map = ['client_id', 'scope', 'state', 'nonce', 'response_mode', 'response_type', 'redirect_uri', 'code_challenge', 'code_challenge_method'];
        $param = [
            'nonce' => substr(rand(0, 9999) . '', 0, 4),
            'state' => substr(rand(0, 9999) . '', 0, 4),
            'scope' => 'openid profile email phone address',
            'client_id' => $this->_app_id,
            'response_mode' => 'query',
            'response_type' => 'code',
            'redirect_uri' => $this->_redirect_uri
        ];
        foreach ($map as $item) {
            if (!empty($option) && key_exists($item, $option)) {
                if ($item == 'scope' && strpos($options[$item], 'offline_access')) {
                    $param['prompt'] = 'consent';
                }
                $param[$item] = $options[$item];
            }
        }
        return $this->_app_host . '/oidc/auth?' . http_build_query($param);
    }

    private function getOidcAccessTokenByCode($code) {
        $param = [
            'client_id' => $this->_app_id,
            'client_secret' => $this->_app_secret,
            'grant_type' => 'authorization_code',
            'code' => $code
        ];

        $url = $this->_app_host . '/oidc/token';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');
        return $this->checkResult($http, function() use ($http, $param) {
            return $http->post($param);
        });
    }

    private function getOidcLogoutUrl(array $options = []) {
        // Support redirect_uri
        return $this->_app_host . '/oidc/logout';
    }

    function getBitUserInfoByOidc($access_token) {
        $url = $this->_app_host . '/oidc/bit/user';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');

        return $this->checkResult($http, function() use ($http, $access_token) {
            return $http->post(['access_token' => $access_token]);
        });
    }

    static function checkResult(Http $http, $fun) {
        try {
            $response = $fun();
            if (empty($response)) {
                return $response;
            }
            $json_arr = json_decode($response, true);
            if ($json_arr === null) {
                throw new Exception('json decode faild, data: ' . $response);
            }
        } catch (Exception $e) {
            $response = $http->getResult();
            if (empty($response)) {
                throw $e;
            }
            $json_arr = json_decode($response, true);
            if ($json_arr === null) {
                throw new Exception('json decode faild, data: ' . $response);
            }
        }
        return $json_arr;
    }

}
