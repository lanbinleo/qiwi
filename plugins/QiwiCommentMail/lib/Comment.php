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
 * Comment
 *
 * @package QiwiCommentMail
 */
class Comment
{
	/**
	 * 文章ID
	 *
	 * @var int
	 */
	public $cid;

	/**
	 * 评论ID
	 *
	 * @var int
	 */
	public $coid;

	/**
	 * 评论创建时间
	 *
	 * @var integer
	 */
	public $created;

	/**
	 * 评论作者
	 *
	 * @var string
	 */
	public $author;

	/**
	 * 作者ID
	 *
	 * @var int
	 */
	public $authorId;

	/**
	 * 不知道什么玩意儿
	 *
	 * @var integer
	 */
	public $ownerId;

	/**
	 * 邮箱
	 *
	 * @var string
	 */
	public $mail;

	/**
	 * ip
	 *
	 * @var string
	 */
	public $ip;

	/**
	 * 文章名称
	 *
	 * @var string
	 */
	public $title;

	/**
	 * 邮件内容
	 *
	 * @var string
	 */
	public $text;

	/**
	 * 评论地址
	 *
	 * @var string
	 */
	public $permalink;

	/**
	 * 状态
	 *
	 * @var string
	 */
	public $status;

	/**
	 * 被评论者
	 *
	 * @var string
	 */
	public $parent;

	/**
	 * 对于访客时 访客的原始文字
	 *
	 * @var string
	 */
	public $originalText;

	/**
	 * 对于访客时 访客的名称
	 *
	 * @var string
	 */
	public $originalAuthor;

	/**
	 * 对于访客时 访客的邮件地址
	 *
	 * @var string
	 */
	public $originalMail;

	/**
	 * 模版中联系我的邮箱
	 *
	 * @var string
	 */
	public $contactme;

	/**
	 * 评论类型。保留该字段仅用于兼容旧任务内容。
	 *
	 * @var string
	 */
	public $type;
}
