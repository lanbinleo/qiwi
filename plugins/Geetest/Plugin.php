<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once dirname(__FILE__) . '/lib/class.geetestlib.php';

/**
 * Qiwi GTest，用于用户登录、用户评论时使用极验提供的滑动验证码，适配 Qiwi 主题。原作者：小胖狐、饭饭、CairBin，感谢原版贡献。
 *
 * @package Qiwi GTest
 * @author Leo 里奥
 * @version 2.0.0
 * @link https://bboreo.com/
 * @link http://zsduo.com
 * @link https://ffis.me
 * @link https://cairbin.top
 *
 */
class Geetest_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        $options = Helper::options();
        if (!empty($options->plugins['activated']['QiwiCap'])) {
            throw new Typecho_Plugin_Exception(_t('启用 Qiwi GTest 前请先停用 Qiwi CAP，两个插件不能同时注册评论和登录验证接口。'));
        }
        // 添加插件动作
        // /action/geetest?do=ajaxResponseCaptchaData
        Helper::addAction('geetest', 'Geetest_Action');

        // 注册后台底部结束钩子
        Typecho_Plugin::factory('admin/footer.php')->end = array(__CLASS__, 'renderCaptcha');

        // 接管登录校验入口，在密码校验前完成后台登录验证码校验
        Typecho_Plugin::factory('Widget_User')->login = array(__CLASS__, 'login');

        // 评论钩子
        Typecho_Plugin::factory('Widget_Feedback')->comment = array(__CLASS__, 'commentCaptchaVerify');
        Typecho_Plugin::factory('Widget_Feedback')->trackback = array(__CLASS__, 'commentCaptchaVerify');
        Typecho_Plugin::factory('Widget_XmlRpc')->pingback = array(__CLASS__, 'commentCaptchaVerify');

        // 暴露插件函数（用于在自定义表单中渲染极验验证，以及在自定义逻辑中调用极验验证）
        Typecho_Plugin::factory('Geetest')->renderCaptcha = array(__CLASS__, 'renderCaptcha');
        Typecho_Plugin::factory('Geetest')->verifyCaptcha = array(__CLASS__, 'verifyCaptcha');
        Typecho_Plugin::factory('Geetest')->responseCaptchaData = array(__CLASS__, 'responseCaptchaData');

        return _t('Qiwi GTest 已启用，登录与评论验证码接口已准备好。');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeAction('geetest');
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        
        $isOpenGeetestPage = new Typecho_Widget_Helper_Form_Element_Checkbox('isOpenGeetestPage', [
            "typechoLogin" => _t('登录界面'),
            "typechoComment" => _t('评论页面')
        ], array(), _t('开启 Qiwi GTest 的页面，勾选则开启'), _t('Qiwi 主题已内置评论表单调用，无需手动编辑 comments.php。'));
        
        $captchaId = new Typecho_Widget_Helper_Form_Element_Text('captchaId', null, '', _t('公钥（ID）：'));
        $privateKey = new Typecho_Widget_Helper_Form_Element_Text('privateKey', null, '', _t('私钥（KEY）：'));

        $dismode = new Typecho_Widget_Helper_Form_Element_Select('dismod', array(
            'float' => '浮动式（float）',
            'embed' => '嵌入式（embed）',
            'popup' => '弹出框（popup）'
        ), 'float', _t('展现形式：'));

        $cdnUrl = new Typecho_Widget_Helper_Form_Element_Text('cdnUrl', null, '', _t('引入JS的CDN加速地址：'), _t('注意使用 https 协议<br />留空默认引入本地/static/gt.js文件，不知道的可留空'));

        $debugMode = new Typecho_Widget_Helper_Form_Element_Select('debugMode', array(
            '0' => '关闭',
            '1' => '开启'
        ), '0', _t('调试模式：'), _t('开启时，不会禁用提交按钮，用于测试插件是否生效。'));
        
        $form->addInput($isOpenGeetestPage);
        $form->addInput($captchaId);
        $form->addInput($privateKey);
        $form->addInput($dismode);
        $form->addInput($cdnUrl);
        $form->addInput($debugMode);
    }

    /**
     * 响应验证码数据
     */
    public static function responseCaptchaData()
    {
        @session_start();

        $pluginOptions = Helper::options()->plugin('Geetest');
        $geetestSdk = new GeetestLib($pluginOptions->captchaId, $pluginOptions->privateKey);

        $widgetRequest = Typecho_Widget::widget('Widget_Options')->request;
        $agent = $widgetRequest->getAgent();

        $data = array(
            'user_id' => rand(1000, 9999),
            'client_type' => self::isMobile($agent) ? 'h5' : 'web',
            'ip_address' => $widgetRequest->getIp()
        );

        $_SESSION['gt_server_ok'] = $geetestSdk->pre_process($data, 1);
        $_SESSION['gt_user_id'] = $data['user_id'];
        $captchaResponse = $geetestSdk->get_response();
        $_SESSION['gt_challenge'] = isset($captchaResponse['challenge']) ? $captchaResponse['challenge'] : '';

        echo $geetestSdk->get_response_str();
    }

    /**
     * 渲染后台登陆 验证码
     */
    public static function renderCaptcha()
    {
        // 判断是否登录页面
        $widgetOptions = Typecho_Widget::widget('Widget_Options');
        $widgetRequest = $widgetOptions->request;
        $currentRequestUrl = $widgetRequest->getRequestUrl();
        if (!stripos($currentRequestUrl, 'login.php')) {
            return;
        }
        // 取出插件的配置
        // 判断是否开启登陆页的验证码
        if (!self::isPageEnabled("typechoLogin")) {
            return;
        }
        $pluginOptions = Helper::options()->plugin('Geetest');
        $cdnUrl = ($pluginOptions->cdnUrl ? $pluginOptions->cdnUrl : Helper::options()->pluginUrl . '/Geetest/static/gt.min.js');
        $debugMode = (bool)($pluginOptions->debugMode);

        $disableButtonJs = '';
        $disableSubmitJs = '';
        if (!$debugMode) {
            $disableButtonJs = 'jqFormSubmit.attr({disabled:true}).addClass("gt-btn-disabled");';
            $disableSubmitJs = <<<EOF
            jqForm.submit(function (e) {
                var validate = captchaObj.getValidate();
                if (!validate) {
                    e.preventDefault();
                }
            });
EOF;
        }

        $ajaxUri = self::captchaAjaxUri();

        echo <<<EOF
        <style rel="stylesheet">
        #gt-captcha { line-height: 44px; }
        #gt-captcha .waiting { background-color: #e8e8e8; color: #4d4d4d; }
        .gt-btn-disabled { background-color: #a3b7c1!important; color: #fff!important; cursor: no-drop!important; }
        </style>
        
        <script src="{$cdnUrl}"></script>
        <script>
        
        // 获取表单提交按钮
        var jqForm = $("form");
        var jqFormSubmit = jqForm.find(":submit");
        
        // 在表单提交按钮之前添加极验验证元素
        jqFormSubmit.parent().before('<div id="gt-captcha"><p class="waiting">行为验证™ 安全组件加载中...</p></div>');
        
        // 获取极验验证元素
        var jqGtCaptcha = $("#gt-captcha");
        var jqGtCaptchaWaiting = $("#gt-captcha .waiting");
        var jqGtCaptchaNotice = $("#gt-captcha .notice");
        
        // 定义极验验证初始化回调函数
        var gtInitCallback = function (captchaObj) {
            
            captchaObj.appendTo(jqGtCaptcha);
            
            captchaObj.onSuccess(function () {
                jqFormSubmit.attr({disabled:false}).removeClass("gt-btn-disabled");
            });
            
            captchaObj.onReady(function () {
                jqGtCaptchaWaiting.remove();
                // 禁用表单提交按钮
                $disableButtonJs
            });
            
            $disableSubmitJs
        };
        
        $.ajax({
            url: "{$ajaxUri}&t=" + (new Date()).getTime(),
            type: "get",
            dataType: "json",
            success: function (data) {
                // console.log(data);
                initGeetest({
                    gt: data.gt,
                    challenge: data.challenge,
                    new_captcha: data.new_captcha,
                    product: "{$pluginOptions->dismod}",
                    offline: !data.success,
                    width: '100%'
                }, gtInitCallback);
            }
        });
        </script>
EOF;
    }

    /**
     * 渲染评论验证码
     * @throws Typecho_Plugin_Exception
     */
    public static function commentCaptchaRender() {
        //判断插件是否激活
        $options = Typecho_Widget::widget('Widget_Options');
        if (!isset($options->plugins['activated']['Geetest'])) {
            echo '<div>极验评论验证码插件未激活</div>';
            return;
        }

        //判断是否开启评论页的验证码
        if (!self::isPageEnabled("typechoComment")) {
            return;
        }
        if (!self::isThemeCaptchaEnabled()) {
            return;
        }
        // 取出插件的配置
        $pluginOptions = Helper::options()->plugin('Geetest');
        $cdnUrl = ($pluginOptions->cdnUrl ? $pluginOptions->cdnUrl : Helper::options()->pluginUrl . '/Geetest/static/gt.min.js');
        $debugMode = (bool)($pluginOptions->debugMode);
        $ajaxUri = self::captchaAjaxUri();
        $instanceId = 'qiwi-geetest-' . str_replace('.', '', uniqid('', true));
        $scriptId = $instanceId . '-script';
        $targetId = $instanceId . '-target';
        $cdnJson = json_encode($cdnUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $ajaxJson = json_encode($ajaxUri, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $productJson = json_encode($pluginOptions->dismod, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $debugJson = $debugMode ? 'true' : 'false';

        echo <<<EOF
        <style rel="stylesheet">
        .qiwi-gt-captcha { line-height: 44px; }
        .gt-btn-disabled { background-color: #a3b7c1!important; color: #fff!important; cursor: no-drop!important; }
        </style>
        <div class="qiwi-gt-captcha-root"></div>
        <script id="{$scriptId}">
        (function(script) {
            if (!script) return;

            var container = script.previousElementSibling;
            if (!container) return;

            var cdnUrl = {$cdnJson};
            var ajaxUri = {$ajaxJson};
            var product = {$productJson};
            var debugMode = {$debugJson};
            var targetId = '{$targetId}';

            container.id = targetId;
            container.className = (container.className ? container.className + ' ' : '') + 'qiwi-gt-captcha';
            container.innerHTML = '<p class="waiting">行为验证™ 安全组件加载中...</p>';

            function closestForm(element) {
                while (element && element.nodeType === 1) {
                    if (element.tagName && element.tagName.toLowerCase() === 'form') return element;
                    element = element.parentNode;
                }
                return null;
            }

            function submitButtons(form) {
                return form ? Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]')) : [];
            }

            function setSubmitState(form, disabled) {
                if (!form || debugMode) return;
                submitButtons(form).forEach(function(button) {
                    button.disabled = disabled;
                    if (disabled) {
                        button.classList.add('gt-btn-disabled');
                    } else {
                        button.classList.remove('gt-btn-disabled');
                    }
                });
            }

            function loadGeetest(callback) {
                if (window.initGeetest) {
                    callback();
                    return;
                }

                var existing = document.querySelector('script[data-qiwi-geetest-lib="' + cdnUrl + '"]');
                if (existing) {
                    existing.addEventListener('load', callback, { once: true });
                    return;
                }

                var lib = document.createElement('script');
                lib.src = cdnUrl;
                lib.async = true;
                lib.setAttribute('data-qiwi-geetest-lib', cdnUrl);
                lib.onload = callback;
                document.head.appendChild(lib);
            }

            function requestCaptcha(callback) {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', ajaxUri + '&t=' + (new Date()).getTime(), true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState !== 4) return;
                    if (xhr.status < 200 || xhr.status >= 300) return;
                    try {
                        callback(JSON.parse(xhr.responseText));
                    } catch (error) {}
                };
                xhr.send();
            }

            function isHidden(element) {
                while (element && element.nodeType === 1) {
                    if (element.hidden) return true;
                    if (window.getComputedStyle) {
                        var style = window.getComputedStyle(element);
                        if (style.display === 'none' || style.visibility === 'hidden') return true;
                    }
                    element = element.parentNode;
                }
                return false;
            }

            var visibilityObserver = null;
            var visibilityTimer = null;

            function watchVisibility() {
                if (visibilityObserver || visibilityTimer) return;

                if (window.MutationObserver) {
                    visibilityObserver = new MutationObserver(function() {
                        if (!isHidden(container)) {
                            visibilityObserver.disconnect();
                            visibilityObserver = null;
                            initialize();
                        }
                    });
                    visibilityObserver.observe(document.documentElement, {
                        attributes: true,
                        childList: true,
                        subtree: true,
                        attributeFilter: ['class', 'hidden', 'style']
                    });
                    return;
                }

                visibilityTimer = window.setInterval(function() {
                    if (!isHidden(container)) {
                        window.clearInterval(visibilityTimer);
                        visibilityTimer = null;
                        initialize();
                    }
                }, 200);
            }

            function initialize() {
                if (container.getAttribute('data-qiwi-geetest-ready') === '1') return;
                if (isHidden(container)) {
                    watchVisibility();
                    return;
                }

                container.setAttribute('data-qiwi-geetest-ready', '1');

                var form = closestForm(container);
                setSubmitState(form, true);

                loadGeetest(function() {
                    if (!window.initGeetest) return;

                    requestCaptcha(function(data) {
                        window.initGeetest({
                            gt: data.gt,
                            challenge: data.challenge,
                            new_captcha: data.new_captcha,
                            product: product,
                            offline: !data.success,
                            width: '200px'
                        }, function(captchaObj) {
                            var waiting = container.querySelector('.waiting');
                            captchaObj.appendTo('#' + targetId);

                            captchaObj.onReady(function() {
                                if (waiting && waiting.parentNode) waiting.parentNode.removeChild(waiting);
                            });

                            captchaObj.onSuccess(function() {
                                setSubmitState(form, false);
                            });

                            if (form && !debugMode) {
                                form.addEventListener('submit', function(event) {
                                    var validate = captchaObj.getValidate();
                                    if (!validate) {
                                        event.preventDefault();
                                    }
                                });
                            }
                        });
                    });
                });
            }

            function initializeSoon() {
                window.setTimeout(initialize, 0);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeSoon);
            } else {
                initializeSoon();
            }

            document.addEventListener('pjax:end', initializeSoon);
        })(document.getElementById('{$scriptId}'));
    </script>
EOF;
    }

    /**
     * 评论验证码 校验
     * @access public
     * @param array $comment 评论内容
     */
    public static function commentCaptchaVerify($comment)
    {
        if (self::shouldBypassCommentCaptcha($comment)) {
            return $comment;
        }
        if (!self::isThemeCaptchaEnabled()) {
            return $comment;
        }

        //判断是否开启评论页的验证码
        if (self::isPageEnabled("typechoComment")) {
            if (!self::_verifyCaptcha()) {
                echo "<script language=\"JavaScript\">alert(\"验证失败，请重新验证！\");window.history.go(-1);</script>";
                exit();
            }
        }
        return $comment;

    }

    /**
     * 登录校验。Typecho 没有密码校验前的登录验证码 hook，因此这里复用核心登录流程。
     */
    public static function login($name, $password, $temporarily = false, $expire = 0, $previousResult = null)
    {
        if ($previousResult !== null) {
            return $previousResult;
        }

        if (self::shouldVerifyLoginCaptcha() && !self::_verifyCaptcha()) {
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
            if ('$P$' == substr($user['password'], 0, 3)) {
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

    private static function isPageEnabled($page)
    {
        try {
            $pluginOptions = Helper::options()->plugin('Geetest');
            $enabledPages = isset($pluginOptions->isOpenGeetestPage) ? $pluginOptions->isOpenGeetestPage : array();
            return is_array($enabledPages) && in_array($page, $enabledPages, true);
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
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

    private static function shouldVerifyLoginCaptcha()
    {
        if (!self::isPageEnabled("typechoLogin")) {
            return false;
        }

        try {
            $request = Typecho_Widget::widget('Widget_Options')->request;
            return $request
                && $request->isPost()
                && (string) $request->action === 'login';
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
            Typecho_Widget::widget('Widget_Notice')->set(_t('验证码错误'), 'error');
            Typecho_Widget::widget('Widget_Options')->response->goBack();
        } catch (Exception $e) {
        } catch (Throwable $e) {
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

    private static function captchaAjaxUri()
    {
        try {
            Typecho_Widget::widget('Widget_Security')->to($security);
            return $security->getIndex('/action/geetest?do=ajaxResponseCaptchaData');
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }

        return '/index.php/action/geetest?do=ajaxResponseCaptchaData';
    }

    /**
     * 后台登陆验证码 校验
     */
    public static function verifyCaptcha()
    {
        //判断是否开启评论页的验证码
        if (self::isPageEnabled("typechoLogin")) {
            if (!self::_verifyCaptcha()) {
                Typecho_Widget::widget('Widget_Notice')->set(_t('验证码错误'), 'error');
                Typecho_Widget::widget('Widget_User')->logout();
                Typecho_Widget::widget('Widget_Options')->response->goBack();
            }
        }
    }

    /**
     * 校验验证码 方法
     *
     * @return int
     */
    private static function _verifyCaptcha()
    {
        // 如果插件渲染失败，则默认验证不通过
        if (!isset($_POST['geetest_challenge']) || !isset($_POST['geetest_validate']) || !isset($_POST['geetest_seccode'])) {
            return 0;
        }

        @session_start();

        $pluginOptions = Helper::options()->plugin('Geetest');
        $geetestSdk = new GeetestLib($pluginOptions->captchaId, $pluginOptions->privateKey);

        if (!isset($_SESSION['gt_server_ok'], $_SESSION['gt_challenge'])
            || !hash_equals((string) $_SESSION['gt_challenge'], (string) $_POST['geetest_challenge'])) {
            return 0;
        }

        if ((int) $_SESSION['gt_server_ok'] === 1) {
            if (empty($_SESSION['gt_user_id'])) {
                return 0;
            }

            $widgetRequest = Typecho_Widget::widget('Widget_Options')->request;
            $agent = $widgetRequest->getAgent();
            $clientType = self::isMobile($agent) ? 'h5' : 'web';
            $ipAddress = $widgetRequest->getIp();

            $data = array(
                'user_id' => $_SESSION['gt_user_id'],
                'client_type' => $clientType,
                'ip_address' => $ipAddress
            );

            $result = $geetestSdk->success_validate($_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode'], $data);
            if ($result) {
                unset($_SESSION['gt_server_ok'], $_SESSION['gt_user_id'], $_SESSION['gt_challenge']);
            }

            return $result;
        }

        if ((int) $_SESSION['gt_server_ok'] === 0) {
            $result = $geetestSdk->fail_validate($_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode']);
            if ($result) {
                unset($_SESSION['gt_server_ok'], $_SESSION['gt_user_id'], $_SESSION['gt_challenge']);
            }

            return $result;
        }

        return 0;
    }

    /**
     * isMobile
     *
     * @static
     * @access public
     * @return boolean
     */
    public static function isMobile($userAgent)
    {
        return preg_match('/android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $userAgent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($userAgent, 0, 4));
    }

}
