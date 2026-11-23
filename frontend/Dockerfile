# Stage 1: build the Flutter web bundle.
# Flutter is installed from its git tag so the image matches the local and CI version (3.47.2).
FROM debian:bookworm-slim AS build
ARG FLUTTER_VERSION=3.47.2
RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl unzip xz-utils ca-certificates \
    && rm -rf /var/lib/apt/lists/*
RUN git clone --depth 1 --branch ${FLUTTER_VERSION} https://github.com/flutter/flutter.git /opt/flutter
ENV PATH="/opt/flutter/bin:${PATH}"
RUN flutter config --no-analytics --enable-web \
    && flutter precache --web --no-android --no-ios --no-linux --no-windows --no-macos --no-fuchsia

WORKDIR /src
COPY pubspec.yaml pubspec.lock ./
RUN flutter pub get
COPY . .
# The web app calls the API on the same origin through Caddy (/api/v1).
RUN flutter build web --release --dart-define=API_BASE_URL=/api/v1

# Stage 2: serve static files with nginx
FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /src/build/web /usr/share/nginx/html
EXPOSE 80
