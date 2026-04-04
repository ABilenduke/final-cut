FROM postgres:17-bookworm

COPY postgresql.conf /etc/postgresql/postgresql.conf
COPY pg_hba.conf /etc/postgresql/pg_hba.conf
COPY init-test-db.sh /docker-entrypoint-initdb.d/init-test-db.sh
COPY docker-entrypoint.sh /usr/local/bin/pg-entrypoint.sh

RUN chmod +x /docker-entrypoint-initdb.d/init-test-db.sh \
    && chmod +x /usr/local/bin/pg-entrypoint.sh \
    && mkdir -p /tls /etc/postgresql/tls

EXPOSE 5432

ENTRYPOINT ["pg-entrypoint.sh"]
