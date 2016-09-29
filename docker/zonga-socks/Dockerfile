FROM ubuntu:latest

RUN apt-get update
RUN apt-get install -y nano wget unzip
RUN apt-get install php7.0-cli php7.0-common php7.0-dev -y
RUN apt-get install libevent-dev -y

WORKDIR /tmp
RUN wget https://github.com/expressif/pecl-event-libevent/archive/master.zip
RUN unzip master.zip && rm master.zip
WORKDIR /tmp/pecl-event-libevent-master
RUN phpize && ./configure && make && make install
RUN echo 'extension=libevent.so' > /etc/php/7.0/mods-available/libevent.ini
RUN ln -s /etc/php/7.0/mods-available/libevent.ini /etc/php/7.0/cli/conf.d/20-libevent.ini

RUN apt-get install composer -y

RUN apt-get install supervisor -y
RUN mkdir /logs

COPY supervisor.conf /etc/supervisor/conf.d/supervisor.conf

COPY install.sh /install.sh
RUN chmod +x /install.sh

WORKDIR /zonga

CMD ["/bin/bash", "/install.sh"]
