<?php
/**
 * 抓取并解析Telegram频道内容，保留原始HTML格式
 */
class Fetcher {
    protected $channel;
    protected $limit;
    protected $cacheDir;
    protected $cacheTtl;

    public function __construct(array $config) {
        $this->channel  = $config['channel'];
        $this->limit    = $config['limit'];
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

        $url = "https://t.me/s/{$this->channel}";
        $html = file_get_contents($url);
        $dom  = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // 获取频道简介
        $descriptionNode = $xpath->query('//div[contains(@class, "tgme_channel_info_description")]')->item(0);
        $description = $descriptionNode ? trim($descriptionNode->textContent) : '暂无简介';

        $messages = [];
        $items = $xpath->query('//div[contains(@class, "tgme_widget_message_wrap")]');
        foreach ($items as $i => $node) {
            if ($i >= $this->limit) break;
            // 时间
            $timeNode = $xpath->query('.//time', $node)->item(0);
            $time = $timeNode ? $timeNode->getAttribute('datetime') : '';
            // 文本内容，保留HTML结构
            $textNode = $xpath->query('.//div[contains(@class, "tgme_widget_message_text")]', $node)->item(0);
            $text = '';
            if ($textNode) {
                $innerHTML = '';
                foreach ($textNode->childNodes as $child) {
                    $innerHTML .= $textNode->ownerDocument->saveHTML($child);
                }
                $text = trim($innerHTML);
            }
            // 查看人数
            $viewNode = $xpath->query('.//span[contains(@class, "tgme_widget_message_views")]', $node)->item(0);
            $views = $viewNode ? trim($viewNode->textContent) : '0';
            // 图片
            $imgWrap = $xpath->query('.//a[contains(@class, "tgme_widget_message_photo_wrap")]', $node)->item(0);
            $img = '';
            if ($imgWrap) {
                $style = $imgWrap->getAttribute('style');
                if (preg_match('/url\(([^)]+)\)/', $style, $m)) {
                    $rawUrl = trim($m[1], "'\"");
                    $img = strpos($rawUrl, '//') === 0 ? 'https:' . $rawUrl : $rawUrl;
                }
            }

            $messages[] = [
                'time' => $time,
                'text' => $text,
                'views'=> $views,
                'img'  => $img,
            ];
        }

        $data = [
            'description' => $description,
            'messages' => array_reverse($messages)
        ];

        file_put_contents($cacheFile, json_encode($data));
        return $data;
    }
}
