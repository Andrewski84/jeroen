<?php


// Password Security
// Hashed version of admin password. Use admin panel to change.
define('ADMIN_PASSWORD_HASH', '$2y$10$mmW7cP.n7F8dlff1Z.cQKebXFLFVXUywYpsaLbG0OheA53bx4jrAm');

// Directory Paths
define('BASE_DIR', __DIR__);
define('ASSETS_DIR', BASE_DIR . '/assets');
define('DATA_DIR', BASE_DIR . '/data');
define('TEMPLATES_DIR', BASE_DIR . '/templates');

// File Paths for the application
define('CONTENT_FILE', DATA_DIR . '/content.json');
define('MESSAGES_FILE', DATA_DIR . '/messages.json');
define('TEAM_FILE', DATA_DIR . '/team/team.json');
define('PRACTICE_FILE', DATA_DIR . '/practice/practice.json');
define('LINKS_FILE', DATA_DIR . '/links/links.json');

// Email / SMTP (Cloud86 mailbox)
if (!defined('SMTP_ENABLED')) { define('SMTP_ENABLED', false); } // set to true when ready
if (!defined('SMTP_HOST')) { define('SMTP_HOST', 'mail.javanstudio.be'); }
if (!defined('SMTP_PORT')) { define('SMTP_PORT', 465); }
if (!defined('SMTP_SECURE')) { define('SMTP_SECURE', 'ssl'); } // 'tls' for 587, 'ssl' for 465
if (!defined('SMTP_USERNAME')) { define('SMTP_USERNAME', 'support@javanstudio.be'); }
if (!defined('SMTP_PASSWORD')) { define('SMTP_PASSWORD', 'GDNino1209!'); }
if (!defined('MAIL_FROM')) { define('MAIL_FROM', SMTP_USERNAME); }
if (!defined('MAIL_FROM_NAME')) { define('MAIL_FROM_NAME', 'Andrew Smeets Fotografie'); }
if (!defined('MAIL_TO')) { define('MAIL_TO', SMTP_USERNAME); }

// Mail log file
if (!defined('MAIL_LOG_FILE')) { define('MAIL_LOG_FILE', DATA_DIR . '/mail_log.json'); }
