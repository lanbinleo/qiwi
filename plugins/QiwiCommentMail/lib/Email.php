<?php

namespace TypechoPlugin\QiwiCommentMail\lib;

/**
 * QiwiCommentMail
 * Typecho 异步评论邮件提醒插件。基于 CommentToMail 原版维护，感谢 xcsoft 的原始贡献。
 *
 * @license    GNU General Public License 3.0
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Email
 *
 * @package QiwiCommentMail
 */
class Email
{
    /**
     * 寄件人
     *
     * @var string
     */
    public $from;

    /**
     * 寄件人姓名
     *
     * @var string
     */
    public $fromName;

    /**
     * reply地址
     *
     * @var string
     */
    public $replyTo;

    /**
     * reply姓名
     *
     * @var string
     */
    public $replyToName;

    /**
     * 收件人地址
     *
     * @var string
     */
    public $reciver;

    /**
     * 收件人姓名
     * @var string
     */

    public $reciverName;

    /**
     * 邮件主题
     *
     * @var string
     */
    public $subject;

    /**
     * 邮件内容
     *
     * @var string
     */
    public $altBody;

    /**
     * 邮件内容
     *
     * @var string
     */
    public $msgHtml;


    /**
     * 向博主发邮件的标题
     *
     * @var string
     */
    public $titleForOwner;

    /**
     * 向访客发邮件的标题
     *
     * @var string
     */
    public $titleForGuest;
}
