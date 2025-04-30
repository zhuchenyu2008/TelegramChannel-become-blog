<?php

function add_external_link_class($html) {
    return preg_replace_callback(
        '/<a\s+([^>]*href=[\'"]([^\'"]+)[\'"][^>]*)>/i',
        function($matches) {
            $tag = $matches[1];
            $href = $matches[2];
            // 只处理 http/https 链接
            if (preg_match('#^https?://#i', $href)) {
                if (strpos($tag, 'class=') === false) {
                    $tag .= ' class="external-link"';
                } else {
                    $tag = preg_replace('/class=[\'"]([^\'"]*)[\'"]/', 'class="$1 external-link"', $tag);
                }
                if (strpos($tag, 'target=') === false) {
                    $tag .= ' target="_blank"';
                }
                if (strpos($tag, 'rel=') === false) {
                    $tag .= ' rel="noopener noreferrer"';
                }
            }
            return '<a ' . $tag . '>';
        },
        $html
    );
}


$config = require __DIR__ . '/config.php';
require __DIR__ . '/fetcher.php';

$fetcher = new Fetcher($config);
$data = $fetcher->getPosts();
$posts = $data['messages'];
$description = $data['description'];
$channelName = $config['channel'];
// $tagFilter = $_GET['tag'] ?? null; // 不再需要后端筛选

// 简单访客统计（按天）
$today = date('Ymd');
$statFile = __DIR__ . "/cache/visit_{$today}.txt";
$visits = file_exists($statFile) ? (int)file_get_contents($statFile) : 0;
$visits++;
file_put_contents($statFile, $visits);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($channelName); ?> - 博客</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
    <img src="https://t.me/i/userpic/320/<?php echo $channelName; ?>.jpg" alt="头像" class="avatar">
    <div class="channel-info">
        <a href="/" style="text-decoration: none; color: inherit;"><h1>@<?php echo htmlspecialchars($channelName); ?></h1></a>
        <p><?php echo htmlspecialchars($description); ?></p>
    </div>
    <!-- 改为始终输出返回按钮，但初始隐藏 -->
    <a id="backBtn" href="#" class="back-button" style="display:none;">← 返回</a>
    <div class="visit-counter">
        Number of visitors: <strong><?php echo $visits; ?></strong>
    </div>
</div>
<div class="container">
    <?php foreach ($posts as $post): ?>
        <?php
            preg_match_all('/#(\w+)/u', $post['text'], $tags);
            // 不再用后端筛选
        ?>
        <div class="post">
            <div class="header">
                <span class="time"><?php echo date('Y-m-d H:i', strtotime($post['time'])); ?></span>
                <span class="views"><?php echo htmlspecialchars($post['views']); ?> 阅读</span>
            </div>
            <div class="content">
                <div class="tg-text"><?php echo add_external_link_class($post['text']); ?></div>
                <?php if ($post['img']): ?>
                    <img src="<?php echo htmlspecialchars($post['img']); ?>" alt="" class="adaptive-img">
                <?php endif; ?>
            </div>
            <div class="tags">
                <?php foreach ($tags[1] as $tag): ?>
                    <a href="?tag=<?php echo urlencode($tag); ?>" class="tag">#<?php echo htmlspecialchars($tag); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script src="assets/script.js"></script>
</body>
</html>

