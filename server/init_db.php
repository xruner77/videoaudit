<?php
/**
 * VideoAudit 数据库初始化脚本
 * 手动运行方式：php server/init_db.php
 * 或者浏览器访问（需带上 key 参数）：http://your-domain.com/init_db.php?key=init2026
 */

declare(strict_types=1);

// 安全密钥（防止未授权访问，生产环境使用时建议修改）
$securityKey = 'init2026';

// 检查是否为 CLI 模式或提供了正确的密钥
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== $securityKey) {
    die('Unauthorized access. Use CLI or provide correct key.');
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/schema.php';
$dbConfig = require __DIR__ . '/db_config.php';

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['dbname'],
        $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "--- 开始初始化数据库 ---\n";

    initSchema($db);
    echo "Check: 表结构初始化完成。\n";

    ensureUploadDirs(__DIR__ . '/uploads');
    echo "Check: 上传目录已创建。\n";

    createAdminUser($db);
    echo "Check: 管理员账号已确认。\n";

    echo "--- 数据库初始化完成 ---\n";

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
