# TelegramChannel-become-blog

将telegram频道转为一个简单的博客

这是一个基于 PHP8.3 开发的项目，用于将 Telegram 频道内容转化为微博客样式进行展示。

支持根据标签进行分类、显示最新消息、响应式设计以及访问计数等功能。

由ChatGPT提供必要帮助制作完成

---

## 功能概览
- 自动抓取 Telegram 频道公开消息
- 缓存机制减少请求频率，支持自定义刷新时间
- 自动提取并展示文本、图片、发布时间和阅读数
- 自动识别并高亮文中的 #标签，可筛选查看
- 响应式前端页面，支持移动端浏览
- 在页面右上角显示“今日访问人数”

---

## 项目结构

```
/project-root
    ├── assets/
    │   ├── style.css        # 页面样式文件
    │   └── script.js        # 页面脚本文件
    ├── cache/               # 缓存文件夹，用于存储抓取的消息
    ├── config.php           # 配置文件，包含频道信息、缓存时间等
    ├── fetcher.php          # 抓取 Telegram 频道内容的核心逻辑
    ├── index.php            # 主入口，展示频道内容
    └── README.md            # 项目说明文档
```

---

## 使用方法

1. 克隆本项目到本地：
   ```bash
   git clone https://github.com/your-username/telegram-blog.git
   cd telegram-blog
   ```

2. 修改 `config.php` 配置文件：
   在 `config.php` 中，你可以设置以下配置：
   - `channel`: 设置你要抓取的 Telegram 频道名称。
   - `limit`: 设置每次抓取的最大消息数。
   - `cache_dir`: 设置缓存文件的存放目录。
   - `cache_ttl`: 设置缓存有效期（单位：秒）。

3. 上传到你的 PHP 服务器：
   将整个项目文件上传到你的网站根目录，确保你的 PHP 环境已开启 `allow_url_fopen`。

4. 访问项目：
   在浏览器中打开你的站点，查看抓取的 Telegram 频道内容。

---


## 功能介绍

### 1. 显示频道简介

在页面顶部显示你抓取的 Telegram 频道的简介、频道名称和头像。

### 2. 消息显示和分类

- 最新消息在顶部展示。
- 支持根据 `#标签` 分类查看消息。
- 点击标签进入分类页面，并且支持返回主界面。

### 3. 图片展示

- 图片将等比例缩放，确保图片显示完整。

### 4. 访问人数统计

在页面右上角显示“Number of visitors: ()”，实时显示访问该网站的人数。

---

## 常见问题

### 1. 图片不显示

- 请确保你的服务器能够访问 Telegram 的资源。
  
### 2. 如何修改频道？

- 修改 `config.php` 文件中的 `channel` 配置项，替换为你想抓取的 Telegram 频道。

### 3. 如何修改缓存时间？

- 在 `config.php` 中修改 `cache_ttl` 配置项来设置缓存时间。

---



