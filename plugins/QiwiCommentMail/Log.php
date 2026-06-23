<?php
/**
 * QiwiCommentMail
 * 调试日志工具
 *
 * 基于 CommentToMail 原版调试工具维护。
 */

function get_log($log_msg)
{
    $log_filename = __DIR__ . "/log";
    if (!file_exists($log_filename))
    {
        // create directory/folder uploads.
        mkdir($log_filename, 0755, true);
    }
    $log_file_data = $log_filename . '/log.txt';
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    $log_msg = str_replace(["\r", "\n"], ' ', (string)$log_msg);
    file_put_contents($log_file_data, "QiwiCommentMail: " . $log_msg . "\n", FILE_APPEND | LOCK_EX);
}

// call to function
// get_log("this is my log message");
