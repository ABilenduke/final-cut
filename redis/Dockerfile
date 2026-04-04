FROM redis:8.2.5-bookworm

COPY redis.conf /etc/redis/redis.conf
COPY users.acl /etc/redis/users.acl
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /tls /etc/redis/tls

EXPOSE 6379

ENTRYPOINT ["docker-entrypoint.sh"]
