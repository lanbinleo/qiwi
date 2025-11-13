<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点 LOGO 地址'),
        _t('在这里填入一个图片 URL 地址, 以在网站标题前加上一个 LOGO')
    );

    $form->addInput($logoUrl);

    $sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        [
            'ShowRecentPosts'    => _t('显示最新文章'),
            'ShowRecentComments' => _t('显示最近回复'),
            'ShowCategory'       => _t('显示分类'),
            'ShowArchive'        => _t('显示归档'),
            'ShowOther'          => _t('显示其它杂项')
        ],
        ['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowOther'],
        _t('侧边栏显示')
    );


    // 关于页面信息aboutName / Bio / avatar
    $aboutBio = new Typecho_Widget_Helper_Form_Element_Text('aboutBio', null, null, _t('关于页面 - 简介'), _t('在这里填写你的简介，将显示在关于页面的个人信息卡片中'));
    $aboutAvatar = new Typecho_Widget_Helper_Form_Element_Text('aboutAvatar', null, null, _t('关于页面 - 头像'), _t('在这里填写你的头像URL地址，将显示在关于页面的个人信息卡片中，留空则显示默认头像'));

    $form->addInput($aboutBio);
    $form->addInput($aboutAvatar);

    $form->addInput($sidebarBlock->multiMode());

    // Captcha Script 是否安装Geetest插件并启用 https://github.com/CairBin/typecho-plugin-geetest.git；单选题
    $enabledCaptcha = new Typecho_Widget_Helper_Form_Element_Radio(
        'enabledCaptcha',
        array(
            '1' => _t('启用'),
            '0' => _t('关闭')
        ),
        '0',
        _t('启用验证码'),
        _t('如果你已经安装并启用了 Geetest 插件，可以选择启用验证码功能<br>https://github.com/CairBin/typecho-plugin-geetest.git')
    );

    $form->addInput($enabledCaptcha);


    // === 新增功能配置 ===
    // 背景图片配置
    $backgroundImages = new Typecho_Widget_Helper_Form_Element_Textarea(
        'backgroundImages',
        null,
        null,
        _t('全局背景图片'),
        _t('每行填入一个图片URL地址，多个URL将随机选择显示。留空则不显示背景图片。')
    );
    $form->addInput($backgroundImages);

    // 背景遮罩透明度
    $backgroundMask = new Typecho_Widget_Helper_Form_Element_Radio(
        'backgroundMask',
        array(
            '0.3' => _t('浅色遮罩 (30%)'),
            '0.5' => _t('中等遮罩 (50%)'),
            '0.7' => _t('深色遮罩 (70%)')
        ),
        '0.5',
        _t('背景遮罩强度'),
        _t('调整背景图片的遮罩深度，确保文字可读性')
    );
    $form->addInput($backgroundMask);

    // 首页头图展示方式
    $homeThumbnailLayout = new Typecho_Widget_Helper_Form_Element_Radio(
        'homeThumbnailLayout',
        array(
            'top' => _t('顶部展示 (传统)'),
            'side' => _t('左右布局 (图文并排)')
        ),
        'top',
        _t('首页头图展示方式'),
        _t('选择首页文章列表中头图的展示布局方式')
    );
    $form->addInput($homeThumbnailLayout);

    // 自定义CSS / JS / 页脚信息 / JS追踪代码
    $customCSS = new Typecho_Widget_Helper_Form_Element_Textarea('customCSS', null, null, _t('自定义 CSS'), _t('在这里填写自定义 CSS 代码'));
    $customJS = new Typecho_Widget_Helper_Form_Element_Textarea('customJS', null, null, _t('自定义 JS'), _t('在这里填写自定义 JS代码'));
    $footerInfo = new Typecho_Widget_Helper_Form_Element_Text('footerInfo', null, null, _t('页脚信息'), _t('在这里填写页脚信息，支持 HTML'));
    $trackingCode = new Typecho_Widget_Helper_Form_Element_Text('trackingCode', null, null, _t('JS 追踪代码'), _t('在这里填写第三方统计 JS 代码'));

    $form->addInput($customCSS);
    $form->addInput($customJS);
    $form->addInput($footerInfo);
    $form->addInput($trackingCode);

    // === 友链配置 ===
    $friendsData = new Typecho_Widget_Helper_Form_Element_Textarea(
        'friendsData',
        null,
        null,
        _t('友链数据 (JSON格式)'),
        _t('在这里填入友链数据，格式为JSON。每个分类包含多个友链信息。<br><br>示例格式：<br><pre>{
 "技术": [
   {
     "name": "网站名称",
     "url": "https://example.com",
     "avatar": "https://example.com/avatar.jpg",
     "description": "网站描述",
     "tags": [
       {"name": "技术", "color": "#d99f00"},
       {"name": "博客", "color": "#007cba"}
     ]
   }
 ],
 "推荐": [
   {
     "name": "推荐网站",
     "url": "https://recommend.com",
     "avatar": "https://recommend.com/avatar.jpg",
     "description": "推荐网站描述",
     "tags": [
       {"name": "推荐", "color": "#46b450"}
     ]
   }
 ]
}</pre>')
    );
    $form->addInput($friendsData);
}

	

function themeFields($layout) {
	$isLatex = new Typecho_Widget_Helper_Form_Element_Radio('isLatex', 
	array(1 => _t('启用'),
	0 => _t('关闭')),
	0, _t('LaTeX 渲染'), _t('默认关闭增加网页访问速度，如文章内存在LaTeX语法则需要启用'));

    // 设置文章简介
    $excerpt = new Typecho_Widget_Helper_Form_Element_Textarea('excerpt', null, null, _t('文章简介'), _t('在这里填写文章的简介，将在文章列表中显示，为空则默认摘录正文前200个字符'));

    // 设置头图URL
    $thumbnail = new Typecho_Widget_Helper_Form_Element_Text('thumbnail', null, null, _t('文章头图'), _t('在这里填写文章的头图URL地址'));

    // 是否展示头图（不展示，首页展示，文章页展示，都展示）
    $showThumbnail = new Typecho_Widget_Helper_Form_Element_Radio('showThumbnail',
        array(0 => _t('不展示'),
              3 => _t('都展示'),
              1 => _t('首页展示'),
              2 => _t('文章页展示')),
        3, _t('展示头图'), _t('是否在文章列表中展示头图'));

    // 头图展示布局（单独文章设置，可覆盖全局设置）
    $thumbnailLayout = new Typecho_Widget_Helper_Form_Element_Radio('thumbnailLayout',
        array('default' => _t('跟随全局设置'),
              'top' => _t('顶部展示'),
              'side' => _t('左右布局')),
        'default', _t('头图布局方式'), _t('选择此文章的头图展示布局，留空则跟随全局设置'));

    // 是否开启过期保护
    $enableExpiryProtection = new Typecho_Widget_Helper_Form_Element_Radio('enableExpiryProtection',
        array(1 => _t('是'),
              0 => _t('否')),
        0, _t('开启过期保护'), _t('是否开启文章过期保护'));

    // 过期时长
    $expiryDuration = new Typecho_Widget_Helper_Form_Element_Text('expiryDuration', null, 180, _t('过期时长'), _t('在这里填写文章的过期时长，例如：30，单位是天'));

	$layout->addItem($isLatex);
	   $layout->addItem($excerpt);
	   $layout->addItem($showThumbnail);
	   $layout->addItem($thumbnail);
	   $layout->addItem($thumbnailLayout);
	   $layout->addItem($enableExpiryProtection);
	   $layout->addItem($expiryDuration);
}


/*
function themeFields($layout)
{
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点LOGO地址'),
        _t('在这里填入一个图片URL地址, 以在网站标题前加上一个LOGO')
    );
    $layout->addItem($logoUrl);
}
*/
