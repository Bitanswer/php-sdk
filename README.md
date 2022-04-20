# php-sdk
# 安装
使用 composer 进行安装。
```shell
composer require bitanswer/php-sdk
```
# 认证接口
```php
use Bit\Auth\Authentication;

$authentication = new Authentication(
    $app_id, // 用来唯一标识App
    $app_secret, // app密码
    $app_host, // 用户池域名
    $protocol, // 认证协议，目前仅支持oidc
    $redirect_uri = null); // 回调地址

// 获取登录地址
$authentication->getAuthorizeUrl();
// 获取accessToken
$authentication->getAccessTokenByCode($code);
// 获取登出地址
$authentication->getLogoutUrl();
```
上述参数，在注册为 [bitanswer](https://account.bitanswer.cn/?app=bit) 用户后，均可以得到。

# 错误处理
通过try catch 捕获错误。
```php
try {
    $authentication = new Authentication(...);
    $authentication->getAuthorizeUrl();
} catch (\Exception $e) {
    print_r($e);
}

```