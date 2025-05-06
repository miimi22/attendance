<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'name' => '鈴木 花子',
                'email' => 'hanako.s@coachtech.com',
                'password' => Hash::make('coachtech'),
                'role' => 1,
            ],
            [
                'name' => '西 伶奈',
                'email' => 'reina.n@coachtech.com',
                'password' => Hash::make('coachtech1'),
                'role' => 0,
            ],
            [
                'name' => '山田 太郎',
                'email' => 'taro.y@coachtech.com',
                'password' => Hash::make('coachtech2'),
                'role' => 0,
            ],
            [
                'name' => '増田 一世',
                'email' => 'issei.m@coachtech.com',
                'password' => Hash::make('coachtech3'),
                'role' => 0,
            ],
            [
                'name' => '山本 敬吉',
                'email' => 'keikichi.y@coachtech.com',
                'password' => Hash::make('coachtech4'),
                'role' => 0,
            ],
            [
                'name' => '秋田 朋美',
                'email' => 'tomomi.a@coachtech.com',
                'password' => Hash::make('coachtech5'),
                'role' => 0,
            ],
            [
                'name' => '中西 教夫',
                'email' => 'norio.n@coachtech.com',
                'password' => Hash::make('coachtech6'),
                'role' => 0,
            ],
        ]);
    }
}
