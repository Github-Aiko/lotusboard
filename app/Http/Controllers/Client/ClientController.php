<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Protocols\V2rayN;
use App\Http\Controllers\Controller;
use App\Services\ServerService;
use Illuminate\Http\Request;
use App\Services\UserService;

class ClientController extends Controller
{
    public function subscribe(Request $request)
    {
        $flag = $request->input('flag')
            ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $flag = strtolower($flag);
        $user = $request->user;
        // account not expired and is not banned.
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            $this->setSubscribeInfoToServers($servers, $user);
            if ($flag) {
                foreach (glob(app_path('Http//Controllers//Client//Protocols') . '/*.php') as $file) {
                    $file = 'App\\Http\\Controllers\\Client\\Protocols\\' . basename($file, '.php');
                    $class = new $file($user, $servers);
                    if (strpos($flag, $class->flag) !== false) {
                        die($class->handle());
                    }
                }
            }
            // todo 1.5.3 remove
            $class = new V2rayN($user, $servers);
            die($class->handle());
            die('Not supported client');
        }
    }

    private function setSubscribeInfoToServers(&$servers, $user)
    {
        if (!isset($servers[0])) return;
        if (!(int)config('v2board.show_info_to_server_enable', 0)) return;
        $useTraffic = round($user['u'] / (1024*1024*1024), 2) + round($user['d'] / (1024*1024*1024), 2);
        $totalTraffic = round($user['transfer_enable'] / (1024*1024*1024), 2);
        $remainingTraffic = $totalTraffic - $useTraffic;
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : 'Effective all the time';
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        array_unshift($servers, array_merge($servers[0], [
            'name' => "expired in {$expiredDate}",
        ]));
        if ($resetDay) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "Reset usage in：{$resetDay} day(s)",
            ]));
        }
        array_unshift($servers, array_merge($servers[0], [
            'name' => "Remain：{$remainingTraffic} GB",
        ]));
    }
}
