# 프론트엔드 빌드 (node 이미지에서 수행, composer 설치와 병렬 실행됨)
FROM node:20-slim AS frontend

WORKDIR /app

# npm 라이브러리 설치 (락 파일 먼저 복사해서 캐시 활용)
COPY package.json package-lock.json ./
RUN npm ci

# 소스 복사 후 Vite 빌드
COPY . .
RUN npm run build


# 확장 모듈과 Apache 설정을 담는 베이스 (vendor, 최종 이미지가 공유)
FROM php:8.3-apache AS base

# 한국 시간대 설정
ENV TZ=Asia/Seoul
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 필요한 PHP 확장 모듈 설치 (mbstring 빌드용 libonig-dev 포함)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath \
    && rm -rf /var/lib/apt/lists/*

# mod_rewrite, mod_remoteip 모듈 활성화
RUN a2enmod rewrite remoteip

# DocumentRoot를 Laravel public 폴더로 변경
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf


# composer 의존성 설치
FROM base AS vendor

# composer 설치에 필요한 도구
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl unzip git \
    && rm -rf /var/lib/apt/lists/*

# Composer 설치
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

# composer 라이브러리 설치 (의존성 파일 먼저 복사해서 캐시 활용)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --no-scripts --no-autoloader

# 소스 복사 후 오토로더 최적화
COPY . .
RUN composer dump-autoload --optimize --no-dev


# 빌드 결과물만 담은 최종 이미지 (Apache가 80포트로 서빙)
FROM base

WORKDIR /var/www/html

# composer 의존성 복사 (드물게 변경 → 서버에서 레이어 재사용)
COPY --from=vendor /var/www/html/vendor ./vendor

# 앱 소스 복사 (자주 변경되는 작은 레이어)
COPY . .

# 빌드된 프론트엔드 결과물 복사
COPY --from=frontend /app/public/build ./public/build

# 런타임에 필요한 스토리지 폴더 생성 후 권한 설정
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
