# 使用官方的 PHP Apache 镜像
FROM php:apache

# 安装所需的 PHP 扩展
RUN apt-get update && apt-get install -y libxml2-dev && docker-php-ext-install dom xml

# 将当前目录下的所有文件复制到容器的 /var/www/html 目录
COPY . /var/www/html/

# 创建 cache 目录并确保它对 Apache 用户可写
RUN mkdir -p /var/www/html/cache && chown -R www-data:www-data /var/www/html/cache

# 暴露 80 端口
EXPOSE 80
