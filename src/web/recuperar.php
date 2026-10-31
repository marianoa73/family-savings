<?php
declare(strict_types=1);
require __DIR__.'/../app/http.php';
$error=''; $tok=get_texto('t',64);
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        $in=entrada(); $tok=texto($in,'token',64,true); $pin=texto($in,'nueva',6,true);
        if ($pin!==texto($in,'repetir',6,true)) throw new DomainException('Los PIN no coinciden.');
        recuperar_pin($tok,$pin); cerrar(null); ir('/?mensaje=pin');
    } catch(DomainException $e) { $error=$e->getMessage(); http_response_code(400); }
}
$valido=preg_match('/\A[a-f0-9]{64}\z/D',$tok)?uno('SELECT r.usuario FROM '.t('recuperaciones').' r JOIN '.t('usuarios').' u ON u.id=r.usuario WHERE r.token_hash=? AND r.usado IS NULL AND r.vence>? AND u.activo=1',[hash('sha256',$tok),ahora()]):null;
comenzar('Recuperar PIN');
?><section class="tarjeta estrecha"><h1>Tu PIN nuevo</h1><?php if($error): ?><p class="error" role="alert"><?=h($error)?></p><?php endif; if(!$valido): ?><p>Este enlace venció o ya se usó. <a href="<?=h(ruta("/"))?>?vista=recuperar">Pedí otro enlace</a>.</p><?php else: formulario('restablecer',['token'=>$tok],'/recuperar.php'); pin_campos(false); ?><button>Guardar PIN nuevo</button></form><?php endif; ?></section><?php terminar();
