<?php
declare(strict_types=1);
require_once __DIR__.'/vistas-c7.php';
require_once __DIR__.'/vistas-c8.php';
require_once __DIR__.'/vistas-c8b.php';
// Fragmento de autoría local: construirlo en vistas.php, no servir por HTTP.
function datos_grafico_mes(int $id): array {
    $tz=new DateTimeZone('America/Argentina/Cordoba'); [$desde,$hasta]=mes_limites();
    // Un original anulado permanece junto a su inverso confirmado: se compensan.
    $inicio=(int)q('SELECT COALESCE(SUM(CASE WHEN tipo=\'ingreso\' THEN monto ELSE -monto END),0) FROM '.t('movimientos')." WHERE usuario=? AND estado IN ('confirmado','anulado') AND confirmado<?",[$id,$desde])->fetchColumn();
    $neto=[];
    foreach(q('SELECT tipo,monto,confirmado FROM '.t('movimientos')." WHERE usuario=? AND estado IN ('confirmado','anulado') AND confirmado>=? AND confirmado<? ORDER BY confirmado,id",[$id,$desde,$hasta])as$r){
        $dia=(int)(new DateTimeImmutable($r['confirmado'],new DateTimeZone('UTC')))->setTimezone($tz)->format('j');
        $neto[$dia]=($neto[$dia]??0)+($r['tipo']==='ingreso'?1:-1)*(int)$r['monto'];
    }
    ksort($neto);$hoy=(int)(new DateTimeImmutable('now',$tz))->format('j');$saldo=$inicio;$serie=[];
    for($d=1;$d<=$hoy;$d++){$saldo+=$neto[$d]??0;$serie[$d]=$saldo;}
    return ['inicio'=>$inicio,'cierre'=>$saldo,'neto'=>$neto,'serie'=>$serie,'hoy'=>$hoy];
}
function variacion_mes(array $datos): string {
    if ($datos['inicio']<=0) return 'Nuevo este mes';
    $delta=$datos['cierre']-$datos['inicio'];$porcentaje=$delta/$datos['inicio']*100;
    return ($delta>0?'+':'').number_format($porcentaje,1,',','.').' % este mes';
}
function grafico_mes(int $id,bool $mini=false,?array $datos=null): void {
    $g=$datos??datos_grafico_mes($id);$serie=$g['serie'];$hoy=$g['hoy'];$neto=$g['neto'];
    $lo=min($g['inicio'],...array_values($serie));$hi=max($g['inicio'],...array_values($serie));$margen=max(1,($hi-$lo)*.12);
    $min=(int)floor($lo-$margen);if($lo>=0)$min=max(0,$min);$max=(int)ceil($hi+$margen);if($max===$min)$max=$min+1;
    ?><figure class="grafico <?=$mini?'grafico-mini':''?>"><figcaption>Cuenta Ahorro día por día</figcaption><?php
    foreach($mini?[[320,90,'grafico-unico']]:[[760,240,'grafico-pc'],[320,210,'grafico-movil']]as[$W,$H,$clase]){
        $iz=10;$de=$mini?10:62;$ar=$mini?8:24;$ab=$mini?8:30;
        $x=fn(int $d)=>$iz+($hoy>1?($d-1)*($W-$iz-$de)/($hoy-1):($W-$iz-$de)/2);
        $y=fn(int $v)=>$ar+($max-$v)*($H-$ar-$ab)/($max-$min);
        $pts=implode(' ',array_map(fn($d)=>round($x($d),1).','.round($y($serie[$d]),1),array_keys($serie)));
        ?><svg class="<?=h($clase)?>" viewBox="0 0 <?=$W?> <?=$H?>" role="img" aria-label="<?=h('Cuenta Ahorro registrada: empezó en '.dinero($g['inicio']).' y hoy está en '.dinero($g['cierre']).'. No resta reservas.')?>"><?php if(!$mini): foreach([0,.5,1]as$f):$val=(int)round($min+($max-$min)*$f); ?><line class="eje" x1="<?=$iz?>" x2="<?=$W-$de?>" y1="<?=round($y($val),1)?>" y2="<?=round($y($val),1)?>"/><text x="<?=$W-$de+7?>" y="<?=round($y($val)+4,1)?>"><?=h(number_format($val,0,',','.'))?></text><?php endforeach; endif; ?><polygon class="area" points="<?=$iz?>,<?=$H-$ab?> <?=$pts?> <?=$W-$de?>,<?=$H-$ab?>"/><polyline class="linea" points="<?=$pts?>"/><?php foreach($neto as$d=>$n):if($d>$hoy)continue; ?><circle class="punto <?=$n>=0?'sube':'baja'?>" cx="<?=round($x($d),1)?>" cy="<?=round($y($serie[$d]),1)?>" r="<?=$mini?3:4?>"><title><?=h('Día '.$d.': '.($n>=0?'+ ':'− ').dinero(abs($n)).' · saldo '.dinero($serie[$d]))?></title></circle><?php endforeach; if(!$mini): ?><text x="<?=$iz?>" y="<?=$H-7?>">1 de mes</text><text x="<?=$W-$de?>" y="<?=$H-7?>" text-anchor="end">hoy (<?=$hoy?>)</text><?php endif; ?></svg><?php
    }
    if($neto): ?><details><summary>Ver día por día</summary><table><caption class="sr-only">Variación diaria del saldo registrado</caption><thead><tr><th scope="col">Día</th><th scope="col">Movió</th><th scope="col">Saldo</th></tr></thead><tbody><?php foreach($neto as$d=>$n):if($d>$hoy)continue; ?><tr><td><?=$d?></td><td class="<?=$n>=0?'ingreso':'egreso'?>"><?=($n>=0?'+ ':'− ').h(dinero(abs($n)))?></td><td><?=h(dinero($serie[$d]))?></td></tr><?php endforeach; ?></tbody></table></details><?php else: ?><p class="sutil">Este mes todavía no hubo movimientos aprobados.</p><?php endif; ?></figure><?php
}
function cuadro_saldo(array $u,bool $compacto=false): void {
    $id=(int)$u['id'];$s=saldo($id);$m=resumen_mes($id);$g=datos_grafico_mes($id);$cuentas=saldos_cuenta($id);$delta=$g['cierre']-$g['inicio'];
    ?><section class="saldo"><div class="saldo-identidad"><?php if($compacto)avatar_mostrar($u); ?><p class="ceja"><?=h($u['nombre'])?> · Total disponible</p></div><h2><?=h(dinero($cuentas['total']))?> <small>ARS</small></h2><p class="variacion <?=$delta<0?'egreso':'ingreso'?>" title="Variación de la Cuenta Ahorro registrada, sin efectivo ni reservas."><?=h(variacion_mes($g).' · Cuenta Ahorro')?></p><div class="cuentas-parciales"><div><span>Cuenta Ahorro</span><strong><?=h(dinero($cuentas['fondo']))?></strong><small>la administran los admin</small></div><div><span>Efectivo</span><strong><?=h(dinero($cuentas['efectivo']))?></strong><small>en tu mano</small><?php if($compacto): ?><a href="<?=h(ruta('/?vista=efectivo&chico='.$id))?>">Ver efectivo</a><?php endif; ?></div></div><p class="sutil">Total = Cuenta Ahorro disponible + Efectivo. Lo reservado se muestra aparte.</p><div class="datos-saldo"><span>+ <?=h(dinero($s['ingresos_pendientes']))?><small>por aprobar</small></span><span>− <?=h(dinero($s['gastos_pendientes']))?><small>gastos a confirmar</small></span><span><?=h(dinero($s['reservado']))?><small>reservado para rescates</small></span></div></section><section class="tarjeta terminal-mes"><h2>Cuenta Ahorro · Este mes</h2><div class="metricas"><div><span>Entró</span><strong class="ingreso">+ <?=h(dinero($m['ingresos']))?></strong></div><div><span>Salió</span><strong class="egreso">− <?=h(dinero($m['egresos']))?></strong></div><div><span>Ahorré</span><strong><?=h(dinero($m['ahorro']))?></strong></div></div><?php grafico_mes($id,$compacto,$g); ?><p class="sutil">Saldo registrado: <?=h(dinero($g['cierre']))?>. Los pendientes no cuentan; las reservas se muestran aparte.</p></section><?php
}

function progreso_meta_vista(int $disponible,array $meta): void {
    $objetivo=(int)$meta['monto'];
    $porcentaje=min(100,max(0,(int)floor($disponible*100/$objetivo)));
    ?><progress max="100" value="<?=$porcentaje?>" aria-label="<?=h('Progreso de '.$meta['nombre'])?>"><?=$porcentaje?> %</progress><?php
    if ($disponible>=$objetivo): ?><p class="ok">¡Llegaste! 🎉</p><?php
    else: ?><p class="sutil">Te faltan $ <?=h(number_format($objetivo-$disponible,0,',','.'))?> para llegar.</p><?php endif;
}
function metas_vista(int $uid,bool $admin=false): void { metas_vista_c7($uid,$admin); }
function movimientos_vista(array $u,bool $admin=false): void {
    $where=['1=1']; $params=[];
    $filtro=get_texto('chico',20);
    if(!$admin) { $where[]='m.usuario=?'; $params[]=$u['id']; }
    elseif($filtro!=='') { $where[]='m.usuario=?'; $params[]=entero($filtro); }
    $mes=get_texto('mes',7); if($mes!=='') { [$desde,$hasta]=mes_limites($mes); $where[]='m.fecha>=? AND m.fecha<?'; array_push($params,$desde,$hasta); }
    $tipo=get_texto('tipo',10); $estado=get_texto('estado',15); $cid=get_texto('categoria',20);
    if($tipo!=='' && in_array($tipo,['ingreso','egreso'],true)) { $where[]='m.tipo=?'; $params[]=$tipo; }
    if($estado!=='' && in_array($estado,['pendiente','confirmado','rechazado','anulado'],true)) { $where[]='m.estado=?'; $params[]=$estado; }
    if($cid!=='') { $where[]='m.categoria=?'; $params[]=entero($cid); }
    $w=implode(' AND ',$where); $pagina=entero(get_texto('pagina',6)?:'1',1,999999); $offset=($pagina-1)*20;
    $total=(int)q('SELECT COUNT(*) FROM '.t('movimientos').' m WHERE '.$w,$params)->fetchColumn();
    $rows=q('SELECT m.*,(SELECT f.delta FROM '.t('fondo_movimientos').' f WHERE f.movimiento=m.id) fondo_delta,u.nombre,creador.nombre cargador,conf.nombre confirmador,anul.nombre anulador FROM '.t('movimientos').' m JOIN '.t('usuarios').' u ON u.id=m.usuario JOIN '.t('usuarios').' creador ON creador.id=m.creado_por LEFT JOIN '.t('usuarios').' conf ON conf.id=m.confirmado_por LEFT JOIN '.t('usuarios').' anul ON anul.id=m.anulado_por WHERE '.$w.' ORDER BY m.id DESC LIMIT 20 OFFSET '.$offset,$params)->fetchAll();
    ?><section class="tarjeta terminal-historial" id="movimientos"><h2><?=$admin?'Historial completo':'Mis movimientos'?></h2><?php if($admin): ?><form method="get" class="filtros"><input type="hidden" name="vista" value="historial"><input type="hidden" name="registro" value="<?=h(get_texto('registro',15))?>"><label>Usuario<select name="chico"><option value="">Todos</option><?php foreach(q('SELECT id,nombre FROM '.t('usuarios')." WHERE rol='nino' ORDER BY nombre") as $n): opciones([$n['id']=>$n['nombre']],$filtro); endforeach; ?></select></label><?php campo('mes','Mes','month',$mes,false); ?><label>Tipo<select name="tipo"><?php opciones([''=>'Todos','ingreso'=>'Ingreso','egreso'=>'Egreso'],$tipo); ?></select></label><label>Categoría<select name="categoria"><option value="">Todas</option><?php foreach(q('SELECT id,nombre FROM '.t('categorias').' ORDER BY orden,nombre') as $c) opciones([$c['id']=>$c['nombre']],$cid); ?></select></label><label>Estado<select name="estado"><?php opciones([''=>'Todos','confirmado'=>'Confirmado','pendiente'=>'Pendiente','rechazado'=>'Rechazado','anulado'=>'Anulado'],$estado); ?></select></label><button>Filtrar</button></form><?php endif;
    if(!$rows): ?><p>Todavía no hay movimientos para mostrar.</p><?php endif;
    if($rows): ?><table class="watchlist"><caption class="sr-only">Movimientos con su importe y estado</caption><thead><tr><th scope="col">Movimiento y detalle</th><th scope="col">Importe</th><th scope="col">Estado</th></tr></thead><tbody><?php foreach($rows as $m): ?><tr><td><article class="movimiento"><div class="fila entre"><h3><span aria-hidden="true"><?=h($m['icono'])?></span> <?=h($m['concepto'])?></h3></div><p class="sutil">#<?=h($m['id'])?> · <?=h(fecha($m['fecha']))?><?=$admin?' · '.h($m['nombre']):''?><?php if($m['estado']!=='confirmado'): ?> · <b><?=h(estado_movimiento($m))?></b><?php endif; ?></p><?php nota_detalle_vista($m);if($admin)nota_corregir_vista($m);if($m['nota']!==''): ?><p><?=h($m['nota'])?></p><?php endif; if(isset($m['fondo_delta'])): ?><p class="etiqueta"><?=h($m['inverso_de']?'Reversión del Fondo':((int)$m['fondo_delta']<0?'Pagado con el Fondo':'Transferido al Fondo'))?></p><?php endif; if($m['inverso_id']): ?><p>Compensado por el movimiento #<?=h($m['inverso_id'])?>. <?=h($m['motivo_anulacion'])?></p><?php endif; if($m['inverso_de']): ?><p>Compensa el movimiento #<?=h($m['inverso_de'])?>.</p><?php endif; if($admin): ?><details><summary>Quién lo registró</summary><p>Cargó: <?=h($m['cargador'])?>. Revisó: <?=h($m['confirmador']??'Pendiente')?><?=$m['confirmado']?' · '.h(fecha($m['confirmado'])):''?>.<?php if($m['anulador']): ?> Anuló: <?=h($m['anulador'])?> · <?=h(fecha($m['anulado']))?>.<?php endif; ?></p></details><?php if($m['estado']==='confirmado' && !$m['inverso_de']): ?><details><summary>Anular este movimiento</summary><?php formulario('anular',['id'=>$m['id'],'volver'=>'historial']); campo('motivo','¿Por qué lo anulás?','text','',true,'maxlength="300"'); ?><p class="sutil">Se conserva el original y se agrega el inverso. No se borra nada.</p><button class="peligro" data-confirmar="¿Querés anular este movimiento y crear su inverso?">Confirmar anulación</button></form></details><?php endif; endif; if(!$admin && $m['tipo']==='ingreso' && $m['estado']==='pendiente' && (int)$m['usuario']===(int)$u['id'] && (int)$m['creado_por']===(int)$u['id']): formulario('cancelar_ingreso',['id'=>$m['id']]); ?><button class="secundario" data-confirmar="¿Querés cancelar este ingreso pendiente?">Cancelar ingreso</button></form><?php endif; ?></article></td><td class="importe <?=h($m['tipo'])?>"><?=$m['tipo']==='ingreso'?'+':'−'?> <?=h(dinero((int)$m['monto']))?></td><td><span class="etiqueta estado"><?=h(estado_movimiento($m))?></span></td></tr><?php endforeach; ?></tbody></table><?php endif;
    $base=['vista'=>$admin?'historial':'','registro'=>get_texto('registro',15),'chico'=>$filtro,'mes'=>$mes,'tipo'=>$tipo,'estado'=>$estado,'categoria'=>$cid];
    ?><nav class="paginacion" aria-label="Páginas de movimientos"><?php if($pagina>1): ?><a class="boton secundario" href="<?=h(ruta("/"))?>?<?=h(http_build_query($base+['pagina'=>$pagina-1]))?>">← Anteriores</a><?php endif; ?><span>Página <?=$pagina?> · <?=$total?> movimientos</span><?php if($offset+20<$total): ?><a class="boton secundario" href="<?=h(ruta("/"))?>?<?=h(http_build_query($base+['pagina'=>$pagina+1]))?>">Más movimientos →</a><?php endif; ?></nav></section><?php
}
function rescate_articulo(array $r,bool $admin): void { ?><article class="movimiento" id="rescate-<?=h($r['id'])?>"><?php if($admin): ?><p class="ceja">Retiro de Cuenta Ahorro · <?=$r['estado']==='aprobado'?'Para entregar':h($r['estado'])?></p><?php endif; ?><div class="fila entre"><h3><?=h($r['motivo'])?></h3><strong><?=h(dinero((int)$r['monto']))?></strong></div><p>#<?=h($r['id'])?> · <?=h($r['estado'])?><?=$admin?' · '.h($r['nombre']):''?> · <?=h(fecha($r['fecha']))?></p><?php if($r['nota']): ?><p><?=h($r['nota'])?></p><?php endif; if($r['medio']): ?><p>Entregado: <?=h($r['medio'])?>.</p><?php endif; if($admin): ?><p class="sutil">Cargó: <?=h($r['nombre'])?>. Aprobó: <?=h($r['aprobador']??'Pendiente')?><?=$r['aprobado']?' · '.h(fecha($r['aprobado'])):''?>. Última decisión: <?=h($r['decisor']??'Pendiente')?><?=$r['decidido']?' · '.h(fecha($r['decidido'])):''?>. Entregó: <?=h($r['entrega']??'Pendiente')?><?=$r['entregado']?' · '.h(fecha($r['entregado'])):''?>.</p><?php if(in_array($r['estado'],['pendiente','aprobado'],true)): formulario('entregar',['id'=>$r['id']]); ?><label>¿Cómo lo entregaste?<select name="medio" required><?php opciones(['Transferencia a su cuenta'=>'Transferencia a su cuenta','Efectivo'=>'Efectivo']); ?></select></label><button>Entregar</button></form><?php formulario('rechazar',['id'=>$r['id']]); ?><button class="secundario">Rechazar</button></form><?php endif; elseif($r['estado']==='pendiente'): formulario('cancelar',['id'=>$r['id']]); ?><button class="secundario">Cancelar pedido</button></form><?php endif; ?></article><?php }
function rescates_vista(?int $uid=null,bool $historial=false): void {
    $admin=$uid===null;
    $pagina=entero(get_texto('pedido_pagina',6)?:'1',1,999999); $offset=($pagina-1)*20;
    $cond=[];$params=[];
    if(!$admin){$cond[]='r.usuario=?';$params[]=$uid;}elseif(!$historial)$cond[]="r.estado IN ('pendiente','aprobado')";
    if($admin&&get_texto('chico',20)!==''){$cond[]='r.usuario=?';$params[]=entero(get_texto('chico',20));}
    if($admin&&get_texto('mes',7)!==''){[$desde,$hasta]=mes_limites(get_texto('mes',7));$cond[]='r.fecha>=? AND r.fecha<?';array_push($params,$desde,$hasta);}
    $where=$cond?' WHERE '.implode(' AND ',$cond):'';
    $rows=q('SELECT r.*,u.nombre,d.nombre decisor,e.nombre entrega,a.nombre aprobador FROM '.t('rescates').' r JOIN '.t('usuarios').' u ON u.id=r.usuario LEFT JOIN '.t('usuarios').' d ON d.id=r.decidido_por LEFT JOIN '.t('usuarios').' e ON e.id=r.entregado_por LEFT JOIN '.t('usuarios').' a ON a.id=r.aprobado_por'.$where.' ORDER BY r.id DESC LIMIT 21 OFFSET '.$offset,$params)->fetchAll();
    $hayMas=count($rows)>20; $rows=array_slice($rows,0,20);
    ?><section class="tarjeta"><h2><?=$admin?($historial?'Todos los rescates':'Rescates por resolver'):'Mis pedidos de rescate'?></h2><?php if(!$rows): ?><p>No hay pedidos para mostrar.</p><?php endif; foreach($rows as $r)rescate_articulo($r,$admin); ?><nav class="paginacion" aria-label="Páginas de rescates"><?php if($pagina>1): ?><a href="<?=h(ruta("/"))?>?vista=<?=$admin&&$historial?'historial':''?>&amp;registro=<?=h(get_texto('registro',15))?>&amp;pedido_pagina=<?=$pagina-1?>">← Anteriores</a><?php endif; if($hayMas): ?><a href="<?=h(ruta("/"))?>?vista=<?=$admin&&$historial?'historial':''?>&amp;registro=<?=h(get_texto('registro',15))?>&amp;pedido_pagina=<?=$pagina+1?>">Más pedidos →</a><?php endif; ?></nav><p class="sutil">Pedir reserva la Cuenta Ahorro; entregar registra la salida una vez. El efectivo que conserves en tu mano se registra aparte.</p></section><?php
}
function ingreso_nino_vista(array $u): void {
    $categorias=array_values(array_filter(q('SELECT * FROM '.t('categorias')." WHERE activa=1 AND tipo='ingreso' AND grupo IN ('ingreso','tarea') ORDER BY orden,id")->fetchAll(),'ingreso_chico_permitido'));
    $valor=get_texto('ingreso_cat',20);$elegida=$valor===''?null:entero($valor);$cat=null;foreach($categorias as$c)if((int)$c['id']===$elegida)$cat=$c;
    ?><section class="tarjeta" id="cargar-ingreso"><h2>Cargar un ingreso a Cuenta Ahorro</h2><p>Un adulto lo revisa antes de sumarlo. Si tenés la plata en la mano, usá Efectivo.</p><?php
    foreach(['ingreso'=>'Premios y regalos','tarea'=>'Tareas de la casa']as$grupo=>$titulo): if(!array_filter($categorias,fn($c)=>$c['grupo']===$grupo))continue; ?><details <?=$grupo==='ingreso'||($cat&&$cat['grupo']===$grupo)?'open':''?>><summary><?=h($titulo)?></summary><div class="chips"><?php foreach($categorias as$c):if($c['grupo']!==$grupo)continue; ?><a class="chip <?=$elegida===(int)$c['id']?'elegido':''?>" href="<?=h(ruta('/?ingreso_cat='.$c['id']))?>#cargar-ingreso"><?=h($c['icono'].' '.$c['nombre'])?></a><?php endforeach; ?></div></details><?php endforeach;
    if($cat): ?><div class="subpanel"><h3><?=h($cat['nombre'])?></h3><?php
        formulario('ingreso',['categoria'=>$cat['id']],'/?ingreso_cat='.$cat['id'].'#cargar-ingreso',$cat['codigo']==='nota');
        if($cat['codigo']==='nota'){nota_campos();}
        else{
            if($cat['codigo']==='regalo'): ?><label>¿De quién?<select name="de"><?php opciones(['Abuelos'=>'Abuelos','Tíos'=>'Tíos','Padrinos'=>'Padrinos','Otro'=>'Otro']); ?></select></label><?php endif;
            if($cat['grupo']==='tarea'){campo('monto','Importe fijado por un adulto (pesos)','number',(string)sugerido($cat,$u),true,'readonly min="0" max="10000000"');}
            else campo('monto','¿Cuántos pesos recibiste?','number','',true,'min="1" max="10000000" step="1" inputmode="numeric"');
            campo('nota','¿Qué pasó? (opcional)','text','',false,'maxlength="230"');
        }
        if($cat['grupo']==='tarea'&&sugerido($cat,$u)===0): ?><p class="aviso">Pedile a un adulto que configure el monto antes de cargar este ingreso.</p><?php else: ?><button>Mandar para aprobar</button><?php endif; ?></form></div><?php endif; ?></section><?php
}
function vista_nino(array $u): void {
    ?><nav class="menu" aria-label="Mi libreta"><a class="chip elegido" href="<?=h(ruta('/'))?>">Mi libreta</a><a class="chip" href="#movimientos">Movimientos</a><a class="chip" href="#metas-<?=h($u['id'])?>">Mis metas</a><a class="chip" href="#efectivo-<?=h($u['id'])?>">Efectivo</a><a class="chip" href="<?=h(ruta('/?vista=avatar'))?>">Cambiar avatar</a></nav><div class="tablero-nino"><?php
    cuadro_saldo($u);
    ?><aside class="terminal-lateral"><?php metas_vista((int)$u['id']); ?></aside></div><?php
    cargar_vista_c8b($u);
    efectivo_vista($u,false,false);
    retiro_vista();movimientos_vista($u);rescates_vista((int)$u['id']);
}
function carga_vista(): void {
    $categorias=q('SELECT * FROM '.t('categorias')." WHERE activa=1 AND grupo IN ('ingreso','tarea','admin') ORDER BY orden,id")->fetchAll();
    $elegida=get_texto('carga',20); $selected=$elegida!==''?entero($elegida):null;
    $interes=(float)q('SELECT valor FROM '.t('config')." WHERE clave='interes'")->fetchColumn();
    $categorias=array_values(array_filter($categorias,fn($c)=>$c['codigo']!=='interes'||$interes>0));
    ?><section class="tarjeta"><h2>Cargar Notas Colegio, Tareas, Dinero</h2><p>Elegí una categoría. Revisá el monto de cada usuario antes de guardar.</p><?php foreach(['ingreso'=>'Ingresos','tarea'=>'Tareas de la casa','admin'=>'Operaciones del adulto'] as $grupo=>$titulo): if(!array_filter($categorias,fn($c)=>$c['grupo']===$grupo))continue; ?><details <?=$grupo==='admin'?($selected&&in_array($selected,array_column(array_filter($categorias,fn($c)=>$c['grupo']==='admin'),'id'))?'open':''):'open'?>><summary><?=h($grupo==='admin'?'Más opciones':$titulo)?></summary><div class="chips"><?php foreach($categorias as $c): if($c['grupo']!==$grupo) continue; if($c['codigo']==='interes'&&$interes<=0): ?><span class="chip desactivado">🌱 Interés desactivado</span><?php else: ?><a class="chip <?=$selected===(int)$c['id']?'elegido':''?>" href="<?=h(ruta("/"))?>?carga=<?=h($c['id'])?>#carga"><?=h($c['icono'].' '.$c['nombre'])?></a><?php endif; endforeach; ?></div></details><?php endforeach;
    $cat=null; foreach($categorias as $c) if((int)$c['id']===$selected) $cat=$c;
    if($cat&&$cat['codigo']==='nota'){
        ?><div id="carga" class="subpanel"><h3>Nota del colegio</h3><?php formulario('rapida',['categoria'=>$cat['id']],'/?carga='.$cat['id'].'#carga',true);selector_chico('destino');nota_campos(); ?><label class="check"><input type="checkbox" name="fondo" value="1"> Pagar con el Fondo de devaluaciones</label><p class="sutil">Disponible en el Fondo: <?=h(dinero(saldo_fondo()))?>.</p><button>Guardar nota</button></form></div></section><?php return;
    }
    if($cat && !($cat['codigo']==='interes'&&$interes<=0)) {
        $valorGrado=get_texto('grado',2); $grado=entero($valorGrado===''?'10':$valorGrado,0,10);
        ?><div id="carga" class="subpanel"><h3><?=h($cat['icono'].' '.$cat['nombre'])?></h3><?php 
        formulario('rapida',['categoria'=>$cat['id'],'grado'=>$grado],'/?carga='.$cat['id'].'&grado='.$grado.'#carga'); selector_chico('destino',true);

        if($cat['codigo']==='regalo'): ?><label>¿De quién?<select name="de"><?php opciones(['Abuelos'=>'Abuelos','Tíos'=>'Tíos','Padrinos'=>'Padrinos','Otro'=>'Otro']); ?></select></label><?php endif;
        foreach(chicos() as $n): ?><div data-monto-usuario="<?=h($n['id'])?>"><?php campo('monto_'.$n['id'],'Pesos para '.$n['nombre'],'number',(string)sugerido($cat,$n,$grado),true,($cat['codigo']==='nota'&&$grado<7?'readonly min="0"':'min="1"').' max="10000000" step="1" inputmode="numeric"'); ?></div><?php endforeach;
        campo('nota',in_array($cat['codigo'],['devaluacion','demerito_tutor'],true)?'Motivo obligatorio':'Motivo o detalle (opcional)','text','',in_array($cat['codigo'],['devaluacion','demerito_tutor'],true),'maxlength="230"');
        if($cat['codigo']==='devolucion_demerito'): ?><p>Se paga siempre con el Fondo de devaluaciones (disponible: <?=h(dinero(saldo_fondo()))?>).</p><?php
        elseif($cat['tipo']==='ingreso' && $cat['codigo']!=='demerito_tutor'): ?><label class="check"><input type="checkbox" name="fondo" value="1"> Pagar con el Fondo de devaluaciones</label><p class="sutil">Fondo disponible: <?=h(dinero(saldo_fondo()))?>. No es plata nueva.</p><?php
        elseif($cat['codigo']==='demerito_tutor'): ?><p>Este crédito es plata nueva: no usa el Fondo.</p><?php endif; ?><p class="sutil"><?=$cat['codigo']==='interes'?'Sugerencia de '.$interes.' % sobre el saldo libre actual. No se acredita automáticamente ni se garantiza un rendimiento.':'Se guarda sólo para el usuario elegido.'?></p><button>Guardar carga</button></form></div><?php
    } ?></section><?php
}
function pendientes_vista(): void {pendientes_vista_c8();}
function vista_admin(array $u,string $vista): void {
    ?><nav class="menu" aria-label="Administración"><?php foreach([''=>'Resumen','historial'=>'Historial','chicos'=>'Por usuario','fondo'=>'Fondo','config'=>'Configuración','seguridad'=>'Seguridad'] as $v=>$n): ?><a class="chip <?=$vista===$v?'elegido':''?>" href="<?=h(ruta('/?vista='.$v))?>"><?=h($n)?></a><?php endforeach; ?></nav><?php
    switch($vista) {
        case 'historial': historial_c8b($u); break;
        case 'chicos': foreach(chicos()as$c){$f=get_texto('chico',20);if($f!==''&&entero($f)!==(int)$c['id'])continue;?><div class="chico-bloque"><h1><?=h($c['nombre'])?></h1><?php metas_vista((int)$c['id'],true);efectivo_vista($c,true);?></div><?php } break;
        case 'fondo': fondo_vista((int)$u['id'],true);break;
        case 'config':configuracion_vista();break;
        case 'seguridad':?><details id="usuarios" <?=get_texto('seccion',20)!=='accesos'?'open':''?>><summary>Usuarios</summary><?php usuarios_vista();?></details><details id="accesos" <?=get_texto('seccion',20)==='accesos'?'open':''?>><summary>Ingresos y seguridad</summary><?php accesos_vista();?></details><?php break;
        default: ?><h1>La libreta de la familia</h1><div class="grilla resumenes"><?php foreach(chicos()as$c):?><div><?php cuadro_saldo($c,true);?></div><?php endforeach;?></div><?php pendientes_vista();carga_vista();
    }
}
function fondo_vista(int $actor,bool $detalle=false): void {
    exigir_admin($actor);
    ?><section class="tarjeta"><h2>Fondo de devaluaciones</h2><p class="cifra-fondo"><?=h(dinero(saldo_fondo()))?></p><p>Saldo común de los adultos. Cada descuento y uso queda vinculado al movimiento del usuario. Es distinto de la Cuenta Ahorro individual de cada usuario. No mueve plata bancaria.</p><?php
    if (!$detalle): ?><a class="boton secundario" href="<?=h(ruta('/?vista=fondo'))?>">Ver historial del Fondo</a><?php
    else:
        $pagina=entero(get_texto('pagina',6)?:'1',1,999999); $offset=($pagina-1)*20;
        $rows=q('SELECT f.delta,m.*,u.nombre,a.nombre adulto FROM '.t('fondo_movimientos').' f JOIN '.t('movimientos').' m ON m.id=f.movimiento JOIN '.t('usuarios').' u ON u.id=m.usuario JOIN '.t('usuarios').' a ON a.id=m.creado_por ORDER BY m.id DESC LIMIT 21 OFFSET '.$offset)->fetchAll();
        if (!$rows): ?><p>Todavía no hay movimientos del Fondo.</p><?php endif;
        foreach(array_slice($rows,0,20) as $r): ?><article class="movimiento"><div class="fila entre"><h3><?=h($r['nombre'].' · '.$r['concepto'])?></h3><strong class="<?=$r['delta']>=0?'ingreso':'egreso'?>"><?=$r['delta']>=0?'+':'−'?> <?=h(dinero(abs((int)$r['delta'])))?></strong></div><p><?=h($r['nota'])?></p><p class="sutil">Movimiento #<?=h($r['id'])?> · <?=h(fecha($r['fecha']))?> · <?=h($r['adulto'])?> · <?=h($r['estado'])?></p><?php if($r['inverso_de']): ?><p>Revierte el movimiento #<?=h($r['inverso_de'])?> y su efecto en el Fondo.</p><?php endif; ?></article><?php endforeach;
        ?><nav class="paginacion" aria-label="Páginas del Fondo"><?php if($pagina>1): ?><a href="<?=h(ruta('/?vista=fondo&pagina='.($pagina-1)))?>">← Anteriores</a><?php endif; if(count($rows)>20): ?><a href="<?=h(ruta('/?vista=fondo&pagina='.($pagina+1)))?>">Más movimientos →</a><?php endif; ?></nav><?php
    endif; ?></section><?php
}
function configuracion_vista(): void {
    ?><section class="tarjeta"><h1>Premios y mensualidad</h1><?php formulario('premios',['volver'=>'config']); foreach(q('SELECT * FROM '.t('premios').' WHERE nota>=7 ORDER BY nota DESC') as $p) campo('nota_'.$p['nota'],'Premio por nota '.$p['nota'],'number',(string)$p['monto'],true,'min="1" max="10000000" step="1"'); ?><p>Las notas menores a 7 siempre se registran con $ 0.</p><?php foreach(chicos() as $n) campo('mensualidad_'.$n['id'],'Mensualidad de '.$n['nombre'],'number',(string)$n['mensualidad'],true,'min="0" max="10000000" step="1"'); campo('interes','Interés mensual sugerido (%). 0 lo desactiva.','number',(string)q('SELECT valor FROM '.t('config')." WHERE clave='interes'")->fetchColumn(),true,'min="0" max="100" step="0.01"'); ?><button>Guardar configuración</button></form></section><?php categorias_vista_c8b(); ?><details><summary>+ Agregar categoría o tarea</summary><?php formulario('categoria_nueva',['volver'=>'config']); campo('nombre','Nombre','text','',true,'maxlength="100"'); campo('icono','Ícono emoji','text','📝',true,'maxlength="20"'); ?><label>Grupo<select name="grupo"><?php opciones(['ingreso'=>'Ingreso','tarea'=>'Tarea de la casa','gasto'=>'Gasto / motivo de retiro','admin'=>'Operación del adulto']); ?></select></label><?php campo('monto','Monto sugerido','number','0',true,'min="0" max="10000000"'); campo('orden','Orden','number','100',true,'min="0" max="9999"'); ?><button>Agregar categoría</button></form></details><?php
}
function usuarios_vista(): void {
    ?><section class="tarjeta"><h1>Usuarios</h1><p>Las altas se hacen sólo por consola. Nunca mandes un PIN por este sitio a otra persona: acordalo en privado.</p><?php foreach(q('SELECT * FROM '.t('usuarios').' ORDER BY id') as $r): ?><details><summary><?=h($r['nombre'].' · '.($r['rol']==='nino'?'usuario':$r['rol']))?><?=$r['activo']?'':' · inactivo'?></summary><?php formulario('usuario',['id'=>$r['id'],'volver'=>'usuarios']); campo('mail','Mail para recuperar el PIN (opcional)','email',$r['mail']??'',false,'maxlength="120" autocomplete="off"'); ?><label class="check"><input type="checkbox" name="activo" value="1" <?=$r['activo']?'checked':''?>> Cuenta activa</label><button>Guardar usuario</button></form><?php formulario('desbloquear',['valor'=>$r['nombre'],'volver'=>'usuarios']); ?><button class="secundario">Desbloquear usuario</button></form><details><summary>Dar un PIN provisorio</summary><?php formulario('provisorio',['id'=>$r['id'],'volver'=>'usuarios']); pin_campos(false); ?><p>Se cierran sus sesiones. Tendrá que cambiarlo al entrar (también los adultos).</p><button data-confirmar="¿Querés cambiar su PIN y cerrar sus sesiones?">Guardar PIN provisorio</button></form></details></details><?php endforeach; ?></section><section class="tarjeta"><h2>Desbloquear una IP</h2><p>Podés copiar el hash de la lista de bloqueos de abajo o escribir la dirección IP completa. No se guarda en texto.</p><?php formulario('desbloquear',['volver'=>'usuarios']); campo('valor','IP o hash de IP','text','',true,'maxlength="64"'); ?><button>Desbloquear IP</button></form><?php foreach(q('SELECT ip_hash,bloqueo FROM '.t('limites').' WHERE bloqueo>? ORDER BY bloqueo DESC',[ahora()]) as $l): ?><p class="hash"><?=h($l['ip_hash'])?> · hasta <?=h(fecha($l['bloqueo']))?></p><?php endforeach; ?></section><?php
}
function accesos_vista(): void {
    $id=get_texto('usuario',20); $tipo=get_texto('tipo',30); $dias=entero(get_texto('dias',3)?:'7',7,180); if(!in_array($dias,[7,30,180],true)) $dias=7;
    $w=['a.fecha>=?']; $p=[ahora(-$dias*86400)];
    if($id!=='') { $w[]='a.usuario=?'; $p[]=entero($id); }
    $tipos=['ingreso'=>'Ingreso correcto','fallo'=>'PIN incorrecto','bloqueo'=>'Bloqueo','recuperacion_pedida'=>'Recuperación pedida','recuperacion_usada'=>'Recuperación usada','salida'=>'Salida'];
    if(isset($tipos[$tipo])) { $w[]='a.tipo=?'; $p[]=$tipo; }
    $pagina=entero(get_texto('pagina',6)?:'1',1,999999); $offset=($pagina-1)*50;
    $rows=q('SELECT a.*,u.nombre FROM '.t('accesos').' a LEFT JOIN '.t('usuarios').' u ON u.id=a.usuario WHERE '.implode(' AND ',$w).' ORDER BY a.id DESC LIMIT 51 OFFSET '.$offset,$p)->fetchAll();
    ?><section class="tarjeta"><h1>Registro de ingresos</h1><form method="get" class="filtros"><input type="hidden" name="vista" value="seguridad"><input type="hidden" name="seccion" value="accesos"><label>Usuario<select name="usuario"><option value="">Todos</option><?php foreach(q('SELECT id,nombre FROM '.t('usuarios').' ORDER BY nombre') as $a) opciones([$a['id']=>$a['nombre']],$id); ?></select></label><label>Tipo<select name="tipo"><?php opciones([''=>'Todos']+$tipos,$tipo); ?></select></label><label>Período<select name="dias"><?php opciones([7=>'7 días',30=>'30 días',180=>'180 días'],(string)$dias); ?></select></label><button>Filtrar</button></form><p class="sutil">Retención: 180 días. No se guarda la IP completa ni el user-agent completo.</p><?php if(!$rows): ?><p>No hay registros en este período.</p><?php endif; foreach(array_slice($rows,0,50) as $r): ?><article class="movimiento"><strong><?=h($tipos[$r['tipo']]??$r['tipo'])?> · <?=h($r['nombre']??'Desconocido')?></strong><p><?=h(fecha($r['fecha']).' · '.$r['dispositivo'])?></p><details><summary>Hash de IP</summary><p class="hash"><?=h($r['ip_hash'])?></p></details></article><?php endforeach; ?><nav class="paginacion" aria-label="Páginas de ingresos"><?php $base=['vista'=>'seguridad','seccion'=>'accesos','usuario'=>$id,'tipo'=>$tipo,'dias'=>$dias]; if($pagina>1): ?><a href="<?=h(ruta("/"))?>?<?=h(http_build_query($base+['pagina'=>$pagina-1]))?>">← Anteriores</a><?php endif; if(count($rows)>50): ?><a href="<?=h(ruta("/"))?>?<?=h(http_build_query($base+['pagina'=>$pagina+1]))?>">Más registros →</a><?php endif; ?></nav></section><?php
}
