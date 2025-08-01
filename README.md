# TelegramChannel-become-blog

将telegram频道转为一个简单的博客

这是一个基于 PHP8.3 开发的项目，用于将 Telegram 频道内容转化为微博客样式进行展示。

由ChatGPT|Gemini提供必要帮助制作

项目会自动抓取指定频道的内容，展示为网页博客形式，支持标签分类、外链预览、图片多图浏览和每日访客统计，界面简洁美观，适合自建频道内容归档或展示。

## Docker 部署

本项目支持使用 Docker 进行快速部署。

### 部署步骤

1.  **克隆项目**

    ```bash
    git clone https://github.com/zhuchenyu2008/TelegramChannel-become-blog.git
    cd TelegramChannel-become-blog
    ```
    自定义配置请看下方自定义配置
2.  **构建并启动 Docker 容器**

    ```bash
    docker-compose up -d --build
    ```

3.  **访问博客**

    在浏览器中打开 `http://localhost:8080` 即可访问您的博客。

### 自定义配置

您可以根据需要修改 `config.php` 文件来自定义您的博客：

*   `channel`: 您的 Telegram 频道名称（不含 `@`）。
*   `cache_ttl`: 缓存过期时间（以秒为单位）。
*   `posts_per_page`: 每页显示的文章数。

修改配置后，您需要重新构建 Docker 镜像才能使更改生效：

```bash
docker-compose up -d --build
```

## 功能特色

- **自动抓取 Telegram 频道内容**（无需Bot，无需API Key）
- **标签筛选**：支持通过标签快速筛选帖子
- **外链预览**：自动为正文中的外部链接生成网页摘要预览
- **图片灯箱**：多图轮播、点击放大、键盘切换
- **自适应界面**：手机/桌面均良好显示
- **每日访客统计**（本地简单实现）

## 目录结构

```
├── index.php         # 主页，主逻辑页面
├── config.php        # 配置文件（频道、缓存等）
├── fetcher.php       # Telegram内容抓取与解析
├── assets/
│   ├── style.css     # 样式文件
│   └── script.js     # 前端交互脚本
└── cache/            # 缓存与统计文件目录（需可写）
```

## 手动安装与部署

1. **环境要求**

   - PHP 7.2 或以上
   - 支持 `file_get_contents` 远程访问
   - 可写权限的 `cache/` 目录

2. **下载源码**

   ```bash
   git clone https://github.com/你的用户名/你仓库名.git
   cd 你仓库名
   ```

3. **配置**

   编辑 `config.php`，填写你的频道名（不带@），可调整抓取数量和缓存设置：

   ```php
   return [
       'channel' => '你的频道短链名',
       'limit'   => 20,
       'cache_dir' => __DIR__ . '/cache',
       'cache_ttl' => 300,
   ];
   ```

4. **创建缓存目录**

   ```bash
   mkdir -p cache
   chmod 777 cache
   ```

5. **部署到PHP环境**

   将项目放置于Web服务器目录（如Apache/Nginx），访问 `index.php` 即可。

## 使用说明

- 首页自动展示最新的频道内容
- 每条内容支持标签（#tag）筛选，点击标签可仅显示相关帖子，点击“返回”可返回全部列表
- 帖子中的外部链接自动生成网页预览（需外部API服务）
- 支持多图轮播与图片灯箱
- 顶部可见频道头像、简介与访客统计

## 常见问题

### 1. 无法抓取频道内容或头像显示异常？

- 请确保服务器可以访问 `https://t.me/s/频道名` 和 `https://t.me/i/userpic/320/频道名.jpg`
- 某些频道如设为私密或被屏蔽，无法直接抓取
- 部分共享主机可能禁用了 `file_get_contents` 的远程功能

### 2. 标签筛选无效？

- 标签筛选为前端实现，请保证正确引入 `assets/script.js` 并无控制台报错

### 3. 预览API打不开/加载慢？

- 网页预览依赖 `https://jsonlink.io/api/extract`，如API不可用可自行替换或关闭外链预览功能

### 4. 如何自定义样式？

- 直接修改 `assets/style.css` 文件



---

## 贡献

欢迎PR与建议

---

**如有问题欢迎提issue或联系作者。**

---
