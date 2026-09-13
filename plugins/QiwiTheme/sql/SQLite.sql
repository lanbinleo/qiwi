CREATE TABLE IF NOT EXISTS "typecho_qiwi_comment_mail_queue" (
  "id" INTEGER NOT NULL PRIMARY KEY,
  "dedupe_key" varchar(128) NOT NULL,
  "coid" int(10) NOT NULL DEFAULT 0,
  "cid" int(10) NOT NULL DEFAULT 0,
  "parent" int(10) NOT NULL DEFAULT 0,
  "recipient_type" varchar(16) NOT NULL DEFAULT "",
  "event" varchar(32) NOT NULL DEFAULT "",
  "recipient_mail" varchar(255) NOT NULL DEFAULT "",
  "recipient_name" varchar(255) NOT NULL DEFAULT "",
  "payload" text NOT NULL,
  "status" varchar(16) NOT NULL DEFAULT "pending",
  "attempts" int(10) NOT NULL DEFAULT 0,
  "last_error" text NULL,
  "next_retry" int(10) NOT NULL DEFAULT 0,
  "locked_until" int(10) NOT NULL DEFAULT 0,
  "sent_at" int(10) NOT NULL DEFAULT 0,
  "created" int(10) NOT NULL DEFAULT 0,
  "updated" int(10) NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX IF NOT EXISTS "typecho_qcm_dedupe_key" ON "typecho_qiwi_comment_mail_queue" ("dedupe_key");
CREATE INDEX IF NOT EXISTS "typecho_qcm_status_next_retry" ON "typecho_qiwi_comment_mail_queue" ("status", "next_retry", "locked_until", "id");
CREATE INDEX IF NOT EXISTS "typecho_qcm_coid" ON "typecho_qiwi_comment_mail_queue" ("coid");
CREATE INDEX IF NOT EXISTS "typecho_qcm_recipient_type" ON "typecho_qiwi_comment_mail_queue" ("recipient_type")
