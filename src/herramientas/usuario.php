<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../app/acciones.php';
function leer_privado(string $rotulo): string {
    fwrite(STDERR,$rotulo." (stdin; no se imprime): ");
    // En TTY ocultar incluso el eco del terminal. Por pipe, no ejecutar stty.
    $tty=function_exists('stream_isatty') && stream_isatty(STDIN);
    $estado=null;
    if ($tty) {
        if (!function_exists('shell_exec')) throw new RuntimeException('Usá entrada por pipe: no se puede ocultar el eco en este PHP.');
        $estado=shell_exec('stty -g');
        if (!is_string($estado) || !preg_match('/\A[0-9a-f:]+\s*\z/i',$estado)) throw new RuntimeException('Usá entrada por pipe.');
        shell_exec('stty -echo');
    }
    try { $v=fgets(STDIN,1001); if($v===false || strlen($v)>900) throw new RuntimeException('Entrada inválida.'); return rtrim($v,"\r\n"); }
    finally { if($estado!==null) { shell_exec('stty '.escapeshellarg(trim($estado))); fwrite(STDERR,"\n"); } }
}
try {
    $accion=$argv[1]??''; $nombre=$argv[2]??'';
    if (!in_array($accion,['alta','pin','mail','desbloquear'],true) || $nombre==='' || count($argv)>($accion==='alta'?4:3)) throw new DomainException('Uso: usuario.php alta <nombre> <admin|nino> | pin <nombre> | mail <nombre> | desbloquear <nombre|ip|hash>');
    if($accion==='desbloquear') { desbloquear($nombre); echo "OK: desbloqueo solicitado.\n"; exit; }
    if(!preg_match('/\A[\p{Latin}0-9][\p{Latin}0-9 ._-]{0,39}\z/uD',$nombre)) throw new DomainException('Nombre: 1–40 letras latinas, números, espacios, punto, guion o guion bajo.');
    if($accion==='alta') {
        $rol=$argv[3]??'';
        if(!in_array($rol,['nino','admin'],true)) throw new DomainException('Rol: nino o admin.');
        $pin=leer_privado('PIN de 6 dígitos');
        if(!pin_valido($pin)) throw new DomainException('PIN trivial o inválido.');
        $mail=leer_privado('Mail (vacío si no tiene)');
        if($mail!=='' && !mail_valido($mail)) throw new DomainException('Mail inválido.');
        q('INSERT INTO '.t('usuarios').' (nombre,rol,pin_hash,mail) VALUES (?,?,?,?)',[$nombre,$rol,hash_pin($pin),$mail!==''?$mail:null]);
    } else {
        $u=uno('SELECT id FROM '.t('usuarios').' WHERE nombre=?',[$nombre]);
        if(!$u) throw new DomainException('No se encontró el usuario.');
        if($accion==='pin') {
            $pin=leer_privado('PIN provisorio de 6 dígitos');
            transaccion(function() use($u,$pin) { uno('SELECT id FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$u['id']]); cambiar_pin((int)$u['id'],$pin,true); });
        } else {
            $mail=leer_privado('Mail (vacío para quitar)');
            if($mail!=='' && !mail_valido($mail)) throw new DomainException('Mail inválido.');
            q('UPDATE '.t('usuarios').' SET mail=? WHERE id=?',[$mail!==''?$mail:null,$u['id']]);
        }
    }
    echo "OK: operación completada.\n";
} catch(Throwable $e) {
    fwrite(STDERR,$e instanceof DomainException?$e->getMessage()."\n":"FALLA: revisá configuración, permisos, conexión o nombre duplicado. No se imprimen datos privados.\n"); exit(1);
}
