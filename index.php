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
$all_posts = $data['messages']; // Renamed to avoid confusion
$description = $data['description'];
$channelName = $config['channel'];

// --- Pagination Logic ---
$posts_per_page = $config['posts_per_page'] ?? 20; // Read from config or default to 20
$total_posts = count($all_posts);
$total_pages = ceil($total_posts / $posts_per_page);
if ($total_pages == 0) $total_pages = 1; // Ensure at least 1 page even if no posts

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $posts_per_page;
$posts_on_page = array_slice($all_posts, $offset, $posts_per_page);
// --- End Pagination Logic ---

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
    <?php if (empty($posts_on_page)): ?>
        <p class="no-posts">No posts to display on this page.</p>
    <?php else: ?>
        <?php foreach ($posts_on_page as $post): ?>
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
                <?php if (!empty($post['imgs'])): ?>
                    <div class="image-gallery">
                        <?php foreach ($post['imgs'] as $idx => $img): ?>
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="adaptive-img" data-idx="<?php echo $idx; ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
            <div class="tags">
                <?php foreach ($tags[1] as $tag): ?>
                    <a href="?tag=<?php echo urlencode($tag); ?>" class="tag">#<?php echo htmlspecialchars($tag); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination Controls -->
<div class="pagination">
    <?php if ($total_pages > 1): ?>
        <?php // Previous Page Link ?>
        <?php if ($current_page > 1): ?>
            <a href="?page=<?php echo $current_page - 1; ?>">« Previous</a>
        <?php else: ?>
            <span class="disabled">« Previous</span>
        <?php endif; ?>

        <?php // Page Number Links
        $show_ellipsis_start = false;
        $show_ellipsis_end = false;
        $visible_pages_around_current = 2; // How many pages to show before and after current

        if ($total_pages <= (1 + ($visible_pages_around_current * 2) + 2 + 2)) { // First + AroundCurrent*2 + Current + Ellipses*2
            // Show all pages if total is small enough
            $start_page = 1;
            $end_page = $total_pages;
        } else {
            // Determine if ellipses are needed
            if ($current_page > $visible_pages_around_current + 2) {
                $show_ellipsis_start = true;
            }
            if ($current_page < $total_pages - ($visible_pages_around_current + 1)) {
                $show_ellipsis_end = true;
            }

            if ($show_ellipsis_start && $show_ellipsis_end) {
                $start_page = $current_page - $visible_pages_around_current;
                $end_page = $current_page + $visible_pages_around_current;
            } elseif ($show_ellipsis_start) { // Ellipsis at start only, current page is near the end
                $start_page = $total_pages - (2 * $visible_pages_around_current) -1; // Show last few pages
                $end_page = $total_pages;
            } elseif ($show_ellipsis_end) { // Ellipsis at end only, current page is near the start
                $start_page = 1;
                $end_page = 1 + (2 * $visible_pages_around_current) +1; // Show first few pages
            } else { // Should not happen with the main if, but as fallback
                $start_page = 1;
                $end_page = $total_pages;
            }
        }

        // Always show page 1
        if ($start_page > 1): ?>
            <a href="?page=1">1</a>
            <?php if ($show_ellipsis_start && $start_page > 2): ?>
                <span class="ellipsis">...</span>
            <?php endif; ?>
        <?php endif;

        for ($i = max(1, $start_page); $i <= min($total_pages, $end_page); $i++): ?>
            <?php if ($i == $current_page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor;

        // Always show last page
        if ($end_page < $total_pages): ?>
            <?php if ($show_ellipsis_end && $end_page < $total_pages -1 ): ?>
                <span class="ellipsis">...</span>
            <?php endif; ?>
            <a href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
        <?php endif; ?>

        <?php // Next Page Link ?>
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?php echo $current_page + 1; ?>">Next »</a>
        <?php else: ?>
            <span class="disabled">Next »</span>
        <?php endif; ?>
    <?php endif; ?>
</div>
<!-- End Pagination Controls -->

<!-- 灯箱大图组件 -->
<div id="lightbox" class="lightbox" style="display:none;">
    <div class="lightbox-backdrop"></div>
    <div class="lightbox-content">
        <img src="" alt="" id="lightbox-img">
        <button class="lightbox-close">&times;</button>
        <button class="lightbox-prev">&#8592;</button>
        <button class="lightbox-next">&#8594;</button>
        <div class="lightbox-index"></div>
    </div>
</div>


<script src="assets/script.js"></script>
</body>
</html>

