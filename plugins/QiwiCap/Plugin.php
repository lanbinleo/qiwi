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

        $caBundlePath = new Typecho_Widget_Helper_Form_Element_Text(
            'caBundlePath',
            null,
            '',
            _t('CA 证书路径（可选）'),
            _t('当 PHP 请求 CAP Server 提示 unable to get local issuer certificate 时填写。留空会自动读取 php.ini、Linux 系统证书和常见 phpstudy/Git for Windows 证书路径。')
        );
        $form->addInput($caBundlePath);
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
        $guardScript = self::guardJavascript();

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
    if (widget) {
        widget.setAttribute('data-cap-api-endpoint', endpoint);
        widget.setAttribute('required', '');
    }

    {$guardScript}

    if (!(window.customElements && window.customElements.get('cap-widget'))) {
        var existing = document.querySelector('script[data-qiwi-cap-library]');
        if (!existing) {
            var script = document.createElement('script');
            script.src = scriptUrl;
            script.async = true;
            script.setAttribute('data-qiwi-cap-library', '1');
            script.onerror = function () {
                if (widget) EventTarget.prototype.dispatchEvent.call(widget, new CustomEvent('error', { detail: { isCap: true, message: 'CAP 组件加载失败' } }));
            };
            document.head.appendChild(script);
        }
    }

    var form = root.closest('form');
    if (form && widget && window.QiwiCapGuard) window.QiwiCapGuard(form, widget, root);
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
        $guardScript = self::guardJavascript();

        echo <<<HTML
<style>
.qiwi-cap-login { margin: 0 0 1em; }
.qiwi-cap-login cap-widget { display: block; width: 100%; }
.qiwi-cap-status { margin: .55em 0 0; color: #999; font-size: 12px; line-height: 1.6; }
.qiwi-cap-status.is-error { color: #c0392b; }
.qiwi-cap-status.is-verified { color: #2e7d32; }
.qiwi-cap-login ~ .submit button:disabled { cursor: not-allowed; opacity: .55; }
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
    widget.setAttribute('required', '');
    wrapper.appendChild(widget);

    var submit = form.querySelector('.submit');
    if (submit) {
        form.insertBefore(wrapper, submit);
    } else {
        form.appendChild(wrapper);
    }

    {$guardScript}
    if (window.QiwiCapGuard) window.QiwiCapGuard(form, widget, wrapper);
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
            $reason = self::requestToken() === ''
                ? _t('尚未完成 CAP 人机验证。')
                : _t('CAP 验证已失效或未通过。');
            throw new Typecho_Widget_Exception(self::commentCaptchaErrorMessage($comment, $reason), 403);
        }

        return $comment;
    }

    /**
     * 验证当前 POST 请求中的 cap-token。
     */
    public static function verifyCaptcha()
    {
        $token = self::requestToken();
        if ($token === '') {
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
            $message = self::requestToken() === ''
                ? _t('请先完成 CAP 人机验证，再登录后台。')
                : _t('CAP 验证已失效或未通过，请重新验证。');
            self::rejectLoginCaptcha($name, $message);
            return false;
        }

        $userWidget = Typecho_Widget::widget('Widget_User');
        $db = class_exists('Typecho_Db') ? Typecho_Db::get() : \Typecho\Db::get();
        $user = $db->fetchRow($db->select()
            ->from('table.users')
            ->where('name = ?', $name)
            ->limit(1));

        if (empty($user) && strpos($name, '@') !== false) {
            $user = $db->fetchRow($db->select()
                ->from('table.users')
                ->where('mail = ?', $name)
                ->limit(1));
        }

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
        $caBundle = self::caBundlePath();

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
            if ($caBundle !== '') {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
            }

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
            if ($caBundle !== '') {
                $contextOptions = stream_context_get_options($context);
                $contextOptions['ssl']['cafile'] = $caBundle;
                $context = stream_context_create($contextOptions);
            }

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

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = (string) parse_url($requestUri, PHP_URL_PATH);
        return $method === 'POST' && preg_match('~/(?:index\.php/)?action/login(?:/|$)~i', $path) === 1;
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

    private static function rejectLoginCaptcha($name, $message)
    {
        try {
            Typecho_Cookie::set('__typecho_remember_name', $name);
            Typecho_Widget::widget('Widget_Notice')->set($message, 'error');
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

    private static function requestToken()
    {
        if (!isset($_POST['cap-token']) || is_array($_POST['cap-token'])) {
            return '';
        }

        $token = trim((string) $_POST['cap-token']);
        return $token !== '' && strlen($token) <= 8192 ? $token : '';
    }

    private static function commentCaptchaErrorMessage($comment, $reason)
    {
        $text = is_array($comment) && isset($comment['text']) ? (string) $comment['text'] : '';
        $escapedText = self::escape($text);
        $escapedReason = self::escape($reason);

        return <<<HTML
<style>
.qiwi-cap-error{max-width:680px;margin:0 auto;color:#3f3b36}.qiwi-cap-error h1{margin:0 0 12px;font-size:22px;color:#2f2a25}.qiwi-cap-error p{margin:0 0 16px;line-height:1.8}.qiwi-cap-error textarea{box-sizing:border-box;width:100%;min-height:180px;padding:12px;border:1px solid #d8d1c7;border-radius:8px;background:#faf8f4;color:#3f3b36;font:14px/1.75 ui-monospace,SFMono-Regular,Consolas,monospace;resize:vertical}.qiwi-cap-error-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.qiwi-cap-error button{padding:8px 14px;border:1px solid #b9aa98;border-radius:999px;background:transparent;color:#6f5741;cursor:pointer}.qiwi-cap-error button:hover{background:#f2ece4}
</style>
<div class="qiwi-cap-error">
    <h1>评论没有提交</h1>
    <p>{$escapedReason} 原评论内容已经保留，你可以复制后返回重新验证。</p>
    <textarea id="qiwi-cap-comment-copy" readonly>{$escapedText}</textarea>
    <div class="qiwi-cap-error-actions">
        <button type="button" onclick="var t=document.getElementById('qiwi-cap-comment-copy');if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t.value).then(function(){alert('评论内容已复制');});}else{t.focus();t.select();document.execCommand('copy');alert('评论内容已复制');}">复制评论内容</button>
        <button type="button" onclick="history.back()">返回继续编辑</button>
    </div>
</div>
HTML;
    }

    private static function guardJavascript()
    {
        return <<<'JS'
    if (!window.QiwiCapGuard) {
        window.QiwiCapGuard = function (form, widget, container) {
            if (!form || !widget || widget.dataset.qiwiCapGuard === '1') return;
            widget.dataset.qiwiCapGuard = '1';
            widget.setAttribute('required', '');

            var controls = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
            var status = container.querySelector('[data-qiwi-cap-status]');
            var relocationToken = '';
            var relocationTimer = null;
            var relocationPending = false;
            var commentsRoot = form.closest('#comments');
            if (!status) {
                status = document.createElement('p');
                status.className = 'qiwi-cap-status';
                status.setAttribute('data-qiwi-cap-status', '1');
                status.setAttribute('role', 'status');
                status.setAttribute('aria-live', 'polite');
                container.appendChild(status);
            }

            controls.forEach(function (control) {
                if (!control.hasAttribute('data-qiwi-cap-was-disabled')) {
                    control.setAttribute('data-qiwi-cap-was-disabled', control.disabled ? '1' : '0');
                    control.setAttribute('data-qiwi-cap-title', control.getAttribute('title') || '');
                }
            });

            function tokenValue() {
                var hidden = widget.querySelector('input[name="cap-token"]');
                return String(widget.token || widget.tokenValue || (hidden && hidden.value) || '').trim();
            }

            function update(verified, message, isError) {
                form.classList.toggle('is-cap-verified', verified);
                form.classList.toggle('is-cap-pending', !verified);
                status.classList.toggle('is-verified', verified);
                status.classList.toggle('is-error', Boolean(isError));
                status.textContent = message;
                controls.forEach(function (control) {
                    var originallyDisabled = control.getAttribute('data-qiwi-cap-was-disabled') === '1';
                    control.disabled = originallyDisabled || !verified;
                    control.setAttribute('aria-disabled', control.disabled ? 'true' : 'false');
                    control.setAttribute('title', verified ? control.getAttribute('data-qiwi-cap-title') : '请先完成人机验证');
                });
            }

            function cleanReconnectedWidget() {
                if (!widget.shadowRoot) return;
                ['.captcha-trigger', '.cap-troubleshoot-link', '.credits'].forEach(function (selector) {
                    var nodes = Array.prototype.slice.call(widget.shadowRoot.querySelectorAll(selector));
                    nodes.slice(0, -1).forEach(function (node) { node.remove(); });
                });
            }

            function restoreAfterRelocation(attempt) {
                if (!widget.isConnected) {
                    if (attempt < 80) relocationTimer = window.setTimeout(function () { restoreAfterRelocation(attempt + 1); }, 25);
                    return;
                }

                var hidden = widget.querySelector('input[name="cap-token"]');
                if (!hidden) {
                    if (attempt < 80) relocationTimer = window.setTimeout(function () { restoreAfterRelocation(attempt + 1); }, 25);
                    return;
                }

                cleanReconnectedWidget();

                if (!relocationToken) {
                    relocationPending = false;
                    update(false, '请先完成人机验证。', false);
                    return;
                }

                hidden.value = relocationToken;
                widget.token = relocationToken;
                relocationPending = false;
                EventTarget.prototype.dispatchEvent.call(widget, new CustomEvent('solve', {
                    bubbles: true,
                    composed: true,
                    detail: { token: relocationToken }
                }));
            }

            widget.addEventListener('solve', function (event) {
                var solvedToken = String((event.detail && event.detail.token) || tokenValue()).trim();
                if (solvedToken) relocationToken = solvedToken;
                update(Boolean(solvedToken), '验证已完成，可以提交。', false);
            });
            widget.addEventListener('reset', function () {
                if (relocationPending || !widget.isConnected) {
                    var preservedToken = tokenValue();
                    if (preservedToken) relocationToken = preservedToken;
                    if (relocationTimer !== null) window.clearTimeout(relocationTimer);
                    relocationTimer = window.setTimeout(function () { restoreAfterRelocation(0); }, 0);
                    return;
                }
                relocationToken = '';
                update(false, '验证已失效，请重新完成人机验证。', true);
            });
            widget.addEventListener('error', function () {
                update(false, '人机验证暂时没有完成，请重试。', true);
            });
            form.addEventListener('submit', function (event) {
                if (tokenValue()) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                update(false, '请先完成人机验证，再提交。', true);
                try { widget.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (error) { widget.scrollIntoView(); }
                if (typeof widget.focus === 'function') widget.focus();
            }, true);

            if (commentsRoot) {
                commentsRoot.addEventListener('click', function (event) {
                    var trigger = event.target.closest('.comment-reply a, #cancel-comment-reply-link');
                    if (!trigger) return;
                    relocationPending = true;
                    var currentToken = tokenValue();
                    if (currentToken) relocationToken = currentToken;
                    window.setTimeout(function () {
                        if (relocationPending && widget.isConnected && !form.classList.contains('is-cap-verified')) {
                            relocationPending = false;
                        }
                    }, 1000);
                }, true);
            }

            update(Boolean(tokenValue()), tokenValue() ? '验证已完成，可以提交。' : '请先完成人机验证。', false);
        };
    }
JS;
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

    private static function caBundlePath()
    {
        $options = self::options();
        $configured = isset($options->caBundlePath) ? trim((string) $options->caBundlePath) : '';
        $candidates = array(
            $configured,
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'cacert.pem',
            __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem',
            'C:\\Program Files\\Git\\usr\\ssl\\certs\\ca-bundle.crt',
            'C:\\Program Files\\Git\\mingw64\\ssl\\certs\\ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
        );

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || !is_file($candidate) || !is_readable($candidate)) {
                continue;
            }

            $resolved = realpath($candidate);
            return $resolved !== false ? $resolved : $candidate;
        }

        return '';
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
