<?php

namespace App\Support;

use Illuminate\Http\Request;

class LegacyBridge
{
    public static function run(string $relativePath, string $fakeScriptName, Request $request)
    {
        $legacyBase = base_path('legacy_app/capstone');
        $fullPath = realpath($legacyBase . '/' . ltrim($relativePath, '/'));

        if ($fullPath === false || !str_starts_with(str_replace('\\', '/', $fullPath), str_replace('\\', '/', $legacyBase))) {
            abort(404, 'Legacy script not found.');
        }

        $oldCwd = getcwd();
        $serverBackup = $_SERVER;

        $_SERVER['SCRIPT_NAME'] = $fakeScriptName;
        $_SERVER['PHP_SELF'] = $fakeScriptName;
        $_SERVER['REQUEST_URI'] = '/' . ltrim($request->path(), '/');
        $_SERVER['REQUEST_METHOD'] = $request->getMethod();
        $_SERVER['QUERY_STRING'] = $request->getQueryString() ?? '';
        $_SERVER['DOCUMENT_ROOT'] = public_path();

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        chdir(dirname($fullPath));
        ob_start();
        try {
            include basename($fullPath);
            $content = ob_get_clean();
        } finally {
            if (is_string($oldCwd) && $oldCwd !== '') {
                chdir($oldCwd);
            }
            $_SERVER = $serverBackup;
        }

        return response($content);
    }
}
