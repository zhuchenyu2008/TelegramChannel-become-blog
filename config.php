<?php
/**
 * 配置文件
 */
return [
    // Telegram频道短链接名称（不含 @）
    'channel' => 'biggestseahome',
    // 缓存目录（需可写）
    'cache_dir' => __DIR__ . '/cache',
    // 缓存有效期，单位秒（适当延长，降低抓取频率）
    'cache_ttl' => 1800,
    // 每页显示的文章数量
    'posts_per_page' => 20,
    // 标签过滤时，扫描的最大文章数量
    'tag_filter_max_posts_to_scan' => 100,
    // 抓取最大帖子数量（限制深翻页，避免过慢）
    'max_posts_to_collect' => 200,
    // 过期可用 + 后台刷新（SWR）保护间隔（秒），避免并发重复刷新
    'swr_refresh_min_interval' => 60,
];
