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
            'ShowCategory'       => _t('显示分类'),
        ],
        ['ShowRecentPosts', 'ShowCategory'],
        _t('侧边栏显示')
    );

    $form->addInput($sidebarBlock->multiMode());

    // 一言打字机效果
    $enableHitokoto = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableHitokoto',
        array(1 => _t('启用'),
              0 => _t('关闭')),
        1,
        _t('一言打字机效果'),
        _t('在侧边栏个人简介处启用一言打字机效果，默认启用')
    );
    $form->addInput($enableHitokoto);

    // 开往功能
    $enableTravellings = new Typecho_Widget_Helper_Form_Element_Radio(
        'enableTravellings',
        array(1 => _t('启用'),
              0 => _t('关闭')),
        1,
        _t('开往（Travellings）'),
        _t('在顶部导航栏显示"开往"链接，默认启用')
    );
    $form->addInput($enableTravellings);

    // 关于页面信息
    $aboutBio = new Typecho_Widget_Helper_Form_Element_Text('aboutBio', null, null, _t('关于页面 - 简介'), _t('在这里填写你的简介，将显示在关于页面的个人信息卡片中'));
    $aboutAvatar = new Typecho_Widget_Helper_Form_Element_Text('aboutAvatar', null, null, _t('关于页面 - 头像'), _t('在这里填写你的头像URL地址，将显示在关于页面的个人信息卡片中，留空则显示默认头像'));

    $form->addInput($aboutBio);
    $form->addInput($aboutAvatar);

    // 自定义CSS / JS / 页脚信息 / JS追踪代码
    $customCSS = new Typecho_Widget_Helper_Form_Element_Textarea('customCSS', null, null, _t('自定义 CSS'), _t('在这里填写自定义 CSS 代码'));
    $customJS = new Typecho_Widget_Helper_Form_Element_Textarea('customJS', null, null, _t('自定义 JS'), _t('在这里填写自定义 JS代码'));
    $trackingCode = new Typecho_Widget_Helper_Form_Element_Text('trackingCode', null, null, _t('JS 追踪代码'), _t('在这里填写第三方统计 JS 代码'));
    $footerInfo = new Typecho_Widget_Helper_Form_Element_Text('footerInfo', null, null, _t('页脚信息'), _t('在这里填写页脚信息，支持 HTML'));

    $form->addInput($customCSS);
    $form->addInput($customJS);
    $form->addInput($trackingCode);
    $form->addInput($footerInfo);

    // === 友链配置 ===
    $friendsData = new Typecho_Widget_Helper_Form_Element_Textarea(
        'friendsData',
        null,
        null,
        _t('友链数据 (JSON格式)'),
        _t('在这里填入友链数据，格式为JSON。')
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

    // 是否置顶文章
    $isSticky = new Typecho_Widget_Helper_Form_Element_Radio('isSticky',
        array(1 => _t('是'),
              0 => _t('否')),
        0, _t('置顶文章'), _t('置顶的文章将在首页优先显示'));

    $layout->addItem($isLatex);
    $layout->addItem($excerpt);
    $layout->addItem($showThumbnail);
    $layout->addItem($thumbnail);
    $layout->addItem($isSticky);
}
