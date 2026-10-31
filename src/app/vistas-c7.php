<?php
declare(strict_types=1);
function meta_estado_texto(string $estado): string {
    return ['activa'=>'activa','cumplida'=>'cumplida','rescate_pedido'=>'rescate pedido','rescatada'=>'rescatada','borrada'=>'meta borrada'][$estado]??$estado;
}
function metas_vista_c7(int $uid,bool $admin=false): void {
    $disponible=saldo($uid)['disponible'];
    $rows=q('SELECT m.*,u.nombre borrador,(SELECT COUNT(*) FROM '.t('rescates').' r WHERE r.meta=m.id) veces,(SELECT MAX(id) FROM '.t('rescates').' r WHERE r.meta=m.id) ultimo FROM '.t('metas').' m LEFT JOIN '.t('usuarios').' u ON u.id=m.borrado_por WHERE m.usuario=? ORDER BY m.id DESC',[$uid])->fetchAll();
    $activas=array_filter($rows,fn($m)=>$m['estado']==='activa');$historia=array_filter($rows,fn($m)=>$m['estado']!=='activa');
    $borrar=get_texto('borrar_meta',20);$borrar=$borrar===''?null:entero($borrar);$volver=$admin?'chicos':'';
    ?><section class="tarjeta metas-panel" id="metas-<?=h($uid)?>"><h2>Mis metas<?=$admin?' · '.h(nino($uid)['nombre']):''?></h2><p class="sutil">Las metas y sus rescates usan sólo la Cuenta Ahorro disponible. El efectivo no cuenta. Las metas no reservan plata hasta pedir rescate.</p><?php
    if(!$activas): ?><p>No hay metas activas.</p><?php endif;
    foreach($activas as$m): ?><article class="meta"><div class="fila entre"><h3><?=h($m['nombre'])?></h3><span class="etiqueta">activa</span></div><p><?=h(dinero($disponible))?> de <?=h(dinero((int)$m['monto']))?></p><?php progreso_meta_vista($disponible,$m);
        if($disponible>=(int)$m['monto']): formulario('meta_rescate',['id'=>$m['id'],'volver'=>$volver]); ?><button>Pedir rescate</button></form><?php
        else: ?><button type="button" disabled>Pedir rescate</button><p class="sutil">Te faltan <?=h(dinero((int)$m['monto']-$disponible))?> en la Cuenta Ahorro.</p><?php endif;
        if((int)$m['veces']===0):
            if($borrar===(int)$m['id']): ?><div class="confirmacion-meta"><p>¿Borrar esta meta? Quedará en el histórico como meta borrada. No se borra dinero.</p><?php formulario('meta_borrar',['id'=>$m['id'],'confirmar'=>'borrar','volver'=>$volver]); ?><button class="peligro">Sí, borrar meta</button></form><a class="boton secundario" href="<?=h(ruta('/').($admin?'?vista=chicos':''))?>#metas-<?=h($uid)?>">No, conservarla</a></div><?php
            else: ?><a class="boton secundario" href="<?=h(ruta('/?').http_build_query(['vista'=>$volver,'borrar_meta'=>$m['id']]))?>#metas-<?=h($uid)?>">Borrar meta</a><?php endif;
        else: ?><p class="sutil">Esta meta ya tuvo un rescate: no se puede borrar.</p><?php endif; ?></article><?php endforeach;
    ?><details class="metas-historico" <?=in_array($borrar,array_map(fn($m)=>(int)$m['id'],$historia),true)?'open':''?>><summary>Ver histórico de metas (<?=count($historia)?>)</summary><?php
    if(!$historia): ?><p>No hay metas en el histórico.</p><?php endif;
    foreach($historia as$m): ?><article class="meta"><div class="fila entre"><h3><?=h($m['nombre'])?></h3><strong><?=h(dinero((int)$m['monto']))?></strong></div><p class="etiqueta"><?=h(meta_estado_texto($m['estado']))?></p><p class="sutil">Creada: <?=h(fecha($m['fecha']))?><?php if($m['estado_fecha']): ?> · Último cambio: <?=h(fecha($m['estado_fecha']))?><?php else: ?> · Fecha del cambio anterior a C7 no registrada<?php endif; ?></p><?php
        if($m['estado']==='borrada'): ?><p class="sutil"><?=$m['borrador']?'Borró: '.h($m['borrador']):'Convertida en borrada por migración; autor original no registrado'?><?=$m['borrada']?' · '.h(fecha($m['borrada'])):''?></p><?php endif;
        if($m['estado']==='cumplida' && (int)$m['veces']===0):
            if($borrar===(int)$m['id']): ?><div class="confirmacion-meta"><p>¿Borrar esta meta cumplida? Quedará como meta borrada en el histórico.</p><?php formulario('meta_borrar',['id'=>$m['id'],'confirmar'=>'borrar','volver'=>$volver]); ?><button class="peligro">Sí, borrar meta</button></form><a href="<?=h(ruta('/').($admin?'?vista=chicos':''))?>">No, conservarla</a></div><?php
            else: ?><a class="boton secundario" href="<?=h(ruta('/?').http_build_query(['vista'=>$volver,'borrar_meta'=>$m['id']]))?>#metas-<?=h($uid)?>">Borrar meta</a><?php endif;
        endif;
        if($m['ultimo']): ?><a href="<?=h(ruta('/').($admin?'?vista=pedidos':''))?>#rescate-<?=h($m['ultimo'])?>">Ver rescate #<?=h($m['ultimo'])?></a><?php endif; ?></article><?php endforeach; ?></details>
    <details><summary>+ Crear una meta</summary><?php formulario('meta_crear',['usuario'=>$uid,'volver'=>$volver]);campo('nombre','¿Qué querés lograr?','text','',true,'maxlength="80" placeholder="Por ejemplo, una bici"');campo('monto','¿Cuántos pesos necesitás?','number','',true,'min="1" max="10000000" step="1" inputmode="numeric"'); ?><button>Crear meta</button></form></details></section><?php
}
function efectivo_vista(array $u,bool $admin=false,bool $formulario=true): void {
    $id=(int)$u['id'];$pagina=entero(get_texto('ef_pagina',6)?:'1',1,999999);$offset=($pagina-1)*20;
    $rows=q('SELECT m.*,a.nombre autor FROM '.t('efectivo_movimientos').' m JOIN '.t('usuarios').' a ON a.id=m.creado_por WHERE m.usuario=? ORDER BY m.id DESC LIMIT 21 OFFSET '.$offset,[$id])->fetchAll();
    ?><section class="tarjeta efectivo-panel" id="efectivo-<?=h($id)?>"><h2>Efectivo<?=$admin?' · '.h($u['nombre']):''?></h2><p class="cifra-fondo"><?=h(dinero(saldo_efectivo($id)))?></p><p class="sutil">Plata que tenés en tu mano. Se registra sin aprobación y no financia rescates ni metas. Si recibiste efectivo de un rescate, registrá su entrada acá para sumarlo.</p><?php
    if($formulario){formulario('efectivo',['usuario'=>$id,'volver'=>$admin?'chicos':'']); ?><label>¿Entró o salió?<select name="tipo"><?php opciones(['ingreso'=>'Entrada de efectivo','egreso'=>'Salida de efectivo']); ?></select></label><?php campo('monto','Pesos','number','',true,'min="1" max="10000000" step="1" inputmode="numeric"');campo('nota','¿Qué pasó?','text','',true,'maxlength="230"'); ?><button>Registrar efectivo</button></form><?php } ?>
    <details class="efectivo-historial" <?=$admin?'open':''?>><summary>Ver historial de efectivo</summary><?php if(!$rows): ?><p>Sin movimientos de efectivo.</p><?php endif;
    foreach(array_slice($rows,0,20)as$m): ?><article class="movimiento efectivo-movimiento"><div class="fila entre"><h3><?=h($m['nota'])?></h3><strong class="<?=h($m['tipo'])?>"><?=$m['tipo']==='ingreso'?'+':'−'?> <?=h(dinero((int)$m['monto']))?></strong></div><p class="sutil">#<?=h($m['id'])?> · <?=h(fecha($m['fecha']))?> · <?=h($m['autor'])?></p><?php if($m['inverso_de']): ?><p>Compensa el movimiento #<?=h($m['inverso_de'])?>.</p><?php endif;if($m['inverso_id']): ?><p>Anulado con inverso #<?=h($m['inverso_id'])?>.</p><?php endif;
        if($admin&&!$m['inverso_de']&&!$m['inverso_id']): ?><details><summary>Anular movimiento de efectivo</summary><?php formulario('efectivo_anular',['id'=>$m['id'],'volver'=>'chicos']);campo('nota','Motivo de la anulación','text','',true,'maxlength="230"'); ?><button class="peligro">Confirmar anulación de efectivo</button></form></details><?php endif; ?></article><?php endforeach;
    $query=['vista'=>$admin?'chicos':'','chico'=>$id]; ?><nav class="paginacion" aria-label="Páginas de efectivo"><?php if($pagina>1): ?><a href="<?=h(ruta('/?').http_build_query($query+['ef_pagina'=>$pagina-1]))?>#efectivo-<?=h($id)?>">Anteriores</a><?php endif;if(count($rows)>20): ?><a href="<?=h(ruta('/?').http_build_query($query+['ef_pagina'=>$pagina+1]))?>#efectivo-<?=h($id)?>">Más movimientos</a><?php endif; ?></nav></details></section><?php
}
function avatar_resumen_vista(array $u): void {
    ?><section class="tarjeta"><h2>Mi avatar</h2><div class="fila"><?php avatar_mostrar($u); ?><a class="boton secundario" href="<?=h(ruta('/?vista=avatar'))?>">Cambiar avatar</a></div></section><?php
}
function avatar_vista(array $u): void {
    ?><section class="tarjeta estrecha"><h1>Cambiar avatar</h1><div class="avatar-vista-previa"><?php avatar_mostrar($u,'avatar-grande'); ?></div><p>Tu imagen aparece junto a Mi ahorro. Se recorta al centro en un cuadrado y se eliminan sus metadatos de ubicación.</p><p class="sutil">JPG, PNG o WebP · hasta 5 MB y 16 megapíxeles · cinco intentos por hora.</p><?php formulario('avatar_subir',[],'/avatar.php',true); ?><label>Elegí tu imagen<input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" required></label><button>Guardar avatar</button></form><?php if(avatar_actual((int)$u['id'])){formulario('avatar_quitar',[],'/avatar.php'); ?><button class="secundario">Quitar avatar</button></form><?php } ?><a href="<?=h(ruta('/'))?>">Volver a mi libreta</a></section><?php
}
