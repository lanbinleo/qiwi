<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Self-hosted CAP proof-of-work CAPTCHA integration for Qiwi and Typecho.
 *
 * @package QiwiCap
 * @author  Leo 里奥
 * @version 2.0.0
 * @link    https://capjs.js.org/
 */
class QiwiCap_Plugin implements Typecho_Plugin_Interface
{
    const DEFAULT_WIDGET_SCRIPT = 'https://cdn.jsdelivr.net/npm/cap-widget@latest';

    public static function activate()
    {
        if (self::isPluginActivated('Geetest')) {
            throw new Typecho_Plugin_Exception(_t('启用 Qiwi CAP 前请先停用 Qiwi GTest，两个插件会注册相同的评论和登录验证接口。'));
        }

        Typecho_Plugin::factory('admin/footer.php')->end = array(__CLASS__, 'renderLoginCaptcha');
        Typecho_Plugin::factory('Widget_User')->login = array(__CLASS__, 'login');
        Typecho_Plugin::factory('Widget_Feedback')->comment = array(__CLASS__, 'commentCaptchaVerify');

        Typecho_Plugin::factory('QiwiCap')->renderCaptcha = array(__CLASS__, 'commentCaptchaRender');
        Typecho_Plugin::factory('QiwiCap')->verifyCaptcha = array(__CLASS__, 'verifyCaptcha');

        return _t('Qiwi CAP 已启用。请填写 CAP Server、Site Key 和 Secret Key 后再开启评论或登录验证。');
    }

    public static function deactivate()
    {
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $enabledPages = new Typecho_Widget_Helper_Form_Element_Checkbox(
            'enabledPages',
            array(
                'typechoLogin' => _t('后台登录'),
                'typechoComment' => _t('前台评论'),
            ),
            array(),
            _t('启用位置'),
            _t('评论功能需要主题在评论表单中调用 QiwiCap_Plugin::commentCaptchaRender()。配置完成前请保持未勾选。')
        );
        $form->addInput($enabledPages);

        $serverUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'serverUrl',
            null,
            '',
            _t('CAP Server URL'),
            _t('例如 https://cap.example.com。浏览器和 Typecho 服务器都必须能够访问该地址，生产环境建议使用 HTTPS。')
        );
        $serverUrl->addRule('required', _t('请填写 CAP Server URL'));
        $form->addInput($serverUrl);

        $siteKey = new Typecho_Widget_Helper_Form_Element_Text(
            'siteKey',
            null,
            '',
            _t('Site Key'),
            _t('在 CAP dashboard 中为当前站点创建的 site key。')
        );
        $siteKey->addRule('required', _t('请填写 Site Key'));
        $form->addInput($siteKey);

        $secretKey = new Typecho_Widget_Helper_Form_Element_Text(
            'secretKey',
            null,
            '',
            _t('Secret Key'),
            _t('CAP site key 对应的 secret key，不是 dashboard 的 ADMIN_KEY。该值只用于服务端验证。')
        );
        $secretKey->input->setAttribute('type', 'password');
        $secretKey->addRule('required', _t('请填写 Secret Key'));
        $form->addInput($secretKey);

        $widgetScriptUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'widgetScriptUrl',
            null,
            self::DEFAULT_WIDGET_SCRIPT,
            _t('Widget Script URL'),
            _t('默认从 jsDelivr 加载 cap-widget。也可以填写自己托管的固定版本脚本地址。生产环境建议固定版本，不使用 latest。')
        );
        $widgetScriptUrl->addRule('required', _t('请填写 Widget Script URL'));
        $form->addInput($widgetScriptUrl);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 供主题判断评论验证码是否已经启用并完成配置。
     */
    public static function canRenderCommentCaptcha()
    {
        return self::isThemeCaptchaEnabled()
            && self::isPageEnabled('typechoComment')
            && self::isConfigured();
    }

    /**
     * 输出评论表单中的 CAP widget。主题应在 form 标签内部调用。
     */
    public static function commentCaptchaRender()
    {
        if (!self::canRenderCommentCaptcha()) {
            return false;
        }

        $endpoint = self::widgetEndpoint();
        $scriptUrl = self::widgetScriptUrl();
        if ($endpoint === '' || $scriptUrl === '') {
            return false;
        }

        $endpointHtml = self::escape($endpoint);
        $endpointJson = self::json($endpoint);
        $scriptJson = self::json($scriptUrl);
        $instanceId = 'qiwi-cap-' . str_replace('.', '', uniqid('', true));
        $instanceJson = self::json($instanceId);

        echo <<<HTML
<div class="qiwi-cap-captcha" id="{$instanceId}">
    <cap-widget data-cap-api-endpoint="{$endpointHtml}"></cap-widget>
</div>
<script>
(function () {
    var instanceId = {$instanceJson};
    var endpoint = {$endpointJson};
    var scriptUrl = {$scriptJson};
    var root = document.getElementById(instanceId);
    if (!root) return;

    var widget = root.querySelector('cap-widget');
    if (widget) widget.setAttribute('data-cap-api-endpoint', endpoint);
    if (window.customElements && window.customElements.get('cap-widget')) return;

    var existing = document.querySelector('script[data-qiwi-cap-library]');
    if (existing) return;

    var script = document.createElement('script');
    script.src = scriptUrl;
    script.async = true;
    script.setAttribute('data-qiwi-cap-library', '1');
    document.head.appendChild(script);
})();
</script>
HTML;

        return true;
    }

    /**
     * 在 Typecho 后台登录表单中加入 CAP widget。
     */
    public static function renderLoginCaptcha()
    {
        if (!self::shouldVerifyLoginCaptcha(false) || !self::isLoginPage()) {
            return;
        }

        $endpoint = self::widgetEndpoint();
        $scriptUrl = self::widgetScriptUrl();
        if ($endpoint === '' || $scriptUrl === '') {
            return;
        }

        $endpointJson = self::json($endpoint);
        $scriptUrlHtml = self::escape($scriptUrl);

        echo <<<HTML
<style>
.qiwi-cap-login { margin: 0 0 1em; }
.qiwi-cap-login cap-widget { display: block; width: 100%; }
</style>
<script src="{$scriptUrlHtml}" data-qiwi-cap-library="1"></script>
<script>
(function () {
    var form = document.querySelector('form[name="login"]');
    if (!form || form.querySelector('[data-qiwi-cap-login]')) return;

    var wrapper = document.createElement('div');
    wrapper.className = 'qiwi-cap-login';
    wrapper.setAttribute('data-qiwi-cap-login', '1');

    var widget = document.createElement('cap-widget');
    widget.setAttribute('data-cap-api-endpoint', {$endpointJson});
    wrapper.appendChild(widget);

    var submit = form.querySelector('.submit');
    if (submit) {
        form.insertBefore(wrapper, submit);
    } else {
        form.appendChild(wrapper);
    }
})();
</script>
HTML;
    }

    public static function commentCaptchaVerify($comment)
    {
        if (!self::isPageEnabled('typechoComment') || !self::isConfigured()) {
            return $comment;
        }

        if (!self::isThemeCaptchaEnabled()) {
            return $comment;
        }

        if (self::shouldBypassCommentCaptcha($comment)) {
            return $comment;
        }

        if (!self::verifyCaptcha()) {
            throw new Typecho_Widget_Exception(_t('CAP 验证失败，请返回后重新完成验证。'), 403);
        }

        return $comment;
    }

    /**
     * 验证当前 POST 请求中的 cap-token。
     */
    public static function verifyCaptcha()
    {
        if (!isset($_POST['cap-token']) || is_array($_POST['cap-token'])) {
            return false;
        }

        $token = trim((string) $_POST['cap-token']);
        if ($token === '' || strlen($token) > 8192) {
            return false;
        }

        return self::verifyToken($token);
    }

    /**
     * Typecho 没有密码校验前的独立验证码 hook，因此这里复用核心登录流程。
     */
    public static function login($name, $password, $temporarily = false, $expire = 0, $previousResult = null)
    {
        if ($previousResult !== null) {
            return $previousResult;
        }

        if (self::shouldVerifyLoginCaptcha(true) && !self::verifyCaptcha()) {
            self::rejectLoginCaptcha($name);
            return false;
        }

        $userWidget = Typecho_Widget::widget('Widget_User');
        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
        $user = $db->fetchRow($db->select()
            ->from('table.users')
            ->where((strpos($name, '@') ? 'mail' : 'name') . ' = ?', $name)
            ->limit(1));

        if (empty($user)) {
            return false;
        }

        $hashValidate = Typecho_Plugin::factory('Widget_User')->trigger($hashPluggable)->hashValidate($password, $user['password']);
        if (!$hashPluggable) {
            if ('$P$' === substr($user['password'], 0, 3)) {
                $hasher = new PasswordHash(8, true);
                $hashValidate = $hasher->checkPassword($password, $user['password']);
            } else {
                $hashValidate = Typecho_Common::hashValidate($password, $user['password']);
            }
        }

        if ($user && $hashValidate) {
            if (!$temporarily) {
                $userWidget->commitLogin($user, (int) $expire);
            }

            $userWidget->push($user);
            self::setCurrentUser($userWidget, $user);
            Typecho_Plugin::factory('Widget_User')->loginSucceed($userWidget, $name, $password, $temporarily, $expire);
            return true;
        }

        Typecho_Plugin::factory('Widget_User')->loginFail($userWidget, $name, $password, $temporarily, $expire);
        return false;
    }

    private static function verifyToken($token)
    {
        $endpoint = self::verifyEndpoint();
        $options = self::options();
        $secret = isset($options->secretKey) ? trim((string) $options->secretKey) : '';
        if ($endpoint === '' || $secret === '') {
            return false;
        }

        $body = json_encode(array(
            'secret' => $secret,
            'response' => $token,
        ), JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return false;
        }

        $response = '';
        $status = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => array(
                    'Accept: application/json',
                    'Content-Type: application/json',
                ),
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ));

            $raw = curl_exec($ch);
            if ($raw !== false) {
                $response = (string) $raw;
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            }
            curl_close($ch);
        } else {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Accept: application/json\r\nContent-Type: application/json",
                    'content' => $body,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ),
                'ssl' => array(
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ),
            ));

            $raw = @file_get_contents($endpoint, false, $context);
            if ($raw !== false) {
                $response = (string) $raw;
            }
            $status = self::httpStatusFromHeaders(isset($http_response_header) ? $http_response_header : array());
        }

        if ($status < 200 || $status >= 300 || $response === '') {
            return false;
        }

        $result = json_decode($response, true);
        return is_array($result) && isset($result['success']) && $result['success'] === true;
    }

    private static function shouldVerifyLoginCaptcha($requirePost)
    {
        if (!self::isPageEnabled('typechoLogin') || !self::isConfigured()) {
            return false;
        }

        if (!$requirePost) {
            return true;
        }

        try {
            $request = Typecho_Widget::widget('Widget_Options')->request;
            return $request && $request->isPost() && (string) $request->action === 'login';
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function isLoginPage()
    {
        try {
            $request = Typecho_Widget::widget('Widget_Options')->request;
            return $request && stripos((string) $request->getRequestUrl(), 'login.php') !== false;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function rejectLoginCaptcha($name)
    {
        try {
            Typecho_Cookie::set('__typecho_remember_name', $name);
            Typecho_Widget::widget('Widget_Notice')->set(_t('CAP 验证失败，请重新完成验证。'), 'error');
            Typecho_Widget::widget('Widget_Options')->response->goBack();
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
    }

    private static function shouldBypassCommentCaptcha($comment)
    {
        if (!is_array($comment) || empty($comment['cid'])) {
            return false;
        }

        try {
            Typecho_Widget::widget('Widget_User')->to($user);
            if (!$user || !$user->hasLogin()) {
                return false;
            }

            $db = Typecho_Db::get();
            $content = $db->fetchRow($db->select('cid', 'authorId', 'template')
                ->from('table.contents')
                ->where('cid = ?', (int) $comment['cid'])
                ->where('type = ?', 'page')
                ->limit(1));

            if (empty($content) || !in_array((string) $content['template'], array('page-timemachine.php', 'page-timemachine'), true)) {
                return false;
            }

            $isOwner = isset($content['authorId']) && (int) $content['authorId'] === (int) $user->uid;
            $isAdmin = isset($user->group) && $user->group === 'administrator';
            return $isOwner || $isAdmin;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function setCurrentUser($userWidget, array $user)
    {
        $reflection = new ReflectionObject($userWidget);
        foreach (array('currentUser' => $user, 'hasLogin' => true) as $propertyName => $value) {
            if (!$reflection->hasProperty($propertyName)) {
                continue;
            }

            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($userWidget, $value);
        }
    }

    private static function isConfigured()
    {
        $options = self::options();
        return self::normalizeHttpUrl(isset($options->serverUrl) ? $options->serverUrl : '') !== ''
            && trim((string) (isset($options->siteKey) ? $options->siteKey : '')) !== ''
            && trim((string) (isset($options->secretKey) ? $options->secretKey : '')) !== ''
            && self::normalizeHttpUrl(isset($options->widgetScriptUrl) ? $options->widgetScriptUrl : self::DEFAULT_WIDGET_SCRIPT) !== '';
    }

    private static function isThemeCaptchaEnabled()
    {
        try {
            $options = Helper::options();
            return !isset($options->enabledCaptcha) || (string) $options->enabledCaptcha === '1';
        } catch (Exception $e) {
            return true;
        } catch (Throwable $e) {
            return true;
        }
    }

    private static function isPageEnabled($page)
    {
        $options = self::options();
        $enabledPages = isset($options->enabledPages) ? $options->enabledPages : array();
        return is_array($enabledPages) && in_array($page, $enabledPages, true);
    }

    private static function widgetEndpoint()
    {
        $options = self::options();
        $serverUrl = self::normalizeHttpUrl(isset($options->serverUrl) ? $options->serverUrl : '');
        $siteKey = trim((string) (isset($options->siteKey) ? $options->siteKey : ''));
        return $serverUrl !== '' && $siteKey !== '' ? $serverUrl . '/' . rawurlencode($siteKey) . '/' : '';
    }

    private static function verifyEndpoint()
    {
        $widgetEndpoint = self::widgetEndpoint();
        return $widgetEndpoint !== '' ? $widgetEndpoint . 'siteverify' : '';
    }

    private static function widgetScriptUrl()
    {
        $options = self::options();
        $value = isset($options->widgetScriptUrl) ? $options->widgetScriptUrl : self::DEFAULT_WIDGET_SCRIPT;
        return self::normalizeHttpUrl($value);
    }

    private static function normalizeHttpUrl($url)
    {
        $url = rtrim(trim((string) $url), '/');
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, array('http', 'https'), true) ? $url : '';
    }

    private static function options()
    {
        try {
            return Helper::options()->plugin('QiwiCap');
        } catch (Exception $e) {
            return new stdClass();
        } catch (Throwable $e) {
            return new stdClass();
        }
    }

    private static function isPluginActivated($pluginName)
    {
        try {
            $options = Helper::options();
            return !empty($options->plugins['activated'][$pluginName]);
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function httpStatusFromHeaders(array $headers)
    {
        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string) $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        return $status;
    }

    private static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function json($value)
    {
        return json_encode((string) $value, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
