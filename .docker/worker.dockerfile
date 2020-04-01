FROM docker.pkg.github.com/biigle/core/app as intermediate

FROM php:7.4-cli
MAINTAINER Martin Zurowietz <martin@cebitec.uni-bielefeld.de>

RUN apt-get update \
    && apt-get -y install --no-install-recommends \
        openssl \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        json \
        zip \
        fileinfo \
        exif \
        soap \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

ARG PHPREDIS_VERSION=5.0.0
RUN curl -L -o /tmp/redis.tar.gz https://github.com/phpredis/phpredis/archive/${PHPREDIS_VERSION}.tar.gz \
    && tar -xzf /tmp/redis.tar.gz \
    && rm /tmp/redis.tar.gz \
    && mkdir -p /usr/src/php/ext \
    && mv phpredis-${PHPREDIS_VERSION} /usr/src/php/ext/redis \
    && docker-php-ext-install redis

ENV PKG_CONFIG_PATH="/usr/local/lib/pkgconfig:${PKG_CONFIG_PATH}"
# Install vips from source to get a newer version with the right configuration.
# I've ommitted libexif on purpose because the EXIF orientation of images captured by
# an AUV is not reliable. Without libexif, vipsthumbnail ignores the EXIF orientation and
# the thumbnail orientation is correct again.
# Install libvips and the vips PHP extension in one go so the *-dev dependencies are
# reused.
ARG LIBVIPS_VERSION=8.8.1
ARG PHP_VIPS_EXT_VERSION=1.0.7
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential \
        pkg-config \
        glib2.0-dev \
        libexpat1-dev \
        libtiff5-dev \
        libjpeg62-turbo-dev \
        libgsf-1-dev \
        libpng-dev \
    && apt-get install -y --no-install-recommends \
        glib2.0 \
        libexpat1 \
        libtiff5 \
        libjpeg62-turbo \
        libgsf-1-114 \
        libpng16-16 \
    && cd /tmp \
    && curl -L https://github.com/libvips/libvips/releases/download/v${LIBVIPS_VERSION}/vips-${LIBVIPS_VERSION}.tar.gz -o vips-${LIBVIPS_VERSION}.tar.gz \
    && tar -xzf vips-${LIBVIPS_VERSION}.tar.gz \
    && cd vips-${LIBVIPS_VERSION} \
    && ./configure \
        --without-python \
        --enable-debug=no \
        --disable-dependency-tracking \
        --disable-static \
    && make -j $(nproc) \
    && make -s install-strip \
    && cd /tmp \
    && curl -L https://github.com/libvips/php-vips-ext/releases/download/v${PHP_VIPS_EXT_VERSION}/vips-${PHP_VIPS_EXT_VERSION}.tgz -o  vips-${PHP_VIPS_EXT_VERSION}.tgz \
    && echo '' | pecl install vips-${PHP_VIPS_EXT_VERSION}.tgz \
    && docker-php-ext-enable vips \
    && rm -r /tmp/* \
    && apt-get purge -y \
        build-essential \
        pkg-config \
        glib2.0-dev \
        libexpat1-dev \
        libtiff5-dev \
        libjpeg-turbo8-dev \
        libgsf-1-dev \
        libpng-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        python \
        python-pip \
        python-setuptools \
        python-dev \
    && pip install --no-cache-dir \
        numpy==1.8.2 \
        scipy==0.13.3 \
        scikit-learn==0.14.1 \
        Pillow==6.2.0 \
        PyExcelerate==0.6.7 \
    # Matplotlib requires numpy but tries to install another version if it is installed
    # at the same time than numpy, so it is installed in a second step.
    && pip install --no-cache-dir \
        matplotlib==1.5.3 \
    && apt-get purge -y \
        python-pip \
        python-setuptools \
        python-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Just copy from intermediate biigle/app so the installation of dependencies with
# Composer doesn't have to run twice.
COPY --from=intermediate /var/www /var/www

WORKDIR /var/www

# This is required to run php artisan tinker in the worker container. Do this for
# debugging purposes.
RUN mkdir -p /.config/psysh && chmod o+w /.config/psysh

ARG BIIGLE_VERSION
ENV BIIGLE_VERSION=${BIIGLE_VERSION}
