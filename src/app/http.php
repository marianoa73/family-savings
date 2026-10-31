<?php
declare(strict_types=1);
require_once __DIR__.'/acciones.php';
require_once __DIR__.'/avatares.php';
ini_set('display_errors','0');
set_exception_handler(function(Throwable $e):void {
    if($e instanceof DomainException) {
        http_response_code(400); header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store');
        echo 'Revisá los datos de la página y volvé a ingresar desde la portada.'; return;
    }
    // Nunca imprimir consultas, credenciales ni tokens de recuperación.
    error_log('Mi ahorro: error interno '.get_class($e).' en '.basename($e->getFile()).':'.$e->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store'); }
    echo 'No pudimos abrir Mi ahorro. Avisale a un adulto e intentá más tarde.';
});
header_remove('X-Powered-By');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Strict-Transport-Security: max-age=31536000');
header('X-Robots-Tag: noindex, nofollow');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
$metodo=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($metodo,['GET','POST'],true)) { http_response_code(405); header('Allow: GET, POST'); exit('Método no permitido.'); }
if (($_SERVER['HTTPS']??'')!=='on' && ($_SERVER['HTTPS']??'')!=='1') {
    header('Location: '.url_app().ruta('/'),true,303); exit;
}
if (strtolower((string)($_SERVER['HTTP_HOST']??''))!==strtolower((string)parse_url(url_app(),PHP_URL_HOST).(parse_url(url_app(),PHP_URL_PORT)?':'.parse_url(url_app(),PHP_URL_PORT):''))) { http_response_code(400); exit('Dominio no configurado.'); }
function entrada(): array {
    $mime=strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]));
    $multipart=$mime==='multipart/form-data';
    if ($mime!=='application/x-www-form-urlencoded'&&!$multipart) { http_response_code(415); exit('Formato no permitido.'); }
    if($multipart){if((int)($_SERVER['CONTENT_LENGTH']??0)>12*1024*1024){http_response_code(413);exit('La foto debe pesar hasta 10 MB.');}}
    else{$raw=file_get_contents('php://input',false,null,0,20001);if($raw===false||strlen($raw)>20000||(int)($_SERVER['CONTENT_LENGTH']??0)>20000){http_response_code(413);exit('Formulario demasiado grande.');}}
    // 22/10 (Claude): con Referrer-Policy no-referrer Chrome manda "Origin: null" en el propio sitio; se acepta null sólo si
    // el navegador confirma same-origin. El token CSRF sigue siendo la prueba principal.
    if (!origen_permitido($_SERVER)) { http_response_code(403); exit('Origen no permitido.'); }
    foreach($_POST as $k=>$v) if (!is_string($v) || strlen($k)>64 || strlen($v)>1600) { http_response_code(400); exit('Formulario inválido.'); }
    foreach($_POST as$k=>$v)if(str_starts_with((string)$k,'_'))throw new DomainException('Formulario inválido.');
    verificar_csrf(texto($_POST,'csrf',64,true));
    if($multipart&&!in_array($_POST['accion']??'',['ingreso','rapida'],true))throw new DomainException('Foto sólo en un ingreso de nota.');
    return $_POST;
}
function ir(string $ruta='/'): never { header('Location: '.ruta($ruta),true,303); exit; }
function dinero(int $monto): string { return '$ '.number_format($monto,0,',','.'); }
function fecha(string $f): string { return (new DateTimeImmutable($f,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Argentina/Cordoba'))->format('d/m/Y H:i'); }
function get_texto(string $k,int $max=80): string { return texto($_GET,$k,$max); }
function avatar_mostrar(array $u,string $clase=''): void {
    $f=empty($u['debe_cambiar'])?avatar_actual((int)$u['id']):null;
    if($f): ?><img class="avatar-imagen <?=h($clase)?>" src="<?=h(ruta('/avatar.php').'?usuario='.$u['id'].'&v='.substr(hash('sha256',$f['archivo']),0,16))?>" alt="<?=h('Avatar de '.$u['nombre'])?>" width="30" height="30"><?php
    else: ?><span class="avatar-inicial <?=h($clase)?>" role="img" aria-label="<?=h('Inicial de '.$u['nombre'])?>"><?=h(mb_strtoupper(mb_substr($u['nombre'],0,1)))?></span><?php endif;
}
function comenzar(string $titulo,?array $u=null): void {
    $csrf=csrf();
    $perfil=$u??sesion();
    $temaPerfil=$perfil && ($perfil['tema']??'claro')==='oscuro'?'oscuro':'claro';
    ?><!doctype html><html lang="es-AR" data-tema="<?=h($temaPerfil)?>"<?=$perfil?' data-tema-perfil="1"':''?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="<?=$temaPerfil==='oscuro'?'#0B1019':'#F1F4F9'?>"><meta name="robots" content="noindex,nofollow"><meta name="app-base" content="<?=h(app_base())?>"><meta name="csrf" content="<?=h($csrf)?>"><title><?=h($titulo)?> · Mi ahorro</title><script src="<?=h(ruta("/"))?>tema.js?v=<?=h((string)@filemtime(__DIR__.'/../web/tema.js'))?>"></script><link rel="icon" href="<?=h(ruta("/"))?>icono.svg" type="image/svg+xml"><link rel="stylesheet" href="<?=h(ruta("/"))?>estilo.css?v=<?=h((string)@filemtime(__DIR__.'/../web/estilo.css'))?>"><script src="<?=h(ruta("/"))?>app.js?v=<?=h((string)@filemtime(__DIR__.'/../web/app.js'))?>" defer></script></head><body<?= $u?' data-visto="'.h(strtotime($u['visto'].' UTC')*1000).'"':''?>><a class="saltar" href="#contenido">Ir al contenido</a><div class="hoja"><header class="cabecera"><div class="marca-grupo"><a href="<?=h(ruta("/"))?>" class="marca"><img src="<?=h(ruta("/"))?>logo-oscuro.svg" alt="Family Savings" width="150" height="42"><span>Mi ahorro</span></a><?php if($perfil): ?><a class="avatar-enlace" href="<?=h(ruta('/?vista=avatar'))?>" aria-label="Cambiar avatar" title="Cambiar avatar"><?php avatar_mostrar($perfil); ?></a><?php endif; ?></div><p>Mi plata, mis planes.</p><?php if($u): ?><div class="fila"><strong>Hola, <?=h($u['nombre'])?></strong><a href="<?=h(ruta("/"))?>?vista=cuenta">Mi PIN</a><?php formulario('salir'); ?><button class="secundario" type="submit">Salir</button></form></div><?php endif; ?></header><main id="contenido">
    <?php if($u): ?><p id="aviso-sesion" class="aviso" role="alert" hidden>En un minuto se cierra tu sesión por inactividad. <button type="button" id="seguir">Seguir acá</button></p><?php endif;
}
function terminar(): void { ?><footer>Libreta familiar privada · No mueve plata ni se conecta a bancos.<br>Por seguridad, la sesión se cierra después de 10 minutos sin usarla.</footer></main></div></body></html><?php }
function formulario(string $accion,array $ocultos=[],string $destino='/',bool $multipart=false): void {
    ?><form method="post" action="<?=h(ruta($destino))?>" <?=$multipart?'enctype="multipart/form-data"':''?>><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="envio" value="<?=h(bin2hex(random_bytes(32)))?>"><input type="hidden" name="accion" value="<?=h($accion)?>"><?php foreach($ocultos as $k=>$v): ?><input type="hidden" name="<?=h($k)?>" value="<?=h($v)?>"><?php endforeach;
}
function campo(string $nombre,string $rotulo,string $tipo='text',string $valor='',bool $requerido=true,string $extra=''): void {
    // $extra sólo lo suministran literales internos, nunca datos de usuario.
    ?><label><?=h($rotulo)?><input type="<?=h($tipo)?>" name="<?=h($nombre)?>" value="<?=h($valor)?>" <?=$requerido?'required':''?> <?=$extra?>></label><?php
}
function opciones(array $lista,string $seleccion=''): void { foreach($lista as $v=>$n): ?><option value="<?=h($v)?>" <?=(string)$v===$seleccion?'selected':''?>><?=h($n)?></option><?php endforeach; }
function selector_chico(string $nombre='usuario',bool $ambos=false): void { ?><label>¿Para quién?<select name="<?=h($nombre)?>" required><option value="" selected disabled>Elegí a quién</option><?php foreach(chicos() as $u): ?><option value="<?=h($u['id'])?>"><?=h($u['nombre'])?></option><?php endforeach; ?></select></label><?php }
function pin_campos(bool $actual=true): void {
    if($actual) campo('actual','PIN actual','password','',true,'inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="current-password"');
    campo('nueva','PIN nuevo (6 números)','password','',true,'inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password"');
    campo('repetir','Repetí el PIN nuevo','password','',true,'inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password"');
    ?><p class="sutil">No uses todos los números iguales ni seguidos, como 123456.</p><?php
}
