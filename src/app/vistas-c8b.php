<?php
declare(strict_types=1);
function categoria_editor_c8b(array $c): void {
    ?><details><summary>Editar categoría</summary><?php formulario('categoria',['id'=>$c['id'],'volver'=>'config']);campo('nombre','Nombre','text',$c['nombre'],true,'maxlength="100"');campo('icono','Ícono (emoji)','text',$c['icono'],true,'maxlength="20"');?><label>Tipo<select name="tipo"><?php opciones(['ingreso'=>'Ingreso','egreso'=>'Egreso'],$c['tipo']);?></select></label><?php campo('monto','Monto sugerido común','number',(string)$c['sugerido'],true,'min="0" max="10000000"');campo('orden','Orden','number',(string)$c['orden'],true,'min="0" max="9999"');foreach(chicos()as$n){$v=q('SELECT monto FROM '.t('sugerencias').' WHERE usuario=? AND categoria=?',[$n['id'],$c['id']])->fetchColumn();campo('sugerido_'.$n['id'],'Sugerido para '.$n['nombre'].' (opcional)','number',$v===false?'':(string)$v,false,'min="0" max="10000000"');}?><p class="sutil">Grupo: <?=h($c['grupo'])?>. Nota, mensualidad e interés usan su configuración específica. Vacío usa el monto común.</p><button>Guardar categoría</button></form></details><?php
}
function categoria_fila_c8b(array $c): void {
    ?><article class="movimiento categoria-fila" id="categoria-<?=h($c['id'])?>"><h3><?=h($c['icono'].' '.$c['nombre'])?></h3><?php
    $ev=uno('SELECT e.*,u.nombre FROM '.t('categorias_eventos').' e JOIN '.t('usuarios').' u ON u.id=e.actor WHERE e.categoria=? ORDER BY e.id DESC LIMIT 1',[$c['id']]);
    if($ev):?><p class="sutil"><?=h(($ev['activa']?'Restaurada':'Quitada').' por '.$ev['nombre'].' el '.fecha($ev['fecha']))?></p><?php endif;
    if($c['activa']){
        if(get_texto('quitar',20)===(string)$c['id']):?><div class="confirmacion-meta"><p>¿Quitar <?=h($c['nombre'])?> de los formularios? El historial se conserva y podés volver a mostrarla.</p><?php formulario('categoria_estado',['id'=>$c['id'],'mostrar'=>'0','confirmar'=>'quitar','volver'=>'config']);?><button class="peligro">Sí, quitar</button></form><a class="boton secundario" href="<?=h(ruta('/?vista=config'))?>#categoria-<?=h($c['id'])?>">Conservar</a></div><?php
        else:?><a class="boton secundario" href="<?=h(ruta('/?vista=config&quitar='.$c['id']))?>#categoria-<?=h($c['id'])?>">Quitar</a><?php endif;categoria_editor_c8b($c);
    }else{formulario('categoria_estado',['id'=>$c['id'],'mostrar'=>'1','volver'=>'config']);?><button class="secundario">Volver a mostrar</button></form><?php }
    $events=q('SELECT e.*,u.nombre FROM '.t('categorias_eventos').' e JOIN '.t('usuarios').' u ON u.id=e.actor WHERE e.categoria=? ORDER BY e.id DESC LIMIT 20',[$c['id']])->fetchAll();if($events):?><details><summary>Últimos cambios</summary><?php foreach($events as$e):?><p><?=h(($e['activa']?'Volvió a mostrarse':'Quitada').' por '.$e['nombre'].' · '.fecha($e['fecha']))?></p><?php endforeach;?></details><?php endif;?></article><?php
}
function categorias_vista_c8b(): void {
    $rows=q('SELECT * FROM '.t('categorias').' ORDER BY orden,id')->fetchAll();$off=array_filter($rows,fn($c)=>!$c['activa']);
    ?><section class="tarjeta" id="categorias"><h2>Categorías y tareas</h2><p>Quitar esconde la opción; no borra movimientos. Los cambios quedan registrados.</p><?php foreach(['ingreso'=>'Ingresos','tarea'=>'Tareas de la casa','admin'=>'Operaciones del adulto','gasto'=>'Gastos/motivos']as$grupo=>$titulo):$active=array_filter($rows,fn($c)=>$c['activa']&&$c['grupo']===$grupo);?><details class="categorias-grupo"><summary><?=h($titulo)?> (<?=count($active)?>)</summary><?php foreach($active as$c)categoria_fila_c8b($c);if(!$active):?><p>Sin categorías activas.</p><?php endif;?></details><?php endforeach;?><details id="categorias-desactivadas"><summary>Categorías desactivadas (<?=count($off)?>)</summary><?php foreach($off as$c)categoria_fila_c8b($c);if(!$off):?><p>Ninguna desactivada.</p><?php endif;?></details></section><?php
}
function cargar_vista_c8b(array $u): void {
    $modo=get_texto('cargar',10);if(get_texto('ingreso_cat',20)!=='')$modo='fondo';
    ?><section class="tarjeta" id="cargar"><h2>Cargar Notas Colegio, Tareas, Dinero</h2><p>¿Es de la Cuenta Ahorro o es efectivo mío?</p><div class="chips"><a class="chip <?=$modo==='fondo'?'elegido':''?>" href="<?=h(ruta('/?cargar=fondo'))?>#cargar">Cuenta Ahorro</a><a class="chip <?=$modo==='efectivo'?'elegido':''?>" href="<?=h(ruta('/?cargar=efectivo'))?>#cargar">Efectivo mío</a></div><?php
    if($modo==='fondo')ingreso_nino_vista($u);
    elseif($modo==='efectivo'){
        formulario('efectivo',['usuario'=>$u['id']],'/?cargar=efectivo#cargar');?><p>Se registra sin aprobación de un adulto.</p><label>¿Entró o salió?<select name="tipo"><?php opciones(['ingreso'=>'Entrada de efectivo','egreso'=>'Salida de efectivo']);?></select></label><?php campo('monto','Pesos','number','',true,'min="1" max="10000000" inputmode="numeric"');campo('nota','¿Qué pasó?','text','',true,'maxlength="230"');?><button>Registrar efectivo</button></form><?php
    }?></section><?php
}
function historial_c8b(array $u): void {
    $tipo=get_texto('registro',15);?><section class="tarjeta"><h1>Historial familiar</h1><form method="get"><input type="hidden" name="vista" value="historial"><label>Tipo de registro<select name="registro"><?php opciones([''=>'Todos','movimientos'=>'Ingresos y egresos','retiros'=>'Retiros'],$tipo);?></select></label><button>Ver historial</button></form></section><?php
    if($tipo!=='retiros')movimientos_vista($u,true);
    if($tipo!=='movimientos')rescates_vista(null,true);
}
