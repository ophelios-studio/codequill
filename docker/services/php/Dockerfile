FROM ghcr.io/ophelios-studio/docker-pulsar-php84:latest

# Composer (requires the GitHub Access Token)
ARG GITHUB_ACCESS_TOKEN
ENV GITHUB_ACCESS_TOKEN $GITHUB_ACCESS_TOKEN
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer config -g github-oauth.github.com ${GITHUB_ACCESS_TOKEN}
