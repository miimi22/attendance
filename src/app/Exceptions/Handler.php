<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (AuthorizationException $e, $request) {
            // 403エラー(認可例外)が発生した詳細をログに記録
            Log::error('AuthorizationException Caught: '.$e->getMessage(), [
                'url' => $request->fullUrl(),      // アクセスされたURL
                'method' => $request->method(),    // HTTPメソッド (POSTなど)
                'user_id' => $request->user() ? $request->user()->id : 'Guest', // ユーザーID (ゲストの場合あり)
                'file' => $e->getFile(),          // ★例外が発生したファイル★
                'line' => $e->getLine(),          // ★例外が発生した行番号★
                // 'trace' => $e->getTraceAsString() // 必要ならトレースも (コメント解除)
            ]);
        });

        $this->renderable(function (HttpException $e, $request) {
        if ($e->getStatusCode() == 403) {
            Log::error('HttpException (403) Caught: '.$e->getMessage(), [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user() ? $request->user()->id : 'Guest',
                'file' => $e->getFile(),      // ★例外が発生したファイル★
                'line' => $e->getLine(),      // ★例外が発生した行番号★
            ]);
        }
    });
    }
}
