<?php
/**
 * 配置文件
 */
return [
    // Telegram频道短链接名称（不含 @）
    'channel' => 'biggestseahome',
    // 抓取消息数量
    'limit'   => 20,
    // 缓存目录（需可写）
    'cache_dir' => __DIR__ . '/cache',
    // 缓存有效期，单位秒
    'cache_ttl' => 300,
];