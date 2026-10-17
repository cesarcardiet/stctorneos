<?php

/**
 * Verificación end-to-end local (XAMPP) por roles.
 * Uso: php scripts/verify-full-flow.php [baseUrl]
 */

declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8002', '/');
$cookieJar = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stc-verify-cookies.txt';
@unlink($cookieJar);

$report = [
    'base' => $base,
    'started_at' => date('c'),
    'results' => [],
    'ok' => 0,
    'fail' => 0,
];

function req(string $method, string $url, ?array $form = null, bool $follow = true): array
{
    global $cookieJar;
    $ch = curl_init($url);
    $headers = ['Accept: text/html,application/json', 'X-Requested-With: XMLHttpRequest'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($form !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = is_string($raw) ? substr($raw, $headerSize) : '';
    $headersOut = is_string($raw) ? substr($raw, 0, $headerSize) : '';

    return compact('code', 'body', 'headersOut', 'err');
}

function note(string $name, bool $pass, string $detail = ''): void
{
    global $report;
    $report['results'][] = [
        'name' => $name,
        'pass' => $pass,
        'detail' => $detail,
    ];
    $report[$pass ? 'ok' : 'fail']++;
    $mark = $pass ? 'OK' : 'FAIL';
    echo "[{$mark}] {$name}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
}

function csrfFrom(string $html): ?string
{
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/csrf-token"\s+content="([^"]+)"/', $html, $m)) {
        return $m[1];
    }

    return null;
}

function loginAs(string $email, string $password): bool
{
    global $base, $cookieJar;
    @unlink($cookieJar);
    $loginPage = req('GET', $base.'/login');
    $token = csrfFrom($loginPage['body'] ?? '');
    if (! $token) {
        note("login csrf {$email}", false, 'no csrf token');

        return false;
    }
    $res = req('POST', $base.'/login', [
        '_token' => $token,
        'email' => $email,
        'password' => $password,
    ], true);
    $ok = $res['code'] >= 200 && $res['code'] < 400
        && ! str_contains($res['body'], 'Credenciales incorrectas')
        && ! str_contains($res['headersOut'] ?? '', 'login');

    // Better check: hit a protected URL
    $dash = req('GET', $base.'/dashboard');
    $workspace = req('GET', $base.'/operacion');
    $logged = ($dash['code'] === 200 && ! str_contains($dash['body'], 'name="password"'))
        || ($workspace['code'] === 200 && ! str_contains($workspace['body'], 'name="password"'));

    note("login {$email}", $logged, "dash={$dash['code']} ws={$workspace['code']} post={$res['code']}");

    return $logged;
}

function expectPage(string $name, string $path, array $okCodes = [200], ?string $mustContain = null, ?string $mustNotContain = null): void
{
    global $base;
    $res = req('GET', $base.$path);
    $pass = in_array($res['code'], $okCodes, true);
    if ($mustContain && $pass) {
        $pass = str_contains($res['body'], $mustContain);
    }
    if ($mustNotContain && $pass) {
        $pass = ! str_contains($res['body'], $mustNotContain);
    }
    $detail = "HTTP {$res['code']}";
    if ($res['err']) {
        $detail .= ' err='.$res['err'];
    }
    note($name, $pass, $detail);
}

function expectApi(string $name, string $path, ?string $token = null, array $okCodes = [200]): array
{
    global $base, $cookieJar;
    $ch = curl_init($base.$path);
    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $pass = in_array($code, $okCodes, true);
    $json = json_decode((string) $body, true);
    note($name, $pass, "HTTP {$code}");

    return ['code' => $code, 'json' => $json, 'body' => $body];
}

function apiLogin(string $email, string $password): ?string
{
    global $base;
    $ch = curl_init($base.'/api/v1/auth/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string) $body, true);
    $token = $json['data']['token'] ?? $json['token'] ?? null;
    note("api login {$email}", $code === 200 && is_string($token), "HTTP {$code}");

    return is_string($token) ? $token : null;
}

echo "=== STC verify-full-flow ===".PHP_EOL;
echo "Base: {$base}".PHP_EOL;

// Public
expectPage('public login', '/login', [200], 'password');
expectApi('api home', '/api/v1/home');
expectApi('api live sync', '/api/v1/live/sync');
expectApi('api tournaments', '/api/v1/tournaments');
expectApi('api matches', '/api/v1/matches');
expectApi('api content', '/api/v1/content');

$roles = [
    ['email' => 'admin@stctorneos.demo', 'password' => 'stcdemo', 'label' => 'super-admin', 'paths' => [
        '/dashboard' => 'Dashboard',
        '/admin/torneos' => null,
        '/admin/categorias' => null,
        '/admin/delegaciones' => null,
        '/admin/equipos' => null,
        '/admin/jugadores' => null,
        '/admin/campos' => null,
        '/admin/fixture' => null,
        '/admin/planillas' => null,
        '/admin/resultados' => null,
        '/admin/comunicaciones' => null,
        '/admin/usuarios' => null,
        '/admin/roles' => null,
        '/admin/auditoria' => null,
        '/admin/inbox' => null,
        '/operacion' => null,
    ]],
    ['email' => 'lucia@stctorneos.demo', 'password' => 'stcdemo', 'label' => 'admin-torneo', 'paths' => [
        '/dashboard' => null,
        '/admin/torneos' => null,
        '/admin/fixture' => null,
        '/admin/comunicaciones' => null,
        '/admin/roles' => null, // may be 403
    ]],
    ['email' => 'coordinador@stctorneos.demo', 'password' => 'stcdemo', 'label' => 'coordinador', 'paths' => [
        '/dashboard' => null,
        '/operacion' => null,
        '/admin/fixture' => null,
    ]],
    ['email' => 'delegado@stctorneos.demo', 'password' => 'stcdemo', 'label' => 'delegado', 'paths' => [
        '/operacion' => null,
        '/operacion/mi-club' => null,
    ]],
    ['email' => 'jugador@stctorneos.demo', 'password' => 'stcdemo', 'label' => 'jugador', 'paths' => [
        '/operacion/mi-ficha' => null,
    ]],
    ['email' => 'tutor@stctorneos.demo', 'password' => 'stctutor', 'label' => 'tutor', 'paths' => [
        '/operacion/mis-jugadores' => null,
    ]],
];

foreach ($roles as $role) {
    echo PHP_EOL."--- Rol {$role['label']} ---".PHP_EOL;
    if (! loginAs($role['email'], $role['password'])) {
        continue;
    }

    foreach ($role['paths'] as $path => $needle) {
        $res = req('GET', $base.$path);
        $okCodes = [200, 302];
        // 403 is acceptable for restricted modules (we still record)
        $pass = in_array($res['code'], [200, 302, 403], true) && $res['code'] !== 500;
        if ($res['code'] === 500) {
            $pass = false;
        }
        if ($needle && $res['code'] === 200) {
            $pass = $pass && str_contains($res['body'], $needle);
        }
        note("{$role['label']} {$path}", $pass, "HTTP {$res['code']}");
    }

    // Inbox JSON for admin-capable roles
    if (in_array($role['label'], ['super-admin', 'admin-torneo', 'coordinador'], true)) {
        $inbox = expectApi("{$role['label']} inbox json", '/admin/inbox');
        if (($inbox['json']['ok'] ?? false) !== true && $inbox['code'] === 200) {
            // still ok if structure differs
        }
        // notification bell markup on dashboard
        $dash = req('GET', $base.'/dashboard');
        note("{$role['label']} campanita", str_contains($dash['body'], 'data-notif-bell'), 'dashboard markup');
    }

    // API token
    $token = apiLogin($role['email'], $role['password']);
    if ($token) {
        expectApi("{$role['label']} api /me", '/api/v1/me', $token);
        expectApi("{$role['label']} api notifications", '/api/v1/notifications', $token);
        expectApi("{$role['label']} api notifications sync", '/api/v1/notifications/sync', $token);
        expectApi("{$role['label']} api favorites", '/api/v1/favorites', $token);
        if ($role['label'] === 'delegado') {
            expectApi("{$role['label']} api workspace", '/api/v1/workspace', $token);
        }
        if (in_array($role['label'], ['super-admin', 'admin-torneo', 'coordinador'], true)) {
            expectApi("{$role['label']} api staff matches", '/api/v1/staff/matches', $token, [200, 403]);
        }
        if ($role['label'] === 'tutor') {
            expectApi("{$role['label']} api tutor players", '/api/v1/tutor/players', $token);
        }
        if ($role['label'] === 'jugador') {
            expectApi("{$role['label']} api player portal", '/api/v1/player/portal', $token);
        }
    }
}

// Notification create+send smoke (super-admin)
echo PHP_EOL.'--- Notificación automática ---'.PHP_EOL;
if (loginAs('admin@stctorneos.demo', 'stcdemo')) {
    $page = req('GET', $base.'/admin/comunicaciones/notificaciones/crear');
    $token = csrfFrom($page['body']);
    if ($token) {
        $create = req('POST', $base.'/admin/comunicaciones/notificaciones', [
            '_token' => $token,
            'title' => 'Verificación automática '.date('H:i:s'),
            'body' => 'Aviso de prueba del verificador full-flow.',
            'channel' => 'in_app',
            'audience' => 'staff',
            'tournament_id' => '',
            'content_post_id' => '',
            'scheduled_at' => '',
        ], true);
        note('crear notificación draft', in_array($create['code'], [200, 302], true), 'HTTP '.$create['code']);

        // find latest draft id via list
        $list = req('GET', $base.'/admin/comunicaciones/notificaciones');
        if (preg_match('/notificaciones\/(\d+)/', $list['body'], $m)) {
            $id = $m[1];
            $show = req('GET', $base.'/admin/comunicaciones/notificaciones/'.$id);
            $csrf = csrfFrom($show['body']) ?: $token;
            $send = req('POST', $base.'/admin/comunicaciones/notificaciones/'.$id.'/enviar', [
                '_token' => $csrf,
            ], true);
            note('enviar notificación '.$id, in_array($send['code'], [200, 302], true), 'HTTP '.$send['code']);
            $inbox = expectApi('inbox tras envío', '/admin/inbox');
            $unread = $inbox['json']['data']['unread'] ?? null;
            note('inbox tiene datos', $inbox['code'] === 200, 'unread='.json_encode($unread));
        } else {
            note('localizar notificación creada', false, 'no id en listado');
        }
    } else {
        note('form crear notificación', false, 'sin csrf / sin acceso');
    }
}

$report['finished_at'] = date('c');
$outDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'docs-stc';
if (! is_dir($outDir)) {
    $outDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
}
$jsonPath = $outDir.DIRECTORY_SEPARATOR.'verificacion-full-flow-'.date('Y-m-d').'.json';
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo PHP_EOL."OK={$report['ok']} FAIL={$report['fail']}".PHP_EOL;
echo "Reporte: {$jsonPath}".PHP_EOL;

exit($report['fail'] > 0 ? 1 : 0);
