<?php

namespace App\Http\Controllers;

use App\Support\LegacyBridge;
use Illuminate\Http\Request;

class LegacyBridgeController extends Controller
{
    public function index(Request $request)
    {
        return LegacyBridge::run('index.php', '/index.php', $request);
    }

    public function view(Request $request, string $file)
    {
        return LegacyBridge::run('views/' . $file . '.php', '/views/' . $file . '.php', $request);
    }

    public function controller(Request $request, string $file)
    {
        return LegacyBridge::run('controllers/' . $file . '.php', '/controllers/' . $file . '.php', $request);
    }

    public function api(Request $request, string $file)
    {
        return LegacyBridge::run('api/' . $file . '.php', '/api/' . $file . '.php', $request);
    }

    public function script(Request $request, string $file)
    {
        return LegacyBridge::run('scripts/' . $file . '.php', '/scripts/' . $file . '.php', $request);
    }
}
