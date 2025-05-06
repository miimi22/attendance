# .docker/cron/Dockerfile

# Alpine Linux などの軽量イメージをベースにする
FROM alpine:latest

# docker-cli と docker-compose v2 (plugin) をインストール
# docker-compose exec を使うために必要
# 古い docker-compose v1 を使う場合は `docker-compose` パッケージ
RUN apk add --no-cache docker-cli docker-compose

# cron (Alpine標準のbusybox-cron) のために必要
RUN apk add --no-cache busybox-suid

# 作成したcrontabファイルをコンテナ内のcron設定ディレクトリにコピー
# Alpine の crond は /etc/crontabs/ ディレクトリを参照する
COPY .docker/cron/crontab /etc/crontabs/root

# crontabファイルのパーミッションを設定
RUN chmod 0644 /etc/crontabs/root

# ログファイルを作成（任意）
RUN touch /var/log/cron.log

# cronデーモンをフォアグラウンドで起動し、ログを標準出力/エラーに出す設定
# `-f`: フォアグラウンド実行
# `-L /dev/stdout`: ログを標準出力へ
# `-l 8`: ログレベル（デフォルト）
CMD ["crond", "-f", "-L", "/dev/stdout", "-l", "8"]