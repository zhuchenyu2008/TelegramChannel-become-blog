<?php
/**
 * 配置文件
 */
return [
    // Telegram频道短链接名称（不含 @）
    'channel' => 'biggestseahome',
    // 缓存目录（需可写）
    'cache_dir' => __DIR__ . '/cache',
    // 缓存有效期，单位秒
    'cache_ttl' => 300,
    // 每页显示的文章数量
    'posts_per_page' => 20,
    // 标签过滤时，扫描的最大文章数量
    'tag_filter_max_posts_to_scan' => 400,
];