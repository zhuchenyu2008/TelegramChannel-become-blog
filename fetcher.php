<?php
/**
 * 抓取并解析Telegram频道内容，保留原始HTML格式
 */
class Fetcher {
    protected $channel;
    protected $cacheDir;
    protected $cacheTtl;
    protected $maxPosts;
    protected $swrMinInterval;

    public function __construct(array $config) {
        $this->channel  = $config['channel'];
        $this->cacheDir = $config['cache_dir'];
        $this->cacheTtl = $config['cache_ttl'];
        $this->maxPosts = $config['max_posts_to_collect'] ?? 200;
        $this->swrMinInterval = $config['swr_refresh_min_interval'] ?? 60;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function getPosts() {
        $cacheFile = "$this->cacheDir/{$this->channel}.json";
        // 命中未过期缓存直接返回
        if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $this->cacheTtl) {
            return json_decode(@file_get_contents($cacheFile) ?: '[]', true);
        }

        // 过期缓存：SWR，先返回旧缓存，后台刷新
        if (file_exists($cacheFile)) {
            $stale = json_decode(@file_get_contents($cacheFile) ?: '[]', true);
            $this->scheduleBackgroundRefresh($cacheFile);
            return $stale ?: ['description' => '暂无简介', 'messages' => []];
        }

        // 没有缓存，则立即刷新（带超时与重试）
        return $this->refreshNow($cacheFile);
    }

    protected function scheduleBackgroundRefresh(string $cacheFile): void {
        $lock = $this->cacheDir . '/refresh_' . $this->channel . '.lock';
        $now = time();
        $canRefresh = true;
        if (file_exists($lock)) {
            $last = (int)@file_get_contents($lock);
            if ($last && ($now - $last) < $this->swrMinInterval) {
                $canRefresh = false;
            }
        }
        if (!$canRefresh) return;
        @file_put_contents($lock, (string)$now, LOCK_EX);

        register_shutdown_function(function() use ($cacheFile, $lock) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                $this->refreshNow($cacheFile);
            } finally {
                @file_put_contents($lock, (string)time(), LOCK_EX);
            }
        });
    }

    protected function httpGet(string $url, int $timeout = 8) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => "User-Agent: Mozilla/5.0 (Fetcher; +https://note.biggestsea.top)\r\nAccept: text/html,application/xhtml+xml\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        return @file_get_contents($url, false, $ctx);
    }

    protected function httpGetWithRetry(string $url, int $retries = 2, int $timeout = 8) {
        $attempt = 0;
        $delay = 300; // ms 指数退避
        while (true) {
            $attempt++;
            $res = $this->httpGet($url, $timeout);
            if ($res !== false && $res !== '') return $res;
            if ($attempt > $retries) return false;
            usleep($delay * 1000);
            $delay = min($delay * 2, 1500);
        }
    }

    protected function refreshNow(string $cacheFile) {
        $allPosts = [];
        $processedMessageIds = [];
        $currentUrl = "https://t.me/s/{$this->channel}";
        $description = '暂无简介';
        $maxPagesToFetch = 50; // 安全上限
        $pagesFetched = 0;

        // 首次页面
        $initialHtmlContent = $this->httpGetWithRetry($currentUrl, 2, 8);
        if ($initialHtmlContent === false || empty($initialHtmlContent)) {
            error_log("Fetcher: Failed to fetch initial page for channel {$this->channel} from URL: $currentUrl");
            return ['description' => $description, 'messages' => []];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadHTML($initialHtmlContent)) {
            error_log("Fetcher: Failed to parse HTML for initial page of channel {$this->channel}");
            libxml_clear_errors();
            return ['description' => $description, 'messages' => []];
        }
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $descriptionNode = $xpath->query('//div[contains(@class, "tgme_channel_info_description")]')->item(0);
        if ($descriptionNode) {
            $description = trim($descriptionNode->textContent);
        }


        while ($currentUrl && $pagesFetched < $maxPagesToFetch) {
            $pagesFetched++;
            // 首次使用 initial，之后重试抓取
            $html = ($pagesFetched === 1) ? $initialHtmlContent : $this->httpGetWithRetry($currentUrl, 2, 8);
            
            if ($pagesFetched === 1) {
                unset($initialHtmlContent);
            }

            if ($html === false || empty($html)) {
                error_log("Fetcher: Failed to fetch HTML from $currentUrl for channel {$this->channel} (Page: $pagesFetched)");
                break; // Stop if fetching fails
            }

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            if (!$dom->loadHTML($html)) {
                error_log("Fetcher: Failed to parse HTML from $currentUrl for channel {$this->channel}");
                libxml_clear_errors();
                break; 
            }
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);

            $messageNodes = $xpath->query('//div[contains(@class, "tgme_widget_message_wrap")]');
            
            if ($messageNodes->length === 0) {
                break;
            }

            $newMessagesOnPage = 0;
            $lastProcessedMessageIdOnPage = null;
            $currentPageMessages = [];

            // 1. Collect Page Messages
            foreach ($messageNodes as $node) {
                $messageId = null;
                $messageLinkNode = $xpath->query('.//a[contains(@class, "tgme_widget_message_date")]', $node)->item(0);
                if ($messageLinkNode) {
                    $linkHref = $messageLinkNode->getAttribute('href');
                    if (preg_match('/\/(\d+)$/', $linkHref, $matches)) {
                        $messageId = $matches[1];
                    }
                }
                
                if (!$messageId) {
                    $messageDataPost = $node->getAttribute('data-post');
                    if ($messageDataPost && strpos($messageDataPost, '/') !== false) {
                         list(,$messageId) = explode('/', $messageDataPost);
                    }
                }

                if ($messageId) {
                    $timeNode = $xpath->query('.//time', $node)->item(0);
                    $time = $timeNode ? $timeNode->getAttribute('datetime') : '';
                    
                    $textNode = $xpath->query('.//div[contains(@class, "tgme_widget_message_text")]', $node)->item(0);
                    $text = '';
                    if ($textNode) {
                        $innerHTML = '';
                        foreach ($textNode->childNodes as $child) {
                            $innerHTML .= $textNode->ownerDocument->saveHTML($child);
                        }
                        $text = trim($innerHTML);
                    }
                    
                    $viewNode = $xpath->query('.//span[contains(@class, "tgme_widget_message_views")]', $node)->item(0);
                    $views = $viewNode ? trim($viewNode->textContent) : '0';
                    
                    $imgWraps = $xpath->query('.//a[contains(@class, "tgme_widget_message_photo_wrap")]', $node);
                    $imgs = [];
                    foreach ($imgWraps as $imgWrap) {
                        $style = $imgWrap->getAttribute('style');
                        if (preg_match('/url\(([^)]+)\)/', $style, $m)) {
                            $rawUrl = trim($m[1], "'\"");
                            $img = strpos($rawUrl, '//') === 0 ? 'https:' . $rawUrl : $rawUrl;
                            $imgs[] = $img;
                        }
                    }
                    $currentPageMessages[] = [
                        'id' => $messageId,
                        'time' => $time,
                        'text' => $text,
                        'views'=> $views,
                        'imgs' => $imgs,
                    ];
                }
            }

            // 2. Sort Page Messages (descending by ID, newer first)
            if (!empty($currentPageMessages)) {
                usort($currentPageMessages, function($a, $b) {
                    return $b['id'] <=> $a['id']; // PHP 7+ spaceship operator
                });
            }
            
            // 3. Process Sorted Page Messages
            foreach ($currentPageMessages as $messageData) {
                if (!in_array($messageData['id'], $processedMessageIds)) {
                    $processedMessageIds[] = $messageData['id'];
                    $allPosts[] = $messageData; // Add the full pre-prepared message data
                    $newMessagesOnPage++;
                    $lastProcessedMessageIdOnPage = $messageData['id'];
                    if (count($allPosts) >= $this->maxPosts) {
                        $currentUrl = null;
                        break;
                    }
                }
            }

            if ($newMessagesOnPage === 0 && !empty($currentPageMessages)) {
                break;
            }
            
            if ($lastProcessedMessageIdOnPage !== null) {
                $currentUrl = "https://t.me/s/{$this->channel}?before={$lastProcessedMessageIdOnPage}";
                usleep(300000); // 300ms 礼貌延迟
            } else {
                $currentUrl = null;
            }
        }

        $data = [
            'description' => $description,
            'messages' => $allPosts
        ];

        @file_put_contents($cacheFile, json_encode($data));
        return $data;
    }
}
