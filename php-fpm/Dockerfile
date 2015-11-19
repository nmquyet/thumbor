FROM debian:jessie

MAINTAINER Nguyen Minh Quyet <minhquyet@gmail.com>

RUN apt-get update && apt-get install -y php5-common php5-cli php5-fpm php5-mcrypt php5-apcu php5-curl php5-intl

ADD www.conf /etc/php5/fpm/pool.d/
ADD upload.ini /etc/php5/fpm/conf.d/

RUN usermod -u 1000 www-data

CMD ["php5-fpm", "-F"]

EXPOSE 9000
