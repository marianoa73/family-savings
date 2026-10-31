<?php
declare(strict_types=1);
require_once __DIR__.'/lib.php';
function cuenta_autorizar(int $actor,int $chico): void {
    $a=uno('SELECT id,rol FROM '.t('usuarios').' WHERE id=? AND activo=1',[$actor]);
    if(!$a || ($a['rol']!=='admin'&&$actor!==$chico))throw new DomainException('No podés operar esa cuenta.');
    nino($chico);
}
function saldo_efectivo(int $chico): int {
    return (int)q('SELECT COALESCE(SUM(CASE WHEN tipo=\'ingreso\' THEN monto ELSE -monto END),0) FROM '.t('efectivo_movimientos').' WHERE usuario=?',[$chico])->fetchColumn();
}
function saldos_cuenta(int $chico): array {
    $fondo=saldo($chico);$efectivo=saldo_efectivo($chico);
    return ['fondo'=>$fondo['disponible'],'efectivo'=>$efectivo,'total'=>$fondo['disponible']+$efectivo,'reservado'=>$fondo['reservado']];
}
function efectivo_cargar(int $actor,int $chico,string $tipo,int $monto,string $nota): int {
    cuenta_autorizar($actor,$chico);entero($monto);$nota=texto(['nota'=>$nota],'nota',230,true);
    if(!in_array($tipo,['ingreso','egreso'],true))throw new DomainException('Elegí entrada o salida de efectivo.');
    return transaccion(function()use($actor,$chico,$tipo,$monto,$nota){
        nino($chico,true);if($tipo==='egreso'&&$monto>saldo_efectivo($chico))throw new DomainException('No alcanza tu efectivo.');
        q('INSERT INTO '.t('efectivo_movimientos').' (usuario,tipo,monto,nota,fecha,creado_por) VALUES (?,?,?,?,?,?)',[$chico,$tipo,$monto,$nota,ahora(),$actor]);
        return (int)db()->lastInsertId();
    });
}
function efectivo_anular(int $actor,int $id,string $nota): int {
    exigir_admin($actor);$nota=texto(['nota'=>$nota],'nota',230,true);
    $u=uno('SELECT usuario FROM '.t('efectivo_movimientos').' WHERE id=?',[$id]);if(!$u)throw new DomainException('Movimiento no disponible.');
    return transaccion(function()use($actor,$id,$nota,$u){
        nino((int)$u['usuario'],true);$m=uno('SELECT * FROM '.t('efectivo_movimientos').' WHERE id=? FOR UPDATE',[$id]);
        if(!$m||$m['inverso_de']!==null||$m['inverso_id']!==null)throw new DomainException('Sólo se anula una vez el original.');
        if($m['tipo']==='ingreso'&&(int)$m['monto']>saldo_efectivo((int)$m['usuario']))throw new DomainException('Ese efectivo ya fue gastado.');
        q('INSERT INTO '.t('efectivo_movimientos').' (usuario,tipo,monto,nota,fecha,creado_por,inverso_de) VALUES (?,?,?,?,?,?,?)',[$m['usuario'],$m['tipo']==='ingreso'?'egreso':'ingreso',$m['monto'],$nota,ahora(),$actor,$id]);
        $inverso=(int)db()->lastInsertId();q('UPDATE '.t('efectivo_movimientos').' SET inverso_id=?,anulado_por=?,anulado=? WHERE id=?',[$inverso,$actor,ahora(),$id]);return $inverso;
    });
}
function meta_operar(int $actor,int $id,string $accion): ?int {
    $m=uno('SELECT usuario FROM '.t('metas').' WHERE id=?',[$id]);if(!$m)throw new DomainException('Meta no disponible.');
    $chico=(int)$m['usuario'];cuenta_autorizar($actor,$chico);
    return transaccion(function()use($actor,$id,$accion,$chico){
        nino($chico,true);$m=uno('SELECT * FROM '.t('metas').' WHERE id=? FOR UPDATE',[$id]);
        if(!$m||!in_array($m['estado'],$accion==='borrar'?['activa','cumplida']:['activa'],true))throw new DomainException('La meta ya cambió de estado.');
        if($accion==='rescate'){
            if((int)$m['monto']>saldo($chico)['disponible'])throw new DomainException('No alcanza la Cuenta Ahorro disponible. El efectivo no financia rescates.');
            q('INSERT INTO '.t('rescates').' (usuario,monto,motivo,nota,fecha,creado_por,meta) VALUES (?,?,?,?,?,?,?)',[$chico,$m['monto'],$m['nombre'],'Rescate de meta',ahora(),$actor,$id]);$rescate=(int)db()->lastInsertId();
            q('UPDATE '.t('metas')." SET estado='rescate_pedido',estado_fecha=? WHERE id=?",[ahora(),$id]);return $rescate;
        }
        if($accion==='borrar'){
            if(uno('SELECT id FROM '.t('rescates').' WHERE meta=? LIMIT 1',[$id]))throw new DomainException('No se puede borrar: esta meta ya tuvo un rescate pedido.');
            q('UPDATE '.t('metas')." SET estado='borrada',borrado_por=?,borrada=?,estado_fecha=? WHERE id=?",[$actor,ahora(),ahora(),$id]);return null;
        }
        if($accion!=='cumplida')throw new DomainException('Acción de meta inválida.');
        q('UPDATE '.t('metas')." SET estado='cumplida',estado_fecha=? WHERE id=?",[ahora(),$id]);return null;
    });
}
