<?php

date_default_timezone_set('Asia/Shanghai');

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function short_text($value, $length) {
    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length, 'UTF-8');
    }
    return substr($value, 0, $length);
}

function post_plain_text($html) {
    return trim(html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function strip_urls_from_text($text) {
    $text = preg_replace('#https?://\S+#i', '', (string)$text);
    $text = preg_replace('#(?:^|\s)(?:www\.)?[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)+(?:/\S*)?#iu', ' ', $text);
    return trim(preg_replace('/\s+/u', ' ', $text));
}

function extract_tags_from_html($html) {
    preg_match_all('/#([\p{L}\p{N}_-]+)/u', (string)$html, $matches);
    $tags = $matches[1] ?? [];
    return array_values(array_unique(array_filter($tags)));
}

function add_external_link_class($html) {
    return preg_replace_callback(
        '/<a\s+([^>]*href=[\'"]([^\'"]+)[\'"][^>]*)>/i',
        function($matches) {
            $tag = $matches[1];
            $href = $matches[2];
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

function build_query(array $updates = []) {
    $params = [];
    foreach (['tag', 'q', 'month'] as $key) {
        if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
            $params[$key] = trim((string)$_GET[$key]);
        }
    }
    foreach ($updates as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    return http_build_query($params);
}

function build_url(array $updates = []) {
    $query = build_query($updates);
    return 'index.php' . ($query === '' ? '' : '?' . $query);
}

$config = require __DIR__ . '/config.php';
require __DIR__ . '/fetcher.php';

$asset_version = max(
    @filemtime(__DIR__ . '/assets/style.css') ?: 0,
    @filemtime(__DIR__ . '/assets/script.js') ?: 0
);

$fetcher = new Fetcher($config);
$data = $fetcher->getPosts();
$all_posts = $data['messages'] ?? [];
$description = $data['description'] ?? '暂无简介';
$display_description = strip_urls_from_text($description);
if ($display_description === '') {
    $display_description = '暂无简介';
}
$channelName = $config['channel'];

$tag_filter = isset($_GET['tag']) ? short_text(trim((string)$_GET['tag']), 60) : '';
$search_query = isset($_GET['q']) ? short_text(trim((string)$_GET['q']), 80) : '';
$month_filter = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', (string)$_GET['month']) ? $_GET['month'] : '';

$tag_counts = [];
$archive_counts = [];
foreach ($all_posts as $post) {
    foreach (extract_tags_from_html($post['text'] ?? '') as $tag) {
        $tag_counts[$tag] = ($tag_counts[$tag] ?? 0) + 1;
    }
    $timestamp = strtotime($post['time'] ?? '');
    if ($timestamp) {
        $month = date('Y-m', $timestamp);
        $archive_counts[$month] = ($archive_counts[$month] ?? 0) + 1;
    }
}
arsort($tag_counts);
krsort($archive_counts);

$posts_for_pagination = $all_posts;
if ($tag_filter !== '') {
    $tag_filter_max_posts_to_scan = $config['tag_filter_max_posts_to_scan'] ?? 400;
    $posts_for_pagination = array_slice($posts_for_pagination, 0, $tag_filter_max_posts_to_scan);
    $posts_for_pagination = array_values(array_filter($posts_for_pagination, function($post) use ($tag_filter) {
        return in_array($tag_filter, extract_tags_from_html($post['text'] ?? ''), true);
    }));
}
if ($month_filter !== '') {
    $posts_for_pagination = array_values(array_filter($posts_for_pagination, function($post) use ($month_filter) {
        $timestamp = strtotime($post['time'] ?? '');
        return $timestamp && date('Y-m', $timestamp) === $month_filter;
    }));
}
if ($search_query !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($search_query, 'UTF-8') : strtolower($search_query);
    $posts_for_pagination = array_values(array_filter($posts_for_pagination, function($post) use ($needle) {
        $text = post_plain_text($post['text'] ?? '');
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        return strpos($haystack, $needle) !== false;
    }));
}

$posts_per_page = $config['posts_per_page'] ?? 20;
$total_posts = count($posts_for_pagination);
$total_pages = max(1, (int)ceil($total_posts / $posts_per_page));
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $posts_per_page;
$posts_on_page = array_slice($posts_for_pagination, $offset, $posts_per_page);

$page_title_parts = ['@' . $channelName];
if ($search_query !== '') $page_title_parts[] = '搜索：' . $search_query;
if ($tag_filter !== '') $page_title_parts[] = '#' . $tag_filter;
if ($month_filter !== '') $page_title_parts[] = $month_filter;
$page_title = implode(' - ', $page_title_parts);

// 简单访客统计保持原功能，仅在界面上做中文化展示。
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
    <title><?php echo e($page_title); ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?php echo e($asset_version); ?>">
    <link rel="dns-prefetch" href="//t.me">
    <link rel="preconnect" href="https://t.me" crossorigin>
    <link rel="dns-prefetch" href="//cdn5.telesco.pe">
    <link rel="preconnect" href="https://cdn5.telesco.pe" crossorigin>
    <link rel="dns-prefetch" href="//jsonlink.io">
    <link rel="preconnect" href="https://jsonlink.io" crossorigin>
    <link rel="dns-prefetch" href="//www.google.com">
    <link rel="preconnect" href="https://www.google.com" crossorigin>
</head>
<body>
<header class="topbar">
    <div class="topbar-main">
        <img src="https://t.me/i/userpic/320/<?php echo e($channelName); ?>.jpg" alt="频道头像" class="avatar" loading="lazy" decoding="async">
        <div class="channel-info">
            <a href="index.php" class="channel-title"><h1>@<?php echo e($channelName); ?></h1></a>
            <p><?php echo e($display_description); ?></p>
        </div>
        <a class="telegram-button" href="https://t.me/<?php echo e($channelName); ?>" target="_blank" rel="noopener noreferrer">打开频道</a>
    </div>
    <div class="topbar-tools">
        <form class="search-form" action="index.php" method="get">
            <?php if ($tag_filter !== ''): ?><input type="hidden" name="tag" value="<?php echo e($tag_filter); ?>"><?php endif; ?>
            <?php if ($month_filter !== ''): ?><input type="hidden" name="month" value="<?php echo e($month_filter); ?>"><?php endif; ?>
            <input type="search" name="q" value="<?php echo e($search_query); ?>" placeholder="搜索频道内容">
            <button type="submit">搜索</button>
        </form>
        <div class="visit-counter">今日访问 <strong><?php echo e($visits); ?></strong></div>
    </div>
</header>

<?php if ($tag_filter !== '' || $search_query !== '' || $month_filter !== ''): ?>
<div class="filter-bar">
    <span>当前筛选</span>
    <?php if ($tag_filter !== ''): ?><a href="<?php echo e(build_url(['tag' => null, 'page' => null])); ?>">#<?php echo e($tag_filter); ?> ×</a><?php endif; ?>
    <?php if ($search_query !== ''): ?><a href="<?php echo e(build_url(['q' => null, 'page' => null])); ?>">关键词：<?php echo e($search_query); ?> ×</a><?php endif; ?>
    <?php if ($month_filter !== ''): ?><a href="<?php echo e(build_url(['month' => null, 'page' => null])); ?>">归档：<?php echo e($month_filter); ?> ×</a><?php endif; ?>
    <a class="clear-filter" href="index.php">清除全部</a>
</div>
<?php endif; ?>

<main class="page-shell">
    <aside class="discover-panel" aria-label="内容导航">
        <section>
            <h2>热门标签</h2>
            <div class="tag-cloud">
                <?php foreach (array_slice($tag_counts, 0, 18, true) as $tag => $count): ?>
                    <a class="<?php echo $tag === $tag_filter ? 'active' : ''; ?>" href="<?php echo e(build_url(['tag' => $tag, 'page' => null])); ?>">#<?php echo e($tag); ?><span><?php echo e($count); ?></span></a>
                <?php endforeach; ?>
                <?php if (empty($tag_counts)): ?><p class="muted">暂无标签</p><?php endif; ?>
            </div>
        </section>
        <details class="archive-section" open>
            <summary>时间归档</summary>
            <div class="archive-list">
                <?php foreach (array_slice($archive_counts, 0, 12, true) as $month => $count): ?>
                    <a class="<?php echo $month === $month_filter ? 'active' : ''; ?>" href="<?php echo e(build_url(['month' => $month, 'page' => null])); ?>"><span><?php echo e($month); ?></span><strong><?php echo e($count); ?></strong></a>
                <?php endforeach; ?>
                <?php if (empty($archive_counts)): ?><p class="muted">暂无归档</p><?php endif; ?>
            </div>
        </details>
    </aside>

    <section class="feed" aria-label="文章列表">
        <div class="feed-summary">
            <span>共 <?php echo e($total_posts); ?> 条内容</span>
            <?php if ($total_pages > 1): ?><span>第 <?php echo e($current_page); ?> / <?php echo e($total_pages); ?> 页</span><?php endif; ?>
        </div>

        <?php if (empty($posts_on_page)): ?>
            <p class="no-posts">没有找到匹配的内容。</p>
        <?php else: ?>
            <?php foreach ($posts_on_page as $post): ?>
                <?php
                    $post_tags = extract_tags_from_html($post['text'] ?? '');
                    $timestamp = strtotime($post['time'] ?? '');
                    $display_time = $timestamp ? date('Y-m-d H:i', $timestamp) : '时间未知';
                    $source_url = 'https://t.me/' . rawurlencode($channelName) . '/' . rawurlencode($post['id'] ?? '');
                    $image_count = count($post['imgs'] ?? []);
                ?>
                <article class="post">
                    <div class="header">
                        <time class="time" datetime="<?php echo e($post['time'] ?? ''); ?>"><?php echo e($display_time); ?></time>
                        <span class="views"><?php echo e($post['views'] ?? '0'); ?> 阅读</span>
                    </div>
                    <div class="content">
                        <div class="tg-text"><?php echo add_external_link_class($post['text'] ?? ''); ?></div>
                        <?php if (!empty($post['imgs'])): ?>
                            <div class="image-gallery" data-count="<?php echo e($image_count); ?>">
                                <?php foreach ($post['imgs'] as $idx => $img): ?>
                                    <img src="<?php echo e($img); ?>" alt="图片 <?php echo e($idx + 1); ?>" class="adaptive-img" data-idx="<?php echo e($idx); ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <footer class="post-footer">
                        <div class="tags">
                            <?php foreach ($post_tags as $tag): ?>
                                <a href="<?php echo e(build_url(['tag' => $tag, 'page' => null])); ?>" class="tag">#<?php echo e($tag); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <a class="source-link" href="<?php echo e($source_url); ?>" target="_blank" rel="noopener noreferrer">查看原文</a>
                    </footer>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="分页">
                <?php $base_query = build_query(['page' => null]); ?>
                <?php $joiner = $base_query === '' ? '' : $base_query . '&'; ?>
                <a class="<?php echo $current_page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo e($joiner); ?>page=1">首页</a>
                <a class="<?php echo $current_page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo e($joiner); ?>page=<?php echo e(max(1, $current_page - 1)); ?>">上一页</a>
                <span class="active"><?php echo e($current_page); ?></span>
                <a class="<?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>" href="?<?php echo e($joiner); ?>page=<?php echo e(min($total_pages, $current_page + 1)); ?>">下一页</a>
                <a class="<?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>" href="?<?php echo e($joiner); ?>page=<?php echo e($total_pages); ?>">末页</a>
            </nav>
        <?php endif; ?>
    </section>
</main>

<div id="lightbox" class="lightbox" style="display:none;" aria-hidden="true">
    <div class="lightbox-backdrop"></div>
    <div class="lightbox-content">
        <img src="" alt="" id="lightbox-img">
        <button class="lightbox-close" aria-label="关闭">&times;</button>
        <button class="lightbox-prev" aria-label="上一张">&#8592;</button>
        <button class="lightbox-next" aria-label="下一张">&#8594;</button>
        <div class="lightbox-index"></div>
    </div>
</div>

<script src="assets/script.js?v=<?php echo e($asset_version); ?>"></script>
</body>
</html>
<?php
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
}
?>
