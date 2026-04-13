ARG WP_VERSION
ARG PHP_VERSION
ARG DOCKER_REGISTRY

FROM ${DOCKER_REGISTRY:-}wp-graphql:latest-wp${WP_VERSION}-php${PHP_VERSION}

# Move the base image app setup script out of the way
# Put our shell script in place which will invoke the base image script
RUN cp /usr/local/bin/app-setup.sh /usr/local/bin/original-app-setup.sh
COPY docker/app.setup.sh /usr/local/bin/app-setup.sh
