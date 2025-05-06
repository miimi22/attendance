# attendance

## 環境構築
Dockerビルド<br>
&emsp;1.
```
git clone git@github.com:miimi22/attendance.git
```
2.
```
cd attendance
```
3.
```
docker-compose up -d --build
```

Laravel環境構築<br>
&emsp;1. 
```
docker-compose exec php bash
```
2.
```
composer install
```
3. 「.env.example」ファイルをコピーして「.env」ファイルを作成する
```
cp .env.example .env
```
4. 「.env」ファイルの環境変数を次の通りに変更
```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=laravel_user
DB_PASSWORD=laravel_pass

MAIL_FROM_ADDRESS=test@example.com

MAILHOG_URL=http://localhost:8025
```
5. アプリケーションキーの作成
```
php artisan key:generate
```
6. マイグレーションの実行
```
php artisan migrate
```
7. シーディングの実行
```
php artisan db:seed
```

## テストアカウント
・管理者
<br>
name：鈴木 花子
<br>
email：hanako.s@coachtech.com
<br>
password：coachtech
<br>
<br>
・スタッフ
<br>
name：西 伶奈
<br>
email：reina.n@coachtech.com
<br>
password：coachtech1
<br>
<br>
name：山田 太郎
<br>
email：taro.y@coachtech.com
<br>
password：coachtech2
<br>
<br>
name：増田 一世
<br>
email：issei.m@coachtech.com
<br>
password：coachtech3
<br>
<br>
name：山本 敬吉
<br>
email：keikichi.y@coachtech.com
<br>
password：coachtech4
<br>
<br>
name：秋田 朋美
<br>
email：tomomi.a@coachtech.com
<br>
password：coachtech5
<br>
<br>
name：中西　教夫
<br>
email：norio.n@coachtech.com
<br>
password：coachtech6

## 使用技術(実行環境)
・PHP 8.1.32
<br>
・Laravel 8.83.29
<br>
・MySQL 8.0.41

## ER図
![attendance-er](https://github.com/user-attachments/assets/5ecc1f8b-508c-469f-ba56-fd5cb75a9517)

## URL
・出勤登録画面：http://localhost/attendance
<br>
・管理者ログイン画面：http://localhost/admin/login
<br>
・スタッフ登録画面：http://localhost/register
<br>
・スタッフログイン画面：http://localhost/login
<br>
・phpMyAdmin：http://localhost:8080
<br>
・MailHog：http://localhost:8025
