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

    $form->addInput($sidebarBlock->multiMode());
}

	

function themeFields($layout) {
	$isLatex = new Typecho_Widget_Helper_Form_Element_Radio('isLatex', 
	array(1 => _t('启用'),
	0 => _t('关闭')),
	0, _t('LaTeX 渲染'), _t('默认关闭增加网页访问速度，如文章内存在LaTeX语法则需要启用'));

    // 设置文章简介
    $excerpt = new Typecho_Widget_Helper_Form_Element_Textarea('excerpt', null, null, _t('文章简介'), _t('在这里填写文章的简介，将在文章列表中显示'));
    
	$layout->addItem($isLatex);
    $layout->addItem($excerpt);
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
