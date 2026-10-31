<?php
declare(strict_types=1);
require __DIR__.'/../app/http.php';
require __DIR__.'/../app/vistas.php';
$error=''; $nombre_reintento='';$fotoPreparada=null; $u=sesion();
$vista=get_texto('vista',20);
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        $in=entrada(); $accion=texto($in,'accion',40,true);
        if(($_FILES??[])!==[]){if(!$u){http_response_code(401);throw new DomainException('Ingresá para adjuntar una foto.');}$fotoPreparada=nota_foto_preparar($u,$in,$_FILES);if($fotoPreparada)$in['_foto_preparada']=$fotoPreparada;}
        if ($accion==='ingresar') {
            if ($u) ir('/');
            $nombre_reintento=texto($in,'nombre',40,true);
            $id=autenticar($nombre_reintento,texto($in,'pin',6,true),true);
            if ($id===null) { http_response_code(429); header('Retry-After: 900'); $error='No pudimos ingresar. Revisá el nombre y el PIN o esperá unos minutos.'; }
            else ir('/');
        } elseif ($accion==='recuperar') {
            pedir_recuperacion(texto($in,'nombre',40,true)); ir('/?mensaje=recuperacion');
        } elseif ($accion==='salir') { cerrar($u); ir('/?mensaje=salida'); }
        else {
            if (!$u) { http_response_code(401); throw new DomainException('Tu sesión terminó. Volvé a ingresar.'); }
            if(in_array($accion,['categoria_estado','ingreso','cancelar_ingreso','aprobar_movimiento','rechazar_movimiento','nota_corregir','rescate','cancelar','rapida','confirmar_gasto','rechazar_gasto','aprobar','rechazar','entregar','anular','meta_rescate','meta_borrar','efectivo','efectivo_anular'],true)) {
                operar_una_vez($u,$in);
            } else operar($u,$in);
            $fotoPreparada=null; // La operación ya confirmó su referencia; no borrar ante un error posterior.
            sesion(true);
            $volver=texto($in,'volver',20);
            if (!in_array($volver,['historial','chicos','seguridad','config','usuarios','accesos','cuenta','metas','efectivo','avatar'],true)) $volver='';
            ir('/?mensaje=guardado'.($volver!==''?'&vista='.$volver:''));
        }
    } catch(DomainException $e) { if($fotoPreparada)unlink(nota_foto_archivo($fotoPreparada['archivo']));$error=$e->getMessage(); if(http_response_code()<400) http_response_code(400); } catch(Throwable $e){if($fotoPreparada)unlink(nota_foto_archivo($fotoPreparada['archivo']));throw $e;}
}
if($u && $u['rol']==='admin' && ($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $alias=['pedidos'=>['historial','retiros'],'metas'=>['chicos',''],'efectivo'=>['chicos',''],'usuarios'=>['seguridad','usuarios'],'accesos'=>['seguridad','accesos']];
    if(isset($alias[$vista])){[$nueva,$seccion]=$alias[$vista];$params=$_GET;$params['vista']=$nueva;if($seccion!=='')$params[$vista==='pedidos'?'registro':'seccion']=$seccion;header('Location: '.ruta('/?').http_build_query($params),true,302);exit;}
}
if ($u) $u=sesion(true); // Navegar es operar, las consultas de fondo no renuevan.
if($u&&$u['rol']==='admin'&&!$u['debe_cambiar'])nota_fotos_reintentar((int)$u['id'],3);
comenzar($u?'Mi libreta':'Entrá a tu libreta',$u);
if ($error!==''): ?><p class="error" role="alert"><?=h($error)?></p><?php endif;
$mensajes=['guardado'=>'Listo, se guardó.','salida'=>'Cerraste tu sesión.','recuperacion'=>'Si tu usuario tiene un mail cargado, te mandamos un enlace; revisá también correo no deseado','pin'=>'Cambiaste tu PIN. Ya podés ingresar.','inactividad'=>'Cerramos tu sesión por seguridad. Volvé a ingresar.'];
$mensaje=get_texto('mensaje',20); if(isset($mensajes[$mensaje])): ?><p class="ok" role="status"><?=h($mensajes[$mensaje])?></p><?php endif;
if (!$u) {
    $nombre=$nombre_reintento!==''?$nombre_reintento:get_texto('nombre',40);
    $activos=q('SELECT nombre FROM '.t('usuarios').' WHERE activo=1 ORDER BY rol<>\'nino\', CASE WHEN rol=\'nino\' THEN id ELSE -id END')->fetchAll(); // 22/10: chicos arriba (por alta), adultos abajo (el último dado de alta primero)
    if ($vista==='recuperar') {
        ?><section class="tarjeta estrecha"><h1>¿Olvidaste tu PIN?</h1><p>Elegí tu nombre. Si tiene un mail cargado, te mandamos un enlace para elegir un PIN nuevo.</p><?php formulario('recuperar'); ?><label>Tu nombre<select name="nombre" required><?php foreach($activos as $a): ?><option value="<?=h($a['nombre'])?>"><?=h($a['nombre'])?></option><?php endforeach; ?></select></label><button>Mandar enlace</button></form><p>Si necesitás ayuda, pedísela a un adulto.</p><a href="<?=h(ruta("/"))?>">Volver al ingreso</a></section><?php
    } elseif ($nombre!=='') {
        ?><section class="tarjeta estrecha"><h1>Hola, <?=h($nombre)?></h1><p>Ingresá tu PIN de 6 números.</p><?php formulario('ingresar',['nombre'=>$nombre]); ?><label>Tu PIN<input id="pin" name="pin" type="password" pattern="[0-9]{6}" inputmode="numeric" minlength="6" maxlength="6" autocomplete="current-password" required autofocus></label><div class="teclado" aria-label="Teclado numérico"><?php foreach(['1','2','3','4','5','6','7','8','9','borrar','0','ver'] as $tecla): ?><button type="button" class="secundario" data-tecla="<?=h($tecla)?>" aria-label="<?=h($tecla==='ver'?'Mostrar u ocultar PIN':($tecla==='borrar'?'Borrar último número':$tecla))?>"><?=h($tecla==='borrar'?'Borrar':($tecla==='ver'?'Ver':$tecla))?></button><?php endforeach; ?></div><button class="ancho">Entrar</button></form><p><a href="<?=h(ruta("/"))?>?vista=recuperar">Olvidé mi PIN</a></p><a href="<?=h(ruta("/"))?>">No soy <?=h($nombre)?>, cambiar nombre</a></section><?php
    } else {
        ?><div class="ingreso-inicio"><section class="bienvenida"><p class="ceja">TU LIBRETA FAMILIAR</p><h1>Un poquito hoy.<br>Un gran plan mañana.</h1><p>Seguí tus ahorros, anotá lo que gastás y acercate a tus metas.</p></section><section class="tarjeta"><h2>¿Quién sos?</h2><p class="sutil">Tocá tu nombre para entrar con tu PIN.</p><div class="grilla nombres"><?php foreach($activos as $a): ?><a class="boton usuario" href="<?=h(ruta("/"))?>?nombre=<?=rawurlencode($a['nombre'])?>"><?=h($a['nombre'])?><span aria-hidden="true">→</span></a><?php endforeach; ?></div><?php if(!$activos): ?><p>Todavía no hay usuarios. Un adulto los crea con la herramienta privada de instalación.</p><?php endif; ?><p><a href="<?=h(ruta("/"))?>?vista=recuperar">Olvidé mi PIN</a></p></section></div><?php
    }
} elseif ($u['debe_cambiar'] || $vista==='cuenta') {
    ?><section class="tarjeta estrecha"><h1><?=$u['debe_cambiar']?'Elegí tu propio PIN':'Cambiar mi PIN'?></h1><p><?=$u['debe_cambiar']?'El que te dieron es provisorio. Antes de usar tu libreta, cambialo por uno que sólo vos sepas.':'No compartas tu PIN.'?></p><?php formulario('pin',['volver'=>'cuenta']); pin_campos(); ?><button>Guardar mi PIN</button></form><?php if(!$u['debe_cambiar']): ?><p><a href="<?=h(ruta("/"))?>">Volver a mi libreta</a></p><?php endif; ?></section><?php
} elseif ($vista==='avatar') avatar_vista($u);
elseif ($u['rol']==='admin') vista_admin($u,$vista);
else vista_nino($u);
terminar();
