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
    private $_post_logout_redirect_uri;

    /**
     * @author   Carl
     * @version  1.0
     * @param string $app_id
     * @param string $app_secret
     * @param string $app_host      用户池域名，示例：https://xxxx.bitanswer.cn
     * @param string $protocol      协议，目前仅支持 “oidc”
     * @param string $redirect_uri  回调地址，必须包含在app的地址配置里
     */
    function __construct($app_id, $app_secret, $app_host, $protocol, $redirect_uri = null, $option = []) {
        $this->_app_id = $app_id;
        $this->_app_secret = $app_secret;
        $this->_app_host = $app_host;
        $this->_protocol = $protocol;
        $this->_redirect_uri = $redirect_uri;

        if (key_exists('post_logout_redirect_uri', $option)) {
            $this->_post_logout_redirect_uri = $option['post_logout_redirect_uri'];
        }
    }

    /**
     * 获取认证地址
     * @author   Carl
     * @version  1.0
     * @return string url
     * @throws Exception
     */
    function getAuthorizeUrl(array $options = []) {
        if ($this->_protocol == 'oidc') {
            return $this->getAuthorizeOidcUrl($options);
        }
        throw new Exception('Not support protocol');
    }

    /**
     * 获取登出地址
     * @author   Carl
     * @version  1.0
     * @return string url
     * @throws Exception
     */
    function getLogoutUrl(array $options = []) {
        if ($this->_protocol == 'oidc') {
            return $this->getOidcLogoutUrl($options);
        }
        throw new Exception('Not support protocol');
    }

    /**
     * 获取accessToken
     * @author   Carl
     * @version  1.0
     * @param string $code  由授权服务器返回   
     * @return json
     * @throws Exception
     */
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

    /*
     * 客户端凭据模式登录
     * @author   Carl
     * @version  1.0
     * @return json
     * @throws Exception
     */
    function getAccessTokenByClientCredentials() {
        if (empty($this->_app_secret)) {
            throw new Exception('Not found secret');
        }
        if (empty($this->_app_id)) {
            throw new Exception('Not found appid');
        }
        if ($this->_protocol == 'oidc') {
            $param = [
                'client_id' => $this->_app_id,
                'client_secret' => $this->_app_secret,
                'grant_type' => 'client_credentials'
            ];

            $url = $this->_app_host . '/oidc/token';
            $http = new Http($url);
            $http->setContentType('application/x-www-form-urlencoded');
            return $this->checkResult($http, function () use ($http, $param) {
                        return $http->post($param);
                    });
        }
        throw new Exception('Not support protocol');
    }

    /*
     * 密码模式登录
     * @author   Carl
     * @version  1.0
     * @return json
     * @throws Exception
     */
    function getAccessTokenByPassword($username, $password, array $options = []) {
        if (empty($this->_app_id)) {
            throw new Exception('Not found appid');
        }
        if ($this->_protocol == 'oidc') {
            $def_param = [
                'client_id' => $this->_app_id,
                'client_secret' => $this->_app_secret,
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password,
                'scope' => 'openid'
            ];
            $param = array_merge($def_param, $options);

            $url = $this->_app_host . '/oidc/token';
            $http = new Http($url);
            $http->setContentType('application/x-www-form-urlencoded');
            return $this->checkResult($http, function () use ($http, $param) {
                        return $http->post($param);
                    });
        }
        throw new Exception('Not support protocol');
    }
    
    /**
     * 获取用户信息
     * @author   Carl
     * @version  1.0
     * @param string $access_token   
     * @return json
     * @throws Exception
     */
    function getBitUserInfo($access_token) {
        if ($this->_protocol != 'oidc') {
            throw new Exception('Not support protocol');
        }
        return $this->getBitUserInfoByOidc($access_token);
    }

    /**
     * 更新用户信息
     * @author   Carl
     * @version  1.0
     * @param string $access_token 
     * @param array $param
     * @throws Exception
     */
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

        $url = $this->_app_host . '/oidc/bit/user/update';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');
        $http->setHeader(['Authorization' => 'bearer ' . $access_token]);
        return $this->checkResult($http, function () use ($http, $data) {
                    return $http->post($data);
                });
    }

    /**
     * 更新用户密码
     * @author   Carl
     * @version  1.0
     * @throws Exception
     */
    function bitUpdatePassword($access_token, $old_password, $new_password) {
        if ($this->_protocol != 'oidc') {
            throw new Exception('Not support protocol');
        }
        $data = [
            'old' => $old_password,
            'new' => $new_password
        ];

        $url = $this->_app_host . '/oidc/bit/user/update/password';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');
        $http->setHeader(['Authorization' => 'bearer ' . $access_token]);
        return $this->checkResult($http, function () use ($http, $data) {
                    return $http->post($data);
                });
    }

    /**
     * 更新用户邮箱
     * @author   Carl
     * @version  1.0
     * @throws Exception
     */
    function bitUpdateEmail($access_token, $email) {
        if ($this->_protocol != 'oidc') {
            throw new Exception('Not support protocol');
        }
        $data = [
            'email' => $email
        ];

        $url = $this->_app_host . '/oidc/bit/user/update/email';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');
        $http->setHeader(['Authorization' => 'bearer ' . $access_token]);
        return $this->checkResult($http, function () use ($http, $data) {
                    return $http->post($data);
                });
    }

    /**
     * 获取登录Session
     * @author   Carl
     * @version  1.0
     * @throws Exception
     */
    function getBitSessionList($access_token) {
        $url = $this->_app_host . '/oidc/bit/session';
        $http = new Http($url);

        $http->setHeader(['Authorization' => 'bearer ' . $access_token]);
        return $this->checkResult($http, function () use ($http) {
                    return $http->post();
                });
    }

    /**
     * 绑定身份源
     * @author   Carl
     * @version  1.0
     * @param string $access_token
     * @param string $identity_provider 身份源guid
     * @param string $identity_token 身份token
     * @throws Exception
     */
    function bindIdentityProvider($access_token, $identity_provider, $identity_token, $nickname) {
        $url = $this->_app_host . '/oidc/bit/bind-identity-provider';
        $http = new Http($url);
        $data = [
            'identity_provider' => $identity_provider,
            'identity_token' => $identity_token,
            'nickname' => $nickname
        ];
        $http->setHeader(['Authorization' => 'bearer ' . $access_token]);
        return $this->checkResult($http, function() use ($http, $data) {
            return $http->post($data);
        });
    }

    /**
     * 解绑身份源
     * @author   Carl
     * @version  1.0
     * @param string $access_token
     * @param string $identity_provider 身份源guid
     * @throws Exception
     */
    function unBindIdentityProvider($access_token, $identity_provider) {
        $url = $this->_app_host . '/oidc/bit/unbind-identity-provider';
        $http = new Http($url);
        $data = [
            'identity_provider' => $identity_provider
        ];
        $http->setHeader(['Authorization' => 'bearer ' . $access_token]);
        return $this->checkResult($http, function() use ($http, $data) {
            return $http->post($data);
        });
    }

    /**
    * 获取已绑定的身份源
    * @author   Carl
    * @version  1.0
    * @param string $access_token
    * @throws Exception
    */
    function getFederatedIdentity($access_token) {
        $url = $this->_app_host . '/oidc/bit/federated-identities';
        $http = new Http($url);
        
        $http->setHeader(['Authorization' => 'bearer ' . $access_token]);
        $result = $this->checkResult($http, function() use ($http) {
            return $http->post();
        });
        return $result['list'];
    }

    /**
     * 踢出帐号下的Session
     * @author   Carl
     * @version  1.0
     * @param string $bit_type      踢出类型。device:按设备踢出,account:按帐号踢出，application:按应用踢出，device_app:踢出设备上的app
     * @param string $device_index  设备编号
     * @param string $app_guid  应用的guid  
     * @throws Exception
     */
    function bitSessionEnd($access_token, $bit_type, $device_index = NULL, $app_guid = NULL) {
        $url = $this->_app_host . '/oidc/bit/session/end';
        $http = new Http($url);
        
        $data = [
            'access_token' => $access_token,
            'bit_type' => $bit_type
        ];
        if (!empty($device_index)) {
            $data['device_index'] = $device_index;
        }
        if (!empty($app_guid)) {
            $data['app_guid'] = $app_guid;
        }
        return $this->checkResult($http, function() use ($http, $data) {
            return $http->post($data);
        });
    }

    /**
     * 获取账号下的app
     * @author   Rick
     * @version  1.0
     * @throws Exception
     */
    function getBitApplication($access_token) {
        $url = $this->_app_host . '/oidc/bit/clients';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');

        $http->setHeader(['Authorization' => 'bearer ' . $access_token]);
        return $this->checkResult($http, function() use ($http) {
            return $http->post();
        });
    }

    private function getAuthorizeOidcUrl(array $options = []) {
        $map = ['client_id', 'scope', 'state', 'nonce', 'response_mode', 'response_type',
                'redirect_uri', 'code_challenge', 'code_challenge_method', 'ui_locales', 'prompt',
                'audience', 'bit_auth_type'];
        $param = [
            'nonce' => substr(rand(0, 9999) . '', 0, 4),
            'state' => substr(rand(0, 9999) . '', 0, 4),
            'scope' => 'openid profile email phone address',
            'client_id' => $this->_app_id,
            'response_type' => 'code',
            'ui_locales' => 'zh_CN en',
            'prompt' => 'consent'
        ];
        if (!empty($this->_redirect_uri)) {
            $param['redirect_uri'] = $this->_redirect_uri;
        }
        foreach ($map as $item) {
            if (!empty($options) && key_exists($item, $options)) {
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
        if (!empty($this->_redirect_uri)) {
            $param['redirect_uri'] = $this->_redirect_uri;
        }
        $url = $this->_app_host . '/oidc/token';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');
        return $this->checkResult($http, function () use ($http, $param) {
            $res = $http->post($param);
                    return $res;
                });
    }

    private function getOidcLogoutUrl(array $options = []) {
        $param = [
            'id_token_hint' => NULL,
            'logout_hint' => NULL,
            'client_id' => $this->_app_id,
            'post_logout_redirect_uri' => $this->_post_logout_redirect_uri,
            'state' => substr(rand(0, 9999) . '', 0, 4),
            'ui_locales' => 'zh_CN en'
        ];
        foreach ($options as $key => $val) {
            if (key_exists($key, $param)) {
                $param[$key] = $val;
            }
        }
        foreach ($param as $key => $val) {
            if ($val === NULL) {
                unset($param[$key]);
            }
        }
        return $this->_app_host . '/oidc/end_session_endpoint?' . http_build_query($param);
    }

    private function getBitUserInfoByOidc($access_token) {
        $url = $this->_app_host . '/oidc/bit/user';
        $http = new Http($url);
        $http->setContentType('application/x-www-form-urlencoded');

        return $this->checkResult($http, function () use ($http, $access_token) {
                    return $http->post(['access_token' => $access_token]);
                });
    }

    private static function checkResult(Http $http, $fun) {
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
