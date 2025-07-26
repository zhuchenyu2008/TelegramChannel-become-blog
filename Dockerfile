FROM php:8.2-apache

# 安装所需的扩展
RUN docker-php-ext-install dom

# 将应用程序文件复制到容器中
COPY . /var/www/html/

# 设置 cache 目录的权限
RUN chown -R www-data:www-data /var/www/html/cache

# 暴露端口 80
EXPOSE 80
