<?php
declare(strict_types=1);
const FALLOS_USUARIO = 5;
const FALLOS_IP = 20;
const VENTANA_FALLOS = 900;
const BLOQUEO_USUARIO = 900;
const BLOQUEO_IP = 1800;
const INACTIVIDAD = 600;
const MAILS_HORA = 3;
const RECUPERACION = 1800;
const MAX_MONTO = 10000000;
// __Host exige Path=/; __Secure permite aislar esta aplicación por subcarpeta.
const COOKIE_SESION = '__Secure-familia';
const COOKIE_CSRF = '__Secure-familia-csrf';
const MOTIVOS_RESCATE = ['Comprar algo que quiero','Salida con amigos','Regalo para alguien','Videojuego/apps','Ropa/zapatillas','Útiles/colegio','Pasarlo a mi cuenta del banco','Otro'];

function h(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ahora(int $seg = 0): string { return gmdate('Y-m-d H:i:s', time() + $seg); }
function datos(): string {
    static $d;
    if ($d) return $d;
    $r = getenv('FAMILIA_DATOS');
    $p = is_string($r) ? realpath($r) : false;
    $web = realpath(__DIR__ . '/../web');
    if (!$p || !is_dir($p) || $p === '/' || ($web && ($p === $web || str_starts_with($p, $web . '/'))))
        throw new RuntimeException('Configurar FAMILIA_DATOS fuera de web, en un directorio existente.');
    return $d = $p;
}
function env_archivo(string $archivo): array {
    $f = datos() . '/' . $archivo;
    clearstatcache(true, $f);
    if (!is_file($f) || is_link($f) || (fileperms($f) & 0777) !== 0600) throw new RuntimeException('Configuración privada ausente o sin permiso 0600.');
    $out = [];
    foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
        if ($linea === '' || str_starts_with($linea, '#')) continue;
        $pos = strpos($linea, '=');
        if ($pos !== false && $pos > 0) $out[substr($linea, 0, $pos)] = substr($linea, $pos + 1);
    }
    return $out;
}
function config_db(): array {
    static $c;
    if ($c !== null) return $c;
    $c = env_archivo('db.env');
    foreach (['APP_URL','APP_BASE'] as $k) { $v=getenv($k); if ($v!==false) $c[$k]=$v; }
    foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $k) if (!array_key_exists($k, $c)) throw new RuntimeException('Configuración de base incompleta.');
    if (!preg_match('/\A[A-Za-z0-9_]{0,24}\z/D', $c['DB_PREFIJO'] ?? '')) throw new RuntimeException('Prefijo inválido.');
    if (!preg_match('/\A[a-zA-Z0-9_.:-]+\z/D', $c['DB_HOST']) || !preg_match('/\A[a-zA-Z0-9_]+\z/D', $c['DB_NAME'])) throw new RuntimeException('Configuración de base inválida.');
    return $c;
}
function t(string $nombre): string {
    if (!in_array($nombre, ['usuarios','sesiones','limites','accesos','recuperaciones','categorias','sugerencias','premios','config','envios','rescates','movimientos','metas','fondo_cerrojo','fondo_movimientos','efectivo_movimientos','avatares','avatares_eventos','notas_fotos','notas_fotos_intentos','notas_eventos','categorias_eventos'], true)) throw new InvalidArgumentException('Tabla desconocida.');
    return '`' . (config_db()['DB_PREFIJO'] ?? '') . $nombre . '`';
}
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $c = config_db();
    $pdo = new PDO('mysql:host=' . $c['DB_HOST'] . ';dbname=' . $c['DB_NAME'] . ';charset=utf8mb4', $c['DB_USER'], $c['DB_PASS'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone = '+00:00'");
    // Tras esperar el bloqueo del chico, las sumas deben ver lo recién confirmado
    // por la otra transacción, no un snapshot anterior al FOR UPDATE.
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $sql = str_replace('{{P}}', $c['DB_PREFIJO'] ?? '', (string)file_get_contents(__DIR__ . '/../sql/esquema.sql'));
    // Este esquema controlado no contiene punto y coma dentro de textos ni procedimientos.
    foreach (explode(';', $sql) as $parte) if (trim($parte) !== '') $pdo->exec($parte);
    // Migración aditiva, idempotente, sin reescribir categorías ya configuradas.
    $sql=str_replace('{{P}}',$c['DB_PREFIJO']??'',(string)file_get_contents(__DIR__.'/../sql/correccion-3.sql'));
    foreach (explode(';',$sql) as $parte) if (trim($parte)!=='') $pdo->exec($parte);
    $sql=str_replace('{{P}}',$c['DB_PREFIJO']??'',(string)file_get_contents(__DIR__.'/../sql/correccion-5.sql'));
    foreach (explode(';',$sql) as $parte) if (trim($parte)!=='') $pdo->exec($parte);
    if ($pdo->query('SELECT valor FROM '.t('config')." WHERE clave='migracion_c7'")->fetchColumn()!=='1') {
        $nombre='c7:'.substr(hash('sha256',$c['DB_NAME'].'|'.($c['DB_PREFIJO']??'')),0,40);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,15)');$lock->execute([$nombre]);
        if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Migración ocupada. Reintentar.');
        try {
            if($pdo->query('SELECT valor FROM '.t('config')." WHERE clave='migracion_c7'")->fetchColumn()!=='1') {
                $sql=str_replace('{{P}}',$c['DB_PREFIJO']??'',(string)file_get_contents(__DIR__.'/../sql/correccion-7.sql'));
                foreach(explode(';',$sql)as$parte)if(trim($parte)!=='')$pdo->exec($parte);
            }
        } finally { $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$nombre]); }
    }
    if ($pdo->query('SELECT valor FROM '.t('config')." WHERE clave='migracion_c8'")->fetchColumn()!=='1') {
        $nombre='c8:'.substr(hash('sha256',$c['DB_NAME'].'|'.($c['DB_PREFIJO']??'')),0,40);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,15)');$lock->execute([$nombre]);
        if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Migración ocupada. Reintentar.');
        try {
            if($pdo->query('SELECT valor FROM '.t('config')." WHERE clave='migracion_c8'")->fetchColumn()!=='1') {
                $sql=str_replace('{{P}}',$c['DB_PREFIJO']??'',(string)file_get_contents(__DIR__.'/../sql/correccion-8.sql'));
                foreach(explode(';',$sql)as$parte)if(trim($parte)!=='')$pdo->exec($parte);
            }
        } finally { $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$nombre]); }
    }
    if ($pdo->query('SELECT valor FROM '.t('config')." WHERE clave='migracion_c8b'")->fetchColumn()!=='1') {
        $nombre='c8b:'.substr(hash('sha256',$c['DB_NAME'].'|'.($c['DB_PREFIJO']??'')),0,40);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,15)');$lock->execute([$nombre]);
        if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Migración ocupada. Reintentar.');
        try {
            if($pdo->query('SELECT valor FROM '.t('config')." WHERE clave='migracion_c8b'")->fetchColumn()!=='1') {
                $sql=str_replace('{{P}}',$c['DB_PREFIJO']??'',(string)file_get_contents(__DIR__.'/../sql/correccion-8b.sql'));
                foreach(explode(';',$sql)as$parte)if(trim($parte)!=='')$pdo->exec($parte);
            }
        } finally { $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$nombre]); }
    }
    if ($pdo->query('SELECT valor FROM '.t('config')." WHERE clave='migracion_c9'")->fetchColumn()!=='1') {
        $nombre='c9:'.substr(hash('sha256',$c['DB_NAME'].'|'.($c['DB_PREFIJO']??'')),0,40);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,15)');$lock->execute([$nombre]);
        if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Migración ocupada. Reintentar.');
        try {
            if($pdo->query('SELECT valor FROM '.t('config')." WHERE clave='migracion_c9'")->fetchColumn()!=='1') {
                $sql=str_replace('{{P}}',$c['DB_PREFIJO']??'',(string)file_get_contents(__DIR__.'/../sql/correccion-9.sql'));
                foreach(explode(';',$sql)as$parte)if(trim($parte)!=='')$pdo->exec($parte);
            }
        } finally { $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$nombre]); }
    }
    return $pdo;
}
function guardar_tema(int $actor,string $tema): void {
    if (!in_array($tema,['claro','oscuro'],true)) throw new DomainException('Tema inválido.');
    q('UPDATE '.t('usuarios').' SET tema=? WHERE id=? AND activo=1',[$tema,$actor]);
}
function q(string $sql, array $params = []): PDOStatement { $s = db()->prepare($sql); $s->execute($params); return $s; }
function uno(string $sql, array $params = []): ?array { return q($sql,$params)->fetch() ?: null; }
function cola_tras_commit(): object {static $cola;return $cola??=(object)['acciones'=>[]];}
function tras_commit(callable $f): void {
    if(db()->inTransaction())cola_tras_commit()->acciones[]=$f;
    else $f();
}
function transaccion(callable $f): mixed {
    if (db()->inTransaction()) return $f();
    $cola=cola_tras_commit();$cola->acciones=[];db()->beginTransaction();
    try { $r=$f();db()->commit(); }
    catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$cola->acciones=[];throw $e;}
    $acciones=$cola->acciones;$cola->acciones=[];
    foreach($acciones as$accion){try{$accion();}catch(Throwable $e){error_log('Mi ahorro: tarea posterior al commit pendiente.');}}
    return $r;
}
function secreto(string $nombre): string {
    $f = datos() . '/' . $nombre;
    if (!is_file($f)) {
        $viejo = umask(0077); $s = @fopen($f, 'x'); umask($viejo);
        if ($s) { fwrite($s, bin2hex(random_bytes(32))); fflush($s); fclose($s); }
    }
    clearstatcache(true,$f);
    if (is_link($f) || !is_file($f) || (fileperms($f)&0777)!==0600) throw new RuntimeException('Permisos de secreto incorrectos.');
    $v = trim((string)file_get_contents($f));
    if (!preg_match('/\A[a-f0-9]{64}\z/D',$v)) throw new RuntimeException('Secreto inválido.');
    return $v;
}
function ip_hash(?string $ip = null): string {
    $ip ??= is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'cli';
    $bin = @inet_pton($ip);
    // IPv4 mapeada en IPv6 se trata como la misma dirección. No se confía en X-Forwarded-For.
    if ($bin !== false && strlen($bin)===16 && substr($bin,0,12)===str_repeat("\0",10)."\xff\xff") $bin=substr($bin,12);
    return hash_hmac('sha256', $bin !== false ? bin2hex($bin) : $ip, secreto('ip-sal'));
}
function dispositivo(): string {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $so = match(true) { str_contains($ua,'Android')=>'Android', str_contains($ua,'iPhone')=>'iPhone', str_contains($ua,'iPad')=>'iPad', str_contains($ua,'Windows')=>'Windows', str_contains($ua,'Mac')=>'Mac', str_contains($ua,'Linux')=>'Linux', default=>'Otro' };
    $nav = match(true) { str_contains($ua,'Edg/')=>'Edge', str_contains($ua,'Firefox/')=>'Firefox', str_contains($ua,'Chrome/')=>'Chrome', str_contains($ua,'Safari/')=>'Safari', default=>'Otro' };
    return "$nav · $so";
}
function evento(string $tipo, ?int $usuario): void { q('INSERT INTO '.t('accesos').' (usuario,tipo,fecha,ip_hash,dispositivo) VALUES (?,?,?,?,?)',[$usuario,$tipo,ahora(),ip_hash(),dispositivo()]); }
function pin_valido(string $pin): bool {
    if (!preg_match('/\A[0-9]{6}\z/D',$pin) || preg_match('/\A([0-9])\1{5}\z/D',$pin)) return false;
    foreach([2,3] as $n) if(str_repeat(substr($pin,0,$n),intdiv(6,$n))===$pin) return false;
    return !str_contains('0123456789012345',$pin) && !str_contains('9876543210987654',$pin);
}
function hash_pin(string $pin): string {
    if (!defined('PASSWORD_ARGON2ID')) throw new RuntimeException('PHP debe incluir Argon2id.');
    return password_hash($pin,PASSWORD_ARGON2ID);
}
function hash_ficticio(): string {
    // Hash Argon2id persistente; el secreto nunca es un PIN utilizable.
    static $h;
    if ($h) return $h;
    $f=datos().'/hash-ficticio';
    if (!is_file($f)) {
        $valor=hash_pin(secreto('hash-semilla')); $old=umask(0077); $s=@fopen($f,'x'); umask($old);
        if ($s) { fwrite($s,$valor); fclose($s); }
    }
    if (is_link($f) || (fileperms($f)&0777)!==0600) throw new RuntimeException('Permisos de hash incorrectos.');
    return $h=trim((string)file_get_contents($f));
}
function cookie_token(string $nombre): ?string {
    $v=$_COOKIE[nombre_cookie($nombre)]??null;
    return is_string($v)&&preg_match('/\A[a-f0-9]{64}\z/D',$v)?$v:null;
}
function cookie(string $nombre,string $valor,int $vence=0): void {
    $nombre=nombre_cookie($nombre);
    setcookie($nombre,$valor,['expires'=>$vence,'path'=>ruta('/'),'secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    $_COOKIE[$nombre]=$valor;
}
function csrf(): string {
    $v=cookie_token(COOKIE_CSRF);
    if (!$v) { $v=bin2hex(random_bytes(32)); cookie(COOKIE_CSRF,$v); }
    // Cookie __Secure propia, firmada y rotada en cada login/salida. No ligar a
    // la cookie de sesión: SameSite=Strict la omite al abrir el link de un mail.
    return hash_hmac('sha256',$v,secreto('csrf-sal'));
}
function verificar_csrf(string $valor): void {
    if (!cookie_token(COOKIE_CSRF) || !hash_equals(csrf(),$valor)) throw new DomainException('La página venció. Volvé a abrirla y probá otra vez.');
}
function sesion(bool $renovar = false): ?array {
    $tok=cookie_token(COOKIE_SESION);
    if (!$tok) return null;
    $r=uno('SELECT u.*,s.visto FROM '.t('sesiones').' s JOIN '.t('usuarios').' u ON u.id=s.usuario WHERE s.token_hash=? AND s.visto>? AND u.activo=1',[hash('sha256',$tok),ahora(-INACTIVIDAD)]);
    if ($r && $renovar) { q('UPDATE '.t('sesiones').' SET visto=? WHERE token_hash=?',[ahora(),hash('sha256',$tok)]); $r['visto']=ahora(); }
    return $r;
}
function nueva_sesion(int $uid): void {
    if ($vieja=cookie_token(COOKIE_SESION)) q('DELETE FROM '.t('sesiones').' WHERE token_hash=?',[hash('sha256',$vieja)]);
    $tok=bin2hex(random_bytes(32));
    q('INSERT INTO '.t('sesiones').' (token_hash,usuario,visto) VALUES (?,?,?)',[hash('sha256',$tok),$uid,ahora()]);
    cookie(COOKIE_SESION,$tok); cookie(COOKIE_CSRF,bin2hex(random_bytes(32)));
}
function cerrar(?array $u): void {
    if ($tok=cookie_token(COOKIE_SESION)) q('DELETE FROM '.t('sesiones').' WHERE token_hash=?',[hash('sha256',$tok)]);
    if ($u) evento('salida',(int)$u['id']);
    cookie(COOKIE_SESION,'',time()-3600); cookie(COOKIE_CSRF,'',time()-3600);
}
function autenticar(string $nombre,string $pin,bool $iniciar=false): ?int {
    q('DELETE FROM '.t('accesos').' WHERE fecha<?',[ahora(-180*86400)]);
    q('DELETE FROM '.t('sesiones').' WHERE visto<?',[ahora(-INACTIVIDAD)]);
    $ip=ip_hash();
    q('INSERT IGNORE INTO '.t('limites').' (ip_hash) VALUES (?)',[$ip]);
    return transaccion(function() use($nombre,$pin,$ip,$iniciar) {
        $l=uno('SELECT * FROM '.t('limites').' WHERE ip_hash=? FOR UPDATE',[$ip]);
        $u=uno('SELECT * FROM '.t('usuarios').' WHERE nombre=? FOR UPDATE',[$nombre]);
        $uid=$u?(int)$u['id']:null;
        $ok=password_verify($pin,$u?$u['pin_hash']:hash_ficticio());
        if (($l['bloqueo']??'')>ahora() || ($u['bloqueo']??'')>ahora()) { evento('bloqueo',$uid); return null; }
        if (!$u || !$u['activo'] || !$ok || !preg_match('/\A[0-9]{6}\z/D',$pin)) {
            $fallos=($l['ventana']??'')>ahora(-VENTANA_FALLOS)?(int)$l['fallos']+1:1;
            $ventana=($l['ventana']??'')>ahora(-VENTANA_FALLOS)?$l['ventana']:ahora();
            q('UPDATE '.t('limites').' SET fallos=?,ventana=?,bloqueo=? WHERE ip_hash=?',[$fallos,$ventana,$fallos>=FALLOS_IP?ahora(BLOQUEO_IP):null,$ip]);
            if ($u) {
                $f=($u['ventana']??'')>ahora(-VENTANA_FALLOS)?(int)$u['fallos']+1:1;
                $v=($u['ventana']??'')>ahora(-VENTANA_FALLOS)?$u['ventana']:ahora();
                q('UPDATE '.t('usuarios').' SET fallos=?,ventana=?,bloqueo=? WHERE id=?',[$f,$v,$f>=FALLOS_USUARIO?ahora(BLOQUEO_USUARIO):null,$uid]);
                if ($f>=FALLOS_USUARIO) evento('bloqueo',$uid);
            }
            evento('fallo',$uid); if ($fallos>=FALLOS_IP) evento('bloqueo',$uid);
            return null;
        }
        q('UPDATE '.t('usuarios').' SET fallos=0,ventana=NULL,bloqueo=NULL WHERE id=?',[$uid]);
        evento('ingreso',$uid);
        // Crear la sesión antes de liberar el usuario: un cambio de PIN concurrente
        // no puede dejar viva una sesión autenticada con el PIN anterior.
        if ($iniciar) nueva_sesion($uid);
        return $uid;
    });
}
function desbloquear(string $valor): void {
    if (filter_var($valor,FILTER_VALIDATE_IP)) $valor=ip_hash($valor);
    if (preg_match('/\A[a-f0-9]{64}\z/D',$valor)) q('UPDATE '.t('limites').' SET fallos=0,ventana=NULL,bloqueo=NULL WHERE ip_hash=?',[$valor]);
    else q('UPDATE '.t('usuarios').' SET fallos=0,ventana=NULL,bloqueo=NULL WHERE nombre=?',[$valor]);
}
function mail_valido(string $m): bool { return strlen($m)<=120 && !strpbrk($m,"\r\n") && filter_var($m,FILTER_VALIDATE_EMAIL)!==false; }
function mail_oculto(?string $m): string { if (!$m || !mail_valido($m)) return 'sin mail cargado'; [$a,$b]=explode('@',$m,2); return substr($a,0,1).'•••@'.$b; }
function url_app(): string {
    $u=config_db()['APP_URL']??'';
    if (!preg_match('~\Ahttps://[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?(?::[0-9]{1,5})?\z~D',$u)) throw new RuntimeException('Configurar APP_URL=https://subdominio en db.env, sin barra final.');
    return $u;
}
function app_base(): string {
    $b=config_db()['APP_BASE']??'';
    if ($b!=='' && !preg_match('~\A(?:/[A-Za-z0-9_-]+)+\z~D',$b)) throw new RuntimeException('APP_BASE debe ser vacío o /subcarpeta sin barra final.');
    return $b;
}
function ruta(string $ruta='/'): string {
    // Sólo rutas lógicas internas, nunca URL absoluta, //host, CRLF ni traversal.
    if (!str_starts_with($ruta,'/') || str_starts_with($ruta,'//') || preg_match('~[\x00-\x20\\\\]|(?:^|/)\.\.(?:/|$)~',$ruta)) throw new DomainException('Ruta no válida.');
    return app_base().$ruta;
}
function nombre_cookie(string $nombre): string { return $nombre.'-'.substr(hash('sha256',app_base()),0,12); }
function enlace_recuperacion(string $token): string { return url_app().ruta('/recuperar.php').'?t='.rawurlencode($token); }
function origen_permitido(array $s): bool {
    $origin=$s['HTTP_ORIGIN']??''; $sitio=$s['HTTP_SEC_FETCH_SITE']??'';
    if ($origin==='null' && $sitio==='same-origin') $origin='';
    if (($origin!=='' && $origin!==url_app()) || $sitio==='cross-site') return false;
    // Origin no tiene ruta. Si hay Referer, su origen y ruta sí deben pertenecer al subsitio.
    $ref=$s['HTTP_REFERER']??'';
    if ($ref!=='') {
        $p=parse_url($ref); if (!$p || isset($p['user']) || isset($p['pass'])) return false;
        $origen=($p['scheme']??'').'://'.($p['host']??'').(isset($p['port'])?':'.$p['port']:'');
        $path=$p['path']??'/';
        if ($origen!==url_app() || (app_base()!=='' && !str_starts_with($path,ruta('/')))) return false;
    }
    return true; // CSRF sigue siendo obligatorio aunque falten ambos encabezados.
}
function enviar_mail(string $para,string $texto): bool {
    if (!mail_valido($para)) return false;
    try { $c=env_archivo('mail.env'); } catch(Throwable $e) { return false; }
    foreach(['MAIL_HOST','MAIL_PORT','MAIL_USER','MAIL_PASS','MAIL_DE'] as $k) if (!isset($c[$k])) return false;
    if (!preg_match('/\A[a-zA-Z0-9.-]+\z/D',$c['MAIL_HOST']) || !in_array($c['MAIL_PORT'],['465','587'],true) || !mail_valido($c['MAIL_DE']) || strpbrk($c['MAIL_USER'],"\r\n")) return false;
    $ssl=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$c['MAIL_HOST']]]);
    $s=@stream_socket_client(($c['MAIL_PORT']==='465'?'ssl':'tcp').'://'.$c['MAIL_HOST'].':'.$c['MAIL_PORT'],$errno,$error,10,STREAM_CLIENT_CONNECT,$ssl);
    if (!$s) return false;
    stream_set_timeout($s,10);
    $cmd=function(?string $com,string $codigo) use($s):bool {
        if ($com!==null && fwrite($s,$com."\r\n")===false) return false;
        for($i=0;$i<100;$i++) { $l=fgets($s,2048); if ($l===false) return false; if (($l[3]??'')===' ') return substr($l,0,3)===$codigo; }
        return false;
    };
    $ok=$cmd(null,'220') && $cmd('EHLO mi-ahorro.local','250');
    if ($ok && $c['MAIL_PORT']==='587') $ok=$cmd('STARTTLS','220') && stream_socket_enable_crypto($s,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)===true && $cmd('EHLO mi-ahorro.local','250');
    $de=$c['MAIL_DE'];
    $body=str_replace(["\r\n","\r"],"\n",$texto); $body=str_replace("\n","\r\n",preg_replace('/^\./m','..',$body));
    $mensaje="From: Mi ahorro <$de>\r\nTo: <$para>\r\nSubject: Recuperar tu PIN de Mi ahorro\r\nDate: ".date('r')."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$body;
    $ok=$ok && $cmd('AUTH LOGIN','334') && $cmd(base64_encode($c['MAIL_USER']),'334') && $cmd(base64_encode($c['MAIL_PASS']),'235') && $cmd("MAIL FROM:<$de>",'250') && $cmd("RCPT TO:<$para>",'250') && $cmd('DATA','354') && $cmd($mensaje."\r\n.",'250');
    @fwrite($s,"QUIT\r\n"); fclose($s); return $ok;
}
function pedir_recuperacion(string $nombre): void {
    $envio=transaccion(function() use($nombre) {
        $u=uno('SELECT * FROM '.t('usuarios').' WHERE nombre=? FOR UPDATE',[$nombre]);
        $id=$u?(int)$u['id']:null;
        $cuenta=$u?(int)q('SELECT COUNT(*) FROM '.t('accesos').' WHERE usuario=? AND tipo=? AND fecha>?',[$id,'recuperacion_pedida',ahora(-3600)])->fetchColumn():MAILS_HORA;
        evento('recuperacion_pedida',$id);
        if (!$u || !$u['activo'] || !$u['mail'] || $cuenta>=MAILS_HORA) return null;
        $tok=bin2hex(random_bytes(32));
        q('INSERT INTO '.t('recuperaciones').' (token_hash,usuario,creado,vence) VALUES (?,?,?,?)',[hash('sha256',$tok),$id,ahora(),ahora(RECUPERACION)]);
        return [$u['mail'],$tok];
    });
    if ($envio) {
        try { $link=enlace_recuperacion($envio[1]); $ok=enviar_mail($envio[0],"Pediste un PIN nuevo de Mi ahorro. Abrí este enlace dentro de 30 minutos:\n$link\n\nSi no lo pediste, ignorá este mensaje. No compartas el enlace."); }
        catch(Throwable $e) { $ok=false; }
        if (!$ok) q('UPDATE '.t('recuperaciones').' SET usado=? WHERE token_hash=?',[ahora(),hash('sha256',$envio[1])]);
    }
}
function cambiar_pin(int $uid,string $pin,bool $provisorio=false): void {
    if (!pin_valido($pin)) throw new DomainException('Elegí 6 números que no sean todos iguales ni una secuencia como 123456.');
    q('UPDATE '.t('usuarios').' SET pin_hash=?,debe_cambiar=?,fallos=0,ventana=NULL,bloqueo=NULL WHERE id=?',[hash_pin($pin),(int)$provisorio,$uid]);
    q('DELETE FROM '.t('sesiones').' WHERE usuario=?',[$uid]);
    q('UPDATE '.t('recuperaciones').' SET usado=? WHERE usuario=? AND usado IS NULL',[ahora(),$uid]);
}
function recuperar_pin(string $tok,string $pin): void {
    if (!preg_match('/\A[a-f0-9]{64}\z/D',$tok)) throw new DomainException('El enlace venció o ya fue usado.');
    // Leer usuario sin bloquear, luego siempre bloquear usuario antes de recuperación (orden común).
    $r=uno('SELECT usuario FROM '.t('recuperaciones').' WHERE token_hash=?',[hash('sha256',$tok)]);
    if (!$r) throw new DomainException('El enlace venció o ya fue usado.');
    transaccion(function() use($r,$tok,$pin) {
        $u=uno('SELECT * FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$r['usuario']]);
        $v=uno('SELECT * FROM '.t('recuperaciones').' WHERE token_hash=? AND usado IS NULL AND vence>? FOR UPDATE',[hash('sha256',$tok),ahora()]);
        if (!$u || !$u['activo'] || !$v) throw new DomainException('El enlace venció o ya fue usado.');
        cambiar_pin((int)$u['id'],$pin); evento('recuperacion_usada',(int)$u['id']);
    });
}
function entero(mixed $v,int $min=1,int $max=MAX_MONTO): int {
    if ((!is_string($v)&&!is_int($v)) || !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',(string)$v) || (int)$v<$min || (int)$v>$max) throw new DomainException("Ingresá un número entero entre $min y $max.");
    return (int)$v;
}
function texto(array $in,string $k,int $max,bool $obligatorio=false): string {
    $v=$in[$k]??'';
    if (!is_string($v) || !preg_match('//u',$v) || strlen($v)>$max*4 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$v)) throw new DomainException('Revisá el campo '.$k.'.');
    $v=trim($v);
    if (mb_strlen($v)>$max || ($obligatorio && $v==='')) throw new DomainException('Revisá el campo '.$k.'.');
    return $v;
}
function nino(int $id,bool $bloquear=false): array {
    $u=uno('SELECT * FROM '.t('usuarios')." WHERE id=? AND rol='nino'".($bloquear?' FOR UPDATE':''),[$id]);
    if (!$u || !$u['activo']) throw new DomainException('Elegí un usuario activo.');
    return $u;
}
function saldo(int $uid): array {
    // Un anulado sigue en el libro: su inverso lo compensa. Nunca excluirlo además del inverso.
    $m=uno('SELECT COALESCE(SUM(CASE WHEN tipo=\'ingreso\' AND estado IN (\'confirmado\',\'anulado\') THEN monto ELSE 0 END),0) ingresos, COALESCE(SUM(CASE WHEN tipo=\'egreso\' AND estado IN (\'confirmado\',\'anulado\') THEN monto ELSE 0 END),0) egresos, COALESCE(SUM(CASE WHEN tipo=\'ingreso\' AND estado=\'pendiente\' THEN monto ELSE 0 END),0) ingresos_pendientes, COALESCE(SUM(CASE WHEN tipo=\'egreso\' AND estado=\'pendiente\' THEN monto ELSE 0 END),0) gastos_pendientes FROM '.t('movimientos').' WHERE usuario=?',[$uid]);
    $r=(int)q('SELECT COALESCE(SUM(monto),0) FROM '.t('rescates')." WHERE usuario=? AND estado IN ('pendiente','aprobado')",[$uid])->fetchColumn();
    return ['disponible'=>(int)$m['ingresos']-(int)$m['egresos']-$r,'ingresos_pendientes'=>(int)$m['ingresos_pendientes'],'gastos_pendientes'=>(int)$m['gastos_pendientes'],'reservado'=>$r];
}
function mes_limites(string $mes=''): array {
    if ($mes==='') $mes=(new DateTimeImmutable('now',new DateTimeZone('America/Argentina/Cordoba')))->format('Y-m');
    if (!preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/D',$mes)) throw new DomainException('Elegí un mes válido.');
    $d=new DateTimeImmutable($mes.'-01 00:00:00',new DateTimeZone('America/Argentina/Cordoba'));
    return [$d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$d->modify('+1 month')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
}
function resumen_mes(int $id): array {
    [$a,$b]=mes_limites();
    $r=uno('SELECT COALESCE(SUM(CASE WHEN tipo=\'ingreso\' THEN monto ELSE 0 END),0) ingresos, COALESCE(SUM(CASE WHEN tipo=\'egreso\' THEN monto ELSE 0 END),0) egresos FROM '.t('movimientos')." WHERE usuario=? AND estado IN ('confirmado','anulado') AND confirmado>=? AND confirmado<?",[$id,$a,$b]);
    return ['ingresos'=>(int)$r['ingresos'],'egresos'=>(int)$r['egresos'],'ahorro'=>(int)$r['ingresos']-(int)$r['egresos']];
}
function agregar_movimiento(int $uid,string $tipo,int $monto,?array $cat,string $concepto,string $nota,int $actor,string $estado='confirmado',?int $grado=null,?string $materia=null,?int $rescate=null,?int $inverso=null): int {
    entero($monto,($grado!==null && $grado<7)?0:1);
    q('INSERT INTO '.t('movimientos').' (usuario,tipo,monto,categoria,concepto,icono,nota,grado,materia,estado,fecha,creado_por,confirmado_por,confirmado,rescate,inverso_de) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$uid,$tipo,$monto,$cat['id']??null,$concepto,$cat['icono']??'↩',$nota,$grado,$materia,$estado,ahora(),$actor,$estado==='confirmado'?$actor:null,$estado==='confirmado'?ahora():null,$rescate,$inverso]);
    return (int)db()->lastInsertId();
}
function categoria(int $id): array {
    $c=uno('SELECT * FROM '.t('categorias').' WHERE id=? AND activa=1'.(db()->inTransaction()?' FOR UPDATE':''),[$id]);
    if (!$c) throw new DomainException('Esa categoría ya no está disponible.');
    return $c;
}
function anotar_gasto(int $uid,int $cid,int $monto,string $nota): int {
    return transaccion(function() use($uid,$cid,$monto,$nota) {
        nino($uid,true); $c=categoria($cid);
        if ($c['grupo']!=='gasto' || $c['tipo']!=='egreso') throw new DomainException('Elegí una categoría de gasto.');
        return agregar_movimiento($uid,'egreso',$monto,$c,$c['nombre'],$nota,$uid,'pendiente');
    });
}
function motivos_retiro(): array {
    $normalizar=static function(string $s):string{
        $s=mb_strtolower(trim($s));$s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
        return match($s){'regalo para alguien','regalo'=>'regalo','ropa','ropa/zapatillas'=>'ropa','videojuego/apps','videojuegos/apps'=>'videojuegos',default=>$s};
    };
    $lista=[];$nombres=array_column(q('SELECT nombre FROM '.t('categorias')." WHERE activa=1 AND grupo='gasto' AND tipo='egreso' ORDER BY orden,id")->fetchAll(),'nombre');
    foreach($nombres as$n){$n=$n==='Regalo para alguien'?'Regalo':$n;$k=$normalizar($n);if(!isset($lista[$k]))$lista[$k]=$n;}
    return array_combine(array_values($lista),array_values($lista));
}
function pedir_rescate(int $uid,int $monto,string $motivo,string $nota): int {
    entero($monto);
    if (!in_array($motivo==='Regalo para alguien'?'Regalo':$motivo,array_values(motivos_retiro()),true)) throw new DomainException('Elegí un motivo.');
    return transaccion(function() use($uid,$monto,$motivo,$nota) {
        nino($uid,true); if ($monto>saldo($uid)['disponible']) throw new DomainException('Ese monto supera tu saldo disponible.');
        q('INSERT INTO '.t('rescates').' (usuario,monto,motivo,nota,fecha,creado_por) VALUES (?,?,?,?,?,?)',[$uid,$monto,$motivo,$nota,ahora(),$uid]);
        return (int)db()->lastInsertId();
    });
}
function decidir_gasto(int $id,int $actor,bool $confirmar): void {
    decidir_movimiento($id,$actor,$confirmar,'egreso');
}
function decidir_movimiento(int $id,int $actor,bool $aprobar,?string $tipoEsperado=null): void {
    exigir_admin($actor);
    $m=uno('SELECT usuario FROM '.t('movimientos').' WHERE id=?',[$id]); if (!$m) throw new DomainException('Movimiento no disponible.');
    transaccion(function() use($id,$actor,$aprobar,$m,$tipoEsperado) {
        nino((int)$m['usuario'],true); $r=uno('SELECT * FROM '.t('movimientos').' WHERE id=? FOR UPDATE',[$id]);
        if (!$r || $r['estado']!=='pendiente' || (int)$r['creado_por']!==(int)$r['usuario'] || ($tipoEsperado!==null && $r['tipo']!==$tipoEsperado)) throw new DomainException('Ese movimiento ya fue revisado o no está disponible.');
        if ($aprobar && $r['tipo']==='egreso' && (int)$r['monto']>saldo((int)$r['usuario'])['disponible']) throw new DomainException('No alcanza el saldo libre. Revisá primero los rescates reservados.');
        q('UPDATE '.t('movimientos').' SET estado=?,confirmado_por=?,confirmado=? WHERE id=?',[$aprobar?'confirmado':'rechazado',$actor,ahora(),$id]);
        if($aprobar)nota_foto_programar($id,$actor);
    });
}
function cancelar_ingreso(int $uid,int $id): void {
    $propio=uno('SELECT usuario,creado_por,tipo FROM '.t('movimientos').' WHERE id=?',[$id]);
    if (!$propio || (int)$propio['usuario']!==$uid || (int)$propio['creado_por']!==$uid || $propio['tipo']!=='ingreso') throw new DomainException('Sólo podés cancelar un ingreso tuyo que siga pendiente.');
    transaccion(function() use($uid,$id) {
        nino($uid,true);
        $r=uno('SELECT * FROM '.t('movimientos').' WHERE id=? FOR UPDATE',[$id]);
        if (!$r || (int)$r['usuario']!==$uid || (int)$r['creado_por']!==$uid || $r['tipo']!=='ingreso' || $r['estado']!=='pendiente') throw new DomainException('Sólo podés cancelar un ingreso tuyo que siga pendiente.');
        // Sin migrar el ENUM: rechazo por el propio chico = cancelación, visible
        // como tal en la UI y distinguible por confirmado_por = usuario.
        q('UPDATE '.t('movimientos')." SET estado='rechazado',confirmado_por=?,confirmado=? WHERE id=?",[$uid,ahora(),$id]);
    });
}
function estado_movimiento(array $m): string {
    if ($m['tipo']==='ingreso' && $m['estado']==='rechazado' && (int)($m['confirmado_por']??0)===(int)$m['usuario']) return 'cancelado por el usuario';
    if ($m['estado']==='pendiente') return $m['tipo']==='ingreso'?'por aprobar':'a confirmar';
    return $m['estado'];
}
function decidir_rescate(int $id,int $actor,string $accion,string $medio='',?int $propietario=null): void {
    if ($propietario===null) exigir_admin($actor);
    elseif ($actor!==$propietario) throw new DomainException('No podés operar ese pedido.');
    $r=uno('SELECT usuario FROM '.t('rescates').' WHERE id=?',[$id]); if (!$r) throw new DomainException('Pedido no disponible.');
    transaccion(function() use($id,$actor,$accion,$medio,$propietario,$r) {
        nino((int)$r['usuario'],true); $v=uno('SELECT * FROM '.t('rescates').' WHERE id=? FOR UPDATE',[$id]);
        if ($propietario!==null && ((int)$v['usuario']!==$propietario || $accion!=='cancelar')) throw new DomainException('No podés operar ese pedido.');
        $nuevo=match($accion) { 'aprobar'=>'aprobado','rechazar'=>'rechazado','entregar'=>'entregado','cancelar'=>'cancelado',default=>throw new DomainException('Acción inválida.') };
        if (($accion==='entregar' && !in_array($v['estado'],['pendiente','aprobado'],true)) || ($accion!=='entregar' && !in_array($v['estado'],$accion==='rechazar'?['pendiente','aprobado']:['pendiente'],true))) throw new DomainException('El pedido ya cambió de estado.');
        if ($accion==='entregar') {
            if (!in_array($medio,['Transferencia a su cuenta','Efectivo'],true)) throw new DomainException('Elegí cómo se entregó.');
            if($v['estado']==='pendiente')q('UPDATE '.t('rescates').' SET aprobado_por=?,aprobado=?,decidido_por=?,decidido=? WHERE id=?',[$actor,ahora(),$actor,ahora(),$id]);
            q('UPDATE '.t('rescates').' SET estado=?,entregado_por=?,entregado=?,medio=? WHERE id=?',[$nuevo,$actor,ahora(),$medio,$id]);
            agregar_movimiento((int)$v['usuario'],'egreso',(int)$v['monto'],null,'Rescate: '.$v['motivo'],$v['nota'].' · '.$medio,$actor,'confirmado',null,null,$id);
        } else {
            q('UPDATE '.t('rescates').' SET estado=?,decidido_por=?,decidido=? WHERE id=?',[$nuevo,$actor,ahora(),$id]);
            if($accion==='aprobar') q('UPDATE '.t('rescates').' SET aprobado_por=?,aprobado=? WHERE id=?',[$actor,ahora(),$id]);
        }
        if ($v['meta']!==null && in_array($accion,['entregar','rechazar','cancelar'],true)) {
            $m=uno('SELECT * FROM '.t('metas').' WHERE id=? FOR UPDATE',[$v['meta']]);
            if (!$m || (int)$m['usuario']!==(int)$v['usuario'] || $m['estado']!=='rescate_pedido') throw new DomainException('La meta vinculada cambió de estado.');
            q('UPDATE '.t('metas').' SET estado=?,estado_fecha=? WHERE id=?',[$accion==='entregar'?'rescatada':'activa',ahora(),$m['id']]);
        }
    });
}
function anular(int $id,int $actor,string $motivo): int {
    exigir_admin($actor);
    if (trim($motivo)==='') throw new DomainException('Contá por qué lo anulás.');
    $m=uno('SELECT usuario FROM '.t('movimientos').' WHERE id=?',[$id]); if (!$m) throw new DomainException('Movimiento no disponible.');
    return transaccion(function() use($id,$actor,$motivo,$m) {
        bloquear_fondo(); // Orden común: fondo → chico → movimiento.
        nino((int)$m['usuario'],true); $r=uno('SELECT * FROM '.t('movimientos').' WHERE id=? FOR UPDATE',[$id]);
        if ($r['estado']!=='confirmado' || $r['inverso_de']!==null) throw new DomainException('Sólo se anula una vez un movimiento confirmado original.');
        if ($r['tipo']==='ingreso' && (int)$r['monto']>saldo((int)$r['usuario'])['disponible']) throw new DomainException('No podés anular plata ya gastada o reservada. Resolvé primero esas operaciones.');
        $inv=agregar_movimiento((int)$r['usuario'],$r['tipo']==='ingreso'?'egreso':'ingreso',(int)$r['monto'],null,'Anulación de #'.$id,$motivo,$actor,'confirmado',$r['grado']===null?null:(int)$r['grado'],$r['materia'],null,$id);
        $f=uno('SELECT delta FROM '.t('fondo_movimientos').' WHERE movimiento=?',[$id]);
        if ($f) registrar_fondo($inv,-(int)$f['delta']);
        q('UPDATE '.t('movimientos')." SET estado='anulado',anulado_por=?,anulado=?,motivo_anulacion=?,inverso_id=? WHERE id=?",[$actor,ahora(),$motivo,$inv,$id]);
        return $inv;
    });
}
function bloquear_fondo(): void {
    if (!db()->inTransaction()) throw new LogicException('El Fondo requiere transacción.');
    uno('SELECT id FROM '.t('fondo_cerrojo').' WHERE id=1 FOR UPDATE');
}
function saldo_fondo(): int { return (int)q('SELECT COALESCE(SUM(delta),0) FROM '.t('fondo_movimientos'))->fetchColumn(); }
function registrar_fondo(int $movimiento,int $delta): void {
    bloquear_fondo();
    if (abs($delta)>MAX_MONTO || ($delta<0 && -$delta>saldo_fondo())) throw new DomainException('No alcanza el Fondo de devaluaciones. No se guardó nada.');
    q('INSERT INTO '.t('fondo_movimientos').' (movimiento,delta) VALUES (?,?)',[$movimiento,$delta]);
}
