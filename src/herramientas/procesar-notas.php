<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/acciones.php';
try{
    $opts=getopt('',['contar','aplicar','actor:','limite:']);
    if((isset($opts['contar'])===isset($opts['aplicar']))||array_diff(array_keys($opts),['contar','aplicar','actor','limite']))throw new DomainException('Uso: php procesar-notas.php --contar | --aplicar --actor=ID_ADULTO [--limite=100]');
    $cantidad=(int)q('SELECT COUNT(*) FROM '.t('notas_fotos')." f WHERE f.procesada IS NULL AND EXISTS (SELECT 1 FROM ".t('movimientos')." m WHERE m.foto_nota=f.id AND m.estado='confirmado' AND m.tipo='ingreso' AND m.grado IS NOT NULL AND m.inverso_de IS NULL)")->fetchColumn();
    $r=['modo'=>isset($opts['contar'])?'contar':'aplicar','fotos_confirmadas_pendientes'=>$cantidad];
    if(isset($opts['aplicar'])){
        $actor=entero($opts['actor']??'');exigir_admin($actor);$limite=entero($opts['limite']??'100',1,1000);$r['procesadas']=0;$r['fallidas']=0;
        foreach(nota_fotos_pendientes(false,$limite)as$id){if(nota_foto_procesar((int)$id,$actor))$r['procesadas']++;else$r['fallidas']++;}
    }
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";exit(($r['fallidas']??0)>0?2:0);
}catch(Throwable $e){fwrite(STDERR,($e instanceof DomainException?$e->getMessage():'No se pudo ejecutar el mantenimiento. Revisá configuración y logs privados.')."\n");exit(1);}
