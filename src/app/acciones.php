<?php
declare(strict_types=1);
require_once __DIR__.'/lib.php';
require_once __DIR__.'/cuentas.php';
require_once __DIR__.'/notas.php';
function exigir_admin(int $id): void {
    if (!uno('SELECT id FROM '.t('usuarios')." WHERE id=? AND activo=1 AND rol='admin'",[$id])) throw new DomainException('Esta acción es sólo para adultos.');
}
function chicos(): array { return q('SELECT * FROM '.t('usuarios')." WHERE rol='nino' AND activo=1 ORDER BY id")->fetchAll(); }
function sugerido(array $c,array $u,?int $nota=null): int {
    if ($c['codigo']==='nota') return (int)(q('SELECT monto FROM '.t('premios').' WHERE nota=?',[$nota??10])->fetchColumn()?:0);
    if ($c['codigo']==='mensualidad') return (int)$u['mensualidad'];
    if ($c['codigo']==='interes') return min(MAX_MONTO,max(0,(int)floor(saldo((int)$u['id'])['disponible']*(float)q('SELECT valor FROM '.t('config')." WHERE clave='interes'")->fetchColumn()/100)));
    $v=q('SELECT monto FROM '.t('sugerencias').' WHERE usuario=? AND categoria=?',[$u['id'],$c['id']])->fetchColumn();
    return $v===false?(int)$c['sugerido']:(int)$v;
}
const INGRESOS_CHICO = ['nota','cumple','regalo','nino','navidad','reyes','pascua','raton','logro','venta'];
const CATEGORIAS_ADULTO = ['devaluacion'=>'egreso','devolucion_demerito'=>'ingreso','demerito_tutor'=>'ingreso'];
function ingreso_chico_permitido(array $c): bool {
    return !isset(CATEGORIAS_ADULTO[$c['codigo']]) && (bool)$c['activa'] && $c['tipo']==='ingreso' && !in_array($c['codigo'],['mensualidad','interes'],true)
        && ($c['grupo']==='tarea' || ($c['grupo']==='ingreso' && in_array($c['codigo'],INGRESOS_CHICO,true)));
}
function cargar_ingreso(int $actor,array $in): int {
    // No hay un destinatario libre: la sesión determina el chico. Un campo de
    // destinatario falsificado se rechaza en vez de redirigirlo silenciosamente.
    foreach(['usuario','destino'] as $campo) if (array_key_exists($campo,$in) && entero($in[$campo])!==$actor) throw new DomainException('Sólo podés cargar ingresos para vos.');
    return transaccion(function() use($actor,$in) {
        $u=nino($actor,true); $c=categoria(entero($in['categoria']??''));
        if (!ingreso_chico_permitido($c)) throw new DomainException('Esa categoría no está habilitada para que cargues ingresos.');
        $grado=null; $materia=null; $nota=texto($in,'nota',230);
        if ($c['codigo']==='nota') {
            $datosNota=nota_datos($in);$grado=$datosNota['grado'];$materia=$datosNota['materia'];$monto=$datosNota['monto']; // Nunca leer monto enviado.
        } elseif ($c['grupo']==='tarea') {
            $monto=sugerido($c,$u); // Tampoco leer monto ni monto_<otro chico>.
            if ($monto===0) throw new DomainException('Esta tarea todavía no tiene monto. Pedile a un adulto que lo configure.');
        } else $monto=entero($in['monto']??'');
        if ($c['codigo']==='regalo') {
            $de=texto($in,'de',20,true);
            if (!in_array($de,['Abuelos','Tíos','Padrinos','Otro'],true)) throw new DomainException('Elegí de quién es el regalo.');
            $nota=$de.($nota!==''?' · '.$nota:'');
        }
        $concepto=$c['nombre'].($grado!==null?' · '.($grado===10?'10 / Excelente':$grado).' · '.$materia:'');
        $id=agregar_movimiento($actor,'ingreso',$monto,$c,$concepto,$nota,$actor,'pendiente',$grado,$materia);
        if($grado!==null)nota_asociar($id,$actor,$actor,$datosNota,$in['_foto_preparada']??null);return $id;
    });
}
function operar_una_vez(array $u,array $in): void {
    $envio=texto($in,'envio',64,true);
    if (!preg_match('/\A[a-f0-9]{64}\z/D',$envio)) throw new DomainException('Volvé a abrir el formulario.');
    transaccion(function() use($envio,$u,$in) {
        $s=q('INSERT IGNORE INTO '.t('envios').' (token,usuario,fecha) VALUES (?,?,?)',[$envio,$u['id'],ahora()]);
        if ($s->rowCount()!==1) throw new DomainException('Ese formulario ya se guardó. Revisá el historial.');
        operar($u,$in);
    });
}
function carga_rapida(int $actor,array $in): void {
    exigir_admin($actor);
    $cid=entero($in['categoria']??''); $destino=texto($in,'destino',20,true);
    $targets=[entero($destino)]; // 22/10: siempre individual ("ambos" confundía)
    sort($targets,SORT_NUMERIC);
    transaccion(function() use($targets,$in,$actor,$cid) {
        bloquear_fondo(); // Fondo común antes de tomar el bloqueo del chico.
        $usuarios=[]; foreach($targets as $id) $usuarios[]=nino((int)$id,true);
        $c=categoria($cid);
        if (!in_array($c['grupo'],['ingreso','tarea','admin'],true)) throw new DomainException('Elegí una categoría de carga rápida.');
        if ($c['codigo']==='interes' && (float)q('SELECT valor FROM '.t('config')." WHERE clave='interes'")->fetchColumn()<=0) throw new DomainException('El interés está desactivado en configuración.');
        $grado=null; $materia=null; $nota=texto($in,'nota',230);
        $codigo=$c['codigo'];
        if (isset(CATEGORIAS_ADULTO[$codigo]) && $c['tipo']!==CATEGORIAS_ADULTO[$codigo]) throw new DomainException('Tipo de categoría protegido incorrecto.');
        if (in_array($codigo,['devaluacion','demerito_tutor'],true) && $nota==='') throw new DomainException('Contá el motivo de esta operación.');
        $desdeFondo=isset($in['fondo']) && texto($in,'fondo',1,true)==='1';
        if ($desdeFondo && ($c['tipo']!=='ingreso' || $codigo==='demerito_tutor')) throw new DomainException('Esa categoría no puede pagarse con el Fondo.');
        $desdeFondo=$desdeFondo || $codigo==='devolucion_demerito';
        if ($c['codigo']==='nota') { $datosNota=nota_datos($in);$grado=$datosNota['grado'];$materia=$datosNota['materia']; }
        if ($c['codigo']==='regalo') {
            $de=texto($in,'de',20,true); if (!in_array($de,['Abuelos','Tíos','Padrinos','Otro'],true)) throw new DomainException('Elegí de quién es el regalo.');
            $nota=$de.($nota!==''?' · '.$nota:'');
        }
        foreach($usuarios as $u) {
            // Sólo se carga al destinatario elegido. La nota <7 siempre vale cero.
            $monto=$grado!==null?$datosNota['monto']:entero($in['monto_'.$u['id']]??'',1);
            if ($c['tipo']==='egreso' && $monto>saldo((int)$u['id'])['disponible']) throw new DomainException('No alcanza el saldo libre de '.$u['nombre'].'. No se cargó nada.');
            $concepto=$c['nombre'].($grado!==null?' · '.($grado===10?'10 / Excelente':$grado).' · '.$materia:'');
            $id=agregar_movimiento((int)$u['id'],$c['tipo'],$monto,$c,$concepto,$nota,$actor,'confirmado',$grado,$materia);
            if($grado!==null)nota_asociar($id,(int)$u['id'],$actor,$datosNota,$in['_foto_preparada']??null);
            if ($codigo==='devaluacion') registrar_fondo($id,$monto);
            elseif ($desdeFondo) registrar_fondo($id,-$monto);
        }
    });
}
function crear_meta(int $usuario,int $actor,string $nombre,int $monto): void {
    if ($usuario!==$actor) exigir_admin($actor);
    nino($usuario); entero($monto);
    q('INSERT INTO '.t('metas').' (usuario,nombre,monto,creado_por,fecha) VALUES (?,?,?,?,?)',[$usuario,$nombre,$monto,$actor,ahora()]);
}
function configurar_categoria(array $in,int $actor): void {
    exigir_admin($actor);
    $id=entero($in['id']??''); $nombre=texto($in,'nombre',100,true); $icono=texto($in,'icono',20,true);
    $monto=entero($in['monto']??'',0); $orden=entero($in['orden']??'',0,9999);
    $tipo=texto($in,'tipo',10,true);
    if (!in_array($tipo,['ingreso','egreso'],true)) throw new DomainException('Tipo incorrecto.');
    transaccion(function() use($in,$id,$nombre,$icono,$monto,$orden,$tipo) {
        $c=uno('SELECT * FROM '.t('categorias').' WHERE id=? FOR UPDATE',[$id]);
        if (!$c) throw new DomainException('Categoría no disponible.');
        $esperado=CATEGORIAS_ADULTO[$c['codigo']]??(in_array($c['grupo'],['ingreso','tarea'],true)?'ingreso':'egreso');
        if ($tipo!==$esperado) throw new DomainException('El tipo debe respetar la función de la categoría (ingreso o gasto).');
        q('UPDATE '.t('categorias').' SET nombre=?,icono=?,tipo=?,sugerido=?,orden=? WHERE id=?',[$nombre,$icono,$tipo,$monto,$orden,$id]);
        foreach(chicos() as $u) {
            $v=texto($in,'sugerido_'.$u['id'],10);
            if ($v==='') q('DELETE FROM '.t('sugerencias').' WHERE usuario=? AND categoria=?',[$u['id'],$id]);
            else q('INSERT INTO '.t('sugerencias').' (usuario,categoria,monto) VALUES (?,?,?) ON DUPLICATE KEY UPDATE monto=VALUES(monto)',[$u['id'],$id,entero($v,0)]);
        }
    });
}
function operar(array $u,array $in): void {
    $a=texto($in,'accion',40,true); $actor=(int)$u['id'];
    if ($u['debe_cambiar'] && !in_array($a,['pin','salir'],true)) throw new DomainException('Primero cambiá tu PIN provisorio.');
    if ($a==='salir') { cerrar($u); return; }
    if ($a==='pin') {
        $pin=texto($in,'nueva',6,true);
        if ($pin!==texto($in,'repetir',6,true)) throw new DomainException('Los PIN nuevos no coinciden.');
        $actual=texto($in,'actual',6,true);
        if (autenticar($u['nombre'],$actual)!==$actor) throw new DomainException('No pudimos verificar el PIN. Revisalo o esperá unos minutos.');
        transaccion(function() use($actor,$pin,$actual) {
            $r=uno('SELECT * FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$actor]);
            if (!$r || !password_verify($actual,$r['pin_hash'])) throw new DomainException('El PIN cambió. Volvé a ingresar.');
            cambiar_pin($actor,$pin);
            // Mantener el bloqueo hasta crear la nueva sesión: un reset concurrente
            // debe invalidarla también y no dejar una sesión posterior al cambio.
            nueva_sesion($actor);
        });
        return;
    }
    if (in_array($a,['meta_crear','meta_estado','meta_rescate','meta_borrar'],true)) {
        if ($a==='meta_crear') {
            $id=$u['rol']==='admin'?entero($in['usuario']??''):$actor;
            crear_meta($id,$actor,texto($in,'nombre',80,true),entero($in['monto']??''));
        } else {
            $id=entero($in['id']??'');
            if($a==='meta_borrar') {
                if(texto($in,'confirmar',10,true)!=='borrar')throw new DomainException('Confirmá la eliminación desde la página.');
                meta_operar($actor,$id,'borrar');
            } elseif($a==='meta_rescate')meta_operar($actor,$id,'rescate');
            else {
                if(texto($in,'estado',15,true)!=='cumplida')throw new DomainException('Estado inválido.');
                meta_operar($actor,$id,'cumplida');
            }
        }
        return;
    }
    if($a==='efectivo') {
        $chico=isset($in['usuario'])?entero($in['usuario']):$actor;
        efectivo_cargar($actor,$chico,texto($in,'tipo',10,true),entero($in['monto']??''),texto($in,'nota',230,true));return;
    }
    if($a==='efectivo_anular') { efectivo_anular($actor,entero($in['id']??''),texto($in,'nota',230,true));return; }
    if ($u['rol']==='nino') {
        switch($a) {
            case 'ingreso': cargar_ingreso($actor,$in); return;
            case 'cancelar_ingreso': cancelar_ingreso($actor,entero($in['id']??'')); return;
            case 'rescate': pedir_rescate($actor,entero($in['monto']??''),texto($in,'motivo',100,true),texto($in,'nota',300)); return;
            case 'cancelar': decidir_rescate(entero($in['id']??''),$actor,'cancelar','',$actor); return;
            default: throw new DomainException('Esa acción no está disponible para tu cuenta.');
        }
    }
    exigir_admin($actor);
    switch($a) {
        case 'nota_corregir': nota_corregir($actor,entero($in['id']??''),$in); return;
        case 'rapida': carga_rapida($actor,$in); return;
        case 'aprobar_movimiento': decidir_movimiento(entero($in['id']??''),$actor,true); return;
        case 'rechazar_movimiento': decidir_movimiento(entero($in['id']??''),$actor,false); return;
        case 'confirmar_gasto': decidir_gasto(entero($in['id']??''),$actor,true); return;
        case 'rechazar_gasto': decidir_gasto(entero($in['id']??''),$actor,false); return;
        case 'aprobar': case 'rechazar': case 'entregar': decidir_rescate(entero($in['id']??''),$actor,$a,texto($in,'medio',40)); return;
        case 'anular': anular(entero($in['id']??''),$actor,texto($in,'motivo',300,true)); return;
        case 'categoria_estado': categoria_estado($actor,$in); return;
        case 'categoria': configurar_categoria($in,$actor); return;
        case 'categoria_nueva':
            $grupo=texto($in,'grupo',10,true);
            if (!in_array($grupo,['ingreso','tarea','gasto','admin'],true)) throw new DomainException('Elegí el grupo.');
            q('INSERT INTO '.t('categorias').' (codigo,nombre,icono,tipo,grupo,sugerido,orden) VALUES (?,?,?,?,?,?,?)',['extra_'.bin2hex(random_bytes(8)),texto($in,'nombre',100,true),texto($in,'icono',20,true),in_array($grupo,['gasto','admin'],true)?'egreso':'ingreso',$grupo,entero($in['monto']??'',0),entero($in['orden']??'',0,9999)]); return;
        case 'premios':
            transaccion(function() use($in) {
                for($g=0;$g<=10;$g++) {
                    // Debajo de 7 sólo registrar, nunca premiar con dinero.
                    $v=$g<7?0:entero($in['nota_'.$g]??'',1);
                    q('UPDATE '.t('premios').' SET monto=? WHERE nota=?',[$v,$g]);
                }
                $i=texto($in,'interes',6,true);
                if (!preg_match('/\A(?:[0-9]|[1-9][0-9]|100)(?:\.[0-9]{1,2})?\z/D',$i) || (float)$i>100) throw new DomainException('Interés: de 0 a 100, hasta dos decimales (punto).');
                q('UPDATE '.t('config')." SET valor=? WHERE clave='interes'",[$i]);
                foreach(chicos() as $c) q('UPDATE '.t('usuarios').' SET mensualidad=? WHERE id=?',[entero($in['mensualidad_'.$c['id']]??'',0),$c['id']]);
            }); return;
        case 'usuario':
            $id=entero($in['id']??''); $mail=texto($in,'mail',120);
            if ($mail!=='' && !mail_valido($mail)) throw new DomainException('Revisá el mail.');
            transaccion(function() use($id,$mail,$in,$actor) {
                // Serializar todos los cambios administrativos para no desactivar al último admin en carrera.
                $admins=q('SELECT id FROM '.t('usuarios')." WHERE rol='admin' ORDER BY id FOR UPDATE")->fetchAll();
                $r=uno('SELECT * FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$id]);
                if (!$r) throw new DomainException('Usuario no disponible.');
                $activo=isset($in['activo'])?1:0;
                if (!$activo && $r['rol']==='admin' && (int)q('SELECT COUNT(*) FROM '.t('usuarios')." WHERE rol='admin' AND activo=1")->fetchColumn()<=1) throw new DomainException('Tiene que quedar al menos un adulto activo.');
                q('UPDATE '.t('usuarios').' SET mail=?,activo=? WHERE id=?',[$mail!==''?$mail:null,$activo,$id]);
                if (!$activo) q('DELETE FROM '.t('sesiones').' WHERE usuario=?',[$id]);
            }); return;
        case 'provisorio':
            $id=entero($in['id']??''); $pin=texto($in,'nueva',6,true);
            if ($pin!==texto($in,'repetir',6,true)) throw new DomainException('Los PIN no coinciden.');
            transaccion(function() use($id,$pin) {
                if (!uno('SELECT id FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$id])) throw new DomainException('Usuario no disponible.');
                cambiar_pin($id,$pin,true);
            }); return;
        case 'desbloquear': desbloquear(texto($in,'valor',64,true)); return;
        default: throw new DomainException('Acción desconocida.');
    }
}

function categoria_estado(int $actor,array $in): void {
    exigir_admin($actor);$id=entero($in['id']??'');$activa=entero($in['mostrar']??'',0,1);
    if(!$activa&&texto($in,'confirmar',10)!=='quitar')throw new DomainException('Confirmá Quitar desde la página.');
    transaccion(function()use($actor,$id,$activa){
        $c=uno('SELECT * FROM '.t('categorias').' WHERE id=? FOR UPDATE',[$id]);
        if(!$c)throw new DomainException('Categoría no disponible.');
        if((int)$c['activa']===$activa)throw new DomainException('La categoría ya cambió. Actualizá la página.');
        q('UPDATE '.t('categorias').' SET activa=? WHERE id=?',[$activa,$id]);
        q('INSERT INTO '.t('categorias_eventos').' (categoria,actor,activa,fecha) VALUES (?,?,?,?)',[$id,$actor,$activa,ahora()]);
    });
}
