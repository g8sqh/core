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

# ENV PKG_CONFIG_PATH="/usr/local/lib/pkgconfig:${PKG_CONFIG_PATH}"
# # Install vips from source because the edge package does not have libgsf support.
# # I've ommitted libexif on purpose because the EXIF orientation of images captured by
# # an AUV is not reliable. Without libexif, vipsthumbnail ignores the EXIF orientation and
# # the thumbnail orientation is correct again.
# # Install libvips and the vips PHP extension in one go so the *-dev dependencies are
# # reused.
# ARG LIBVIPS_VERSION=8.8.1
# ARG PHP_VIPS_EXT_VERSION=1.0.7
# RUN apk add --no-cache --virtual .build-deps \
#         autoconf \
#         automake \
#         build-base \
#         glib-dev \
#         tiff-dev \
#         libjpeg-turbo-dev \
#         libgsf-dev \
#         libpng-dev \
#         expat-dev \
#     && apk add --no-cache \
#         glib \
#         tiff \
#         libjpeg-turbo \
#         libgsf \
#         libpng \
#         expat \
#     && cd /tmp \
#     && curl -L https://github.com/libvips/libvips/releases/download/v${LIBVIPS_VERSION}/vips-${LIBVIPS_VERSION}.tar.gz -o vips-${LIBVIPS_VERSION}.tar.gz \
#     && tar -xzf vips-${LIBVIPS_VERSION}.tar.gz \
#     && cd vips-${LIBVIPS_VERSION} \
#     && ./configure \
#         --without-python \
#         --enable-debug=no \
#         --disable-dependency-tracking \
#         --disable-static \
#     && make -j $(nproc) \
#     && make -s install-strip \
#     && cd /tmp \
#     && curl -L https://github.com/libvips/php-vips-ext/releases/download/v${PHP_VIPS_EXT_VERSION}/vips-${PHP_VIPS_EXT_VERSION}.tgz -o  vips-${PHP_VIPS_EXT_VERSION}.tgz \
#     && echo '' | pecl install vips-${PHP_VIPS_EXT_VERSION}.tgz \
#     && docker-php-ext-enable vips \
#     && rm -r /tmp/* \
#     && apk del --purge .build-deps \
#     && rm -rf /var/cache/apk/*

# # Set this library path to the Python modules are linked correctly.
# # See: https://github.com/python-pillow/Pillow/issues/1763#issuecomment-204252397
# ENV LIBRARY_PATH=/lib:/usr/lib
# # Install Python dependencies. Note that these also depend on some image processing libs
# # that were installed along with vips.
# RUN apk add --no-cache \
#         python \
#         freetype \
#         lapack \
#         libstdc++ \
#         openblas \
#     && apk add --no-cache --virtual .build-deps \
#         build-base \
#         python-dev \
#         py-pip \
#         freetype-dev \
#         lapack-dev \
#         musl-dev \
#         gfortran \
#         libjpeg-turbo-dev \
#         libpng-dev \
#         zlib-dev \
#         openblas-dev \
#     && pip install --no-cache-dir --upgrade pip \
#     && BLAS=/usr/lib/openblas/lib/libopenblas.so pip install --no-cache-dir \
#         numpy==1.8.2 \
#         scipy==0.13.3 \
#         scikit-learn==0.14.1 \
#         Pillow==6.2.0 \
#         PyExcelerate==0.6.7 \
#     # Matplotlib requires numpy but tries to install another version if it is installed
#     # at the same time than numpy, so it is installed in a second step.
#     && pip install --no-cache-dir \
#         matplotlib==1.5.3 \
#     && apk del --purge .build-deps \
#     && rm -rf /var/cache/apk/*

# # Just copy from intermediate biigle/app so the installation of dependencies with
# # Composer doesn't have to run twice.
# COPY --from=intermediate /var/www /var/www

# WORKDIR /var/www

# # This is required to run php artisan tinker in the worker container. Do this for
# # debugging purposes.
# RUN mkdir -p /.config/psysh && chmod o+w /.config/psysh

# ARG BIIGLE_VERSION
# ENV BIIGLE_VERSION=${BIIGLE_VERSION}
