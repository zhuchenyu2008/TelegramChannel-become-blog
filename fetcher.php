<?php
/**
 * 抓取并解析Telegram频道内容，保留原始HTML格式
 */
class Fetcher {
    protected $channel;
    protected $cacheDir;
    protected $cacheTtl;

    public function __construct(array $config) {
        $this->channel  = $config['channel'];
        $this->cacheDir = $config['cache_dir'];
        $this->cacheTtl = $config['cache_ttl'];

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function getPosts() {
        $cacheFile = "$this->cacheDir/{$this->channel}.json";
        if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $this->cacheTtl) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        $allPosts = [];
        $processedMessageIds = [];
        $currentUrl = "https://t.me/s/{$this->channel}";
        $description = '暂无简介'; // Default description
        $maxPagesToFetch = 100; // Safety break for the loop
        $pagesFetched = 0;

        // Fetch description from the first page
        $initialHtmlContent = file_get_contents($currentUrl);
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
            // Use $initialHtmlContent for the first iteration, then fetch subsequent pages
            $html = ($pagesFetched === 1) ? $initialHtmlContent : file_get_contents($currentUrl);
            
            // Clear $initialHtmlContent after its first use to ensure fresh fetches for subsequent pages
            if ($pagesFetched === 1) {
                unset($initialHtmlContent);
            }

            if ($html === false || empty($html)) {
                error_log("Fetcher: Failed to fetch HTML from $currentUrl for channel {$this->channel} (Page: $pagesFetched)");
                break; // Stop if fetching fails
            }

            $dom = new DOMDocument();
            // Use internal errors to avoid outputting HTML parsing errors
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
                // No more messages found on this page
                break;
            }

            $newMessagesOnPage = 0;
            $lastProcessedMessageIdOnPage = null;

            foreach ($messageNodes as $node) {
                $messageLinkNode = $xpath->query('.//a[contains(@class, "tgme_widget_message_date")]', $node)->item(0);
                $messageId = null;
                if ($messageLinkNode) {
                    $linkHref = $messageLinkNode->getAttribute('href');
                    if (preg_match('/\/(\d+)$/', $linkHref, $matches)) {
                        $messageId = $matches[1];
                    }
                }
                
                // Fallback or alternative: Try to get data-post attribute from the message div itself
                if (!$messageId) {
                    $messageDataPost = $node->getAttribute('data-post');
                    if ($messageDataPost && strpos($messageDataPost, '/') !== false) {
                         list(,$messageId) = explode('/', $messageDataPost);
                    }
                }


                if ($messageId && !in_array($messageId, $processedMessageIds)) {
                    $processedMessageIds[] = $messageId;
                    $newMessagesOnPage++;

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
                    
                    $allPosts[] = [
                        'id' => $messageId, // Store ID for reference
                        'time' => $time,
                        'text' => $text,
                        'views'=> $views,
                        'imgs' => $imgs,
                    ];
                    $lastProcessedMessageIdOnPage = $messageId;
                } elseif ($messageId && in_array($messageId, $processedMessageIds)) {
                    // This message was already processed, likely from an overlapping page load.
                    // Continue to check other messages on this page.
                }
                 // If no messageId, it's an issue with parsing that specific message, skip it.
            }

            if ($newMessagesOnPage === 0 && $messageNodes->length > 0) {
                // Fetched a page, but all messages on it were already processed.
                // This means we've reached the end of unique new content.
                break;
            }
            
            if ($lastProcessedMessageIdOnPage !== null) {
                $currentUrl = "https://t.me/s/{$this->channel}?before={$lastProcessedMessageIdOnPage}";
                sleep(1); // Be polite to the server
            } else {
                // Could not determine the next page URL (e.g., no new messages found, or couldn't extract ID)
                $currentUrl = null;
            }
        }

        $data = [
            'description' => $description,
            'messages' => array_reverse($allPosts) // Reverse to keep chronological order (newest first in t.me/s)
        ];

        file_put_contents($cacheFile, json_encode($data));
        return $data;
    }
}
