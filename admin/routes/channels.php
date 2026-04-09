<?php

use App\Models\GenerationJob;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| 定义私有广播频道授权规则
| 用户只能接收自己任务的状态更新
|
*/

// 用户私有频道 - 仅允许用户接收自己的任务状态更新
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});
