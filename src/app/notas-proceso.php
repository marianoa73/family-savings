<?php
declare(strict_types=1);
// Programar dentro de la transacción contable, ejecutar sólo después del commit exterior.
function nota_foto_programar(int $movimiento,int $actor): void {
    $m=uno('SELECT foto_nota FROM '.t('movimientos')." WHERE id=? AND estado='confirmado' AND tipo='ingreso' AND grado IS NOT NULL AND inverso_de IS NULL",[$movimiento]);
    if(!$m||!$m['foto_nota'])return;$foto=(int)$m['foto_nota'];
    q('UPDATE '.t('notas_fotos').' SET reintentar=1 WHERE id=? AND procesada IS NULL',[$foto]);
    tras_commit(static function()use($foto,$actor){nota_foto_procesar($foto,$actor);});
}
function nota_foto_elegible(int $foto): ?array {
    return uno('SELECT id FROM '.t('movimientos')." WHERE foto_nota=? AND estado='confirmado' AND tipo='ingreso' AND grado IS NOT NULL AND inverso_de IS NULL ORDER BY id DESC LIMIT 1",[$foto]);
}
function nota_foto_evento(int $movimiento,int $actor,string $tipo,array $antes,array $despues): void {
    q('INSERT INTO '.t('notas_eventos').' (movimiento,actor,fecha,motivo,anterior,actual,tipo) VALUES (?,?,?,?,?,?,?)',[$movimiento,$actor,ahora(),$tipo==='foto_procesada'?'Foto reducida y comprimida al autorizar la nota':'Foto pendiente de procesar; se reintentará',json_encode($antes,JSON_THROW_ON_ERROR),json_encode($despues,JSON_THROW_ON_ERROR),$tipo]);
}
function nota_foto_procesar(int $foto,int $actor): bool {
    exigir_admin($actor);
    if(db()->inTransaction())throw new LogicException('Procesar foto después del commit contable.');
    try{
        return transaccion(function()use($foto,$actor){
            $f=uno('SELECT * FROM '.t('notas_fotos').' WHERE id=? FOR UPDATE',[$foto]);
            if(!$f||$f['procesada']!==null)return true;
            $m=nota_foto_elegible($foto);if(!$m)return false;
            $tmp=null;$origen=null;$salida=null;
            try{
                $path=nota_foto_archivo($f['archivo']);$info=@getimagesize($path);
                if(!$info||$info[2]!==IMAGETYPE_WEBP||$info[0]*$info[1]>16000000)throw new RuntimeException('Foto privada inválida.');
                $origen=@imagecreatefromwebp($path);if(!$origen)throw new RuntimeException('No se pudo leer la foto.');
                $w=imagesx($origen);$h=imagesy($origen);$factor=min(1,1024/max($w,$h));$nw=max(1,(int)round($w*$factor));$nh=max(1,(int)round($h*$factor));
                $salida=imagecreatetruecolor($nw,$nh);imagefill($salida,0,0,imagecolorallocate($salida,255,255,255));
                if(!imagecopyresampled($salida,$origen,0,0,0,0,$nw,$nh,$w,$h))throw new RuntimeException('No se pudo reducir la foto.');
                $tmp=dirname($path).'/.c9-'.bin2hex(random_bytes(16)).'.tmp';$mask=umask(0077);
                try{$stream=@fopen($tmp,'xb');}finally{umask($mask);}
                if(!$stream)throw new RuntimeException('No se pudo crear temporal.');
                try{if(!imagewebp($salida,$stream,80)||!fflush($stream)||(function_exists('fsync')&&!fsync($stream)))throw new RuntimeException('No se pudo escribir la foto.');}finally{fclose($stream);}
                $fin=@getimagesize($tmp);if(!$fin||$fin[0]!==$nw||$fin[1]!==$nh||!filesize($tmp)||!chmod($tmp,0600))throw new RuntimeException('No se pudo verificar la foto.');
                $antes=['foto'=>$foto,'ancho'=>$w,'alto'=>$h,'bytes'=>filesize($path)];
                // Si ya cabe y el WebP existente es menor, conservarlo evita agrandarlo por otra codificación.
                if(max($w,$h)<=1024&&filesize($tmp)>=$antes['bytes']){unlink($tmp);$tmp=null;}
                else{if(!@rename($tmp,$path))throw new RuntimeException('No se pudo reemplazar la foto.');$tmp=null;}
                clearstatcache(true,$path);
                q('UPDATE '.t('notas_fotos').' SET ancho=?,alto=?,procesada=?,reintentar=0 WHERE id=?',[$nw,$nh,ahora(),$foto]);
                nota_foto_evento((int)$m['id'],$actor,'foto_procesada',$antes,['foto'=>$foto,'ancho'=>$nw,'alto'=>$nh,'bytes'=>filesize($path)]);
                return true;
            }finally{if($origen)imagedestroy($origen);if($salida)imagedestroy($salida);if($tmp&&is_file($tmp))unlink($tmp);}
        });
    }catch(Throwable $e){
        // El dinero ya confirmó antes de entrar. Mantener el archivo válido y reintentar sin revertir la aprobación.
        try{transaccion(function()use($foto,$actor){
            $f=uno('SELECT * FROM '.t('notas_fotos').' WHERE id=? FOR UPDATE',[$foto]);$m=nota_foto_elegible($foto);
            if($f&&$f['procesada']===null&&$m){q('UPDATE '.t('notas_fotos').' SET reintentar=1 WHERE id=?',[$foto]);nota_foto_evento((int)$m['id'],$actor,'foto_error',['foto'=>$foto],['foto'=>$foto,'reintentar'=>true]);}
        });}catch(Throwable $registro){error_log('Mi ahorro: foto pendiente de procesar, revisar mantenimiento.');}
        return false;
    }
}
function nota_fotos_pendientes(bool $soloReintentos=false,int $limite=100): array {
    entero($limite,1,1000);
    return q('SELECT f.id FROM '.t('notas_fotos').' f WHERE f.procesada IS NULL'.($soloReintentos?' AND f.reintentar=1':'').' AND EXISTS (SELECT 1 FROM '.t('movimientos')." m WHERE m.foto_nota=f.id AND m.estado='confirmado' AND m.tipo='ingreso' AND m.grado IS NOT NULL AND m.inverso_de IS NULL) ORDER BY f.id LIMIT ".$limite)->fetchAll(PDO::FETCH_COLUMN);
}
function nota_fotos_reintentar(int $actor,int $limite=3): void {
    exigir_admin($actor);foreach(nota_fotos_pendientes(true,$limite)as$id)nota_foto_procesar((int)$id,$actor);
}
