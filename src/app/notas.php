<?php
declare(strict_types=1);
require_once __DIR__.'/imagenes.php';
require_once __DIR__.'/notas-proceso.php';
const TIPOS_NOTA=['Prueba','Trabajo práctico','Trimestre o boletín','Otro'];
function nota_hoy(): string {return(new DateTimeImmutable('now',new DateTimeZone('America/Argentina/Cordoba')))->format('Y-m-d');}
function nota_datos(array $in): array {
    $grado=entero($in['grado']??'',0,10);$materia=texto($in,'materia',80,true);
    $fecha=texto($in,'fecha_nota',10,true);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
    if(!$d||$d->format('Y-m-d')!==$fecha||!preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/D',$fecha)||$fecha>nota_hoy())throw new DomainException('Elegí una fecha de la nota válida, hasta hoy.');
    $tipo=texto($in,'tipo_nota',30,true);if(!in_array($tipo,TIPOS_NOTA,true))throw new DomainException('Elegí el tipo de nota.');
    $monto=$grado<7?0:(int)q('SELECT monto FROM '.t('premios').' WHERE nota=?',[$grado])->fetchColumn();entero($monto,$grado<7?0:1);
    return ['grado'=>$grado,'materia'=>$materia,'fecha_nota'=>$fecha,'tipo_nota'=>$tipo,'monto'=>$monto];
}
function nota_asociar(int $id,int $chico,int $actor,array $d,?array $foto=null): void {
    $fid=null;
    if($foto){
        if($foto['usuario']!==$chico||$foto['actor']!==$actor||!is_file(nota_foto_archivo($foto['archivo'])))throw new DomainException('La foto no corresponde a esta nota.');
        q('INSERT INTO '.t('notas_fotos').' (usuario,actor,archivo,ancho,alto,fecha) VALUES (?,?,?,?,?,?)',[$chico,$actor,$foto['archivo'],$foto['ancho'],$foto['alto'],ahora()]);$fid=(int)db()->lastInsertId();
    }
    q('UPDATE '.t('movimientos').' SET fecha_nota=?,tipo_nota=?,foto_nota=? WHERE id=?',[$d['fecha_nota'],$d['tipo_nota'],$fid,$id]);
    nota_foto_programar($id,$actor);
}
function nota_corregir(int $actor,int $id,array $in): int {
    exigir_admin($actor);$d=nota_datos($in);$motivo=texto($in,'motivo',230,true);
    $u=uno('SELECT usuario FROM '.t('movimientos').' WHERE id=?',[$id]);if(!$u)throw new DomainException('Nota no disponible.');
    return transaccion(function()use($actor,$id,$in,$d,$motivo,$u){
        bloquear_fondo();nino((int)$u['usuario'],true);
        $m=uno('SELECT * FROM '.t('movimientos').' WHERE id=? FOR UPDATE',[$id]);
        $cat=$m&&$m['categoria']?uno('SELECT * FROM '.t('categorias').' WHERE id=?',[$m['categoria']]):null;
        if(!$m||!$cat||$cat['codigo']!=='nota'||$m['tipo']!=='ingreso'||$m['inverso_de']!==null||$m['grado']===null||!in_array($m['estado'],['pendiente','confirmado'],true))throw new DomainException('Sólo se corrigen notas pendientes o confirmadas sin anular.');
        // Evita que un formulario anterior sobrescriba una corrección o aprobación concurrente.
        if(texto($in,'version',64,true)!==nota_version($m))throw new DomainException('La nota cambió. Volvé a abrir Corregir nota.');
        $original=$m['grado_original']??$m['grado'];$concepto='Nota '.$d['grado'].' (corregida por un adulto) · '.$d['materia'];$nuevo=null;
        if($m['estado']==='pendiente'){
            q('UPDATE '.t('movimientos').' SET grado_original=?,grado=?,materia=?,fecha_nota=?,tipo_nota=?,monto=?,concepto=? WHERE id=?',[$original,$d['grado'],$d['materia'],$d['fecha_nota'],$d['tipo_nota'],$d['monto'],$concepto,$id]);$destino=$id;
        }else{
            if((int)$m['monto']>saldo((int)$m['usuario'])['disponible'])throw new DomainException('No se puede corregir: parte del premio ya se gastó o está reservada. Liberá saldo suficiente para anular el importe original. No se cambió nada.');
            anular($id,$actor,'Corrección de nota: '.$motivo);
            $destino=agregar_movimiento((int)$m['usuario'],'ingreso',$d['monto'],$cat,$concepto,$m['nota'],$actor,'confirmado',$d['grado'],$d['materia']);$nuevo=$destino;
            q('UPDATE '.t('movimientos').' SET fecha_nota=?,tipo_nota=?,foto_nota=?,grado_original=?,correccion_de=? WHERE id=?',[$d['fecha_nota'],$d['tipo_nota'],$m['foto_nota'],$original,$id,$destino]);
            $f=uno('SELECT delta FROM '.t('fondo_movimientos').' WHERE movimiento=?',[$id]);
            if($f){if((int)$f['delta']>0)throw new DomainException('La nota tiene un movimiento de Fondo inesperado.');registrar_fondo($destino,-$d['monto']);}
        }
        $antes=array_intersect_key($m,array_flip(['grado','materia','fecha_nota','tipo_nota','monto','estado','foto_nota']));
        q('INSERT INTO '.t('notas_eventos').' (movimiento,nuevo,actor,fecha,motivo,anterior,actual) VALUES (?,?,?,?,?,?,?)',[$id,$nuevo,$actor,ahora(),$motivo,json_encode($antes,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        if($nuevo!==null)nota_foto_programar($destino,$actor);
        return $destino;
    });
}
function nota_version(array $m): string {
    return hash('sha256',json_encode(array_intersect_key($m,array_flip(['id','estado','grado','materia','fecha_nota','tipo_nota','monto'])),JSON_THROW_ON_ERROR).'|'.(string)q('SELECT MAX(id) FROM '.t('notas_eventos')." WHERE movimiento=? AND tipo='correccion'",[$m['id']])->fetchColumn());
}
function notas_directorio(): string {
    $dir=datos().'/notas';if(is_link($dir))throw new RuntimeException('Directorio inválido.');
    if(!is_dir($dir)){$mask=umask(0077);try{if(!mkdir($dir,0700)&&!is_dir($dir))throw new RuntimeException('No se pudo guardar la foto.');}finally{umask($mask);}}
    if(realpath($dir)!==$dir||!is_writable($dir))throw new RuntimeException('Directorio privado no disponible.');return $dir;
}
function nota_foto_archivo(string $nombre): string {
    if(!preg_match('/\A[a-f0-9]{32}\.webp\z/D',$nombre))throw new RuntimeException('Archivo inválido.');
    $p=notas_directorio().'/'.$nombre;if(is_link($p))throw new RuntimeException('Archivo inválido.');return $p;
}
function nota_foto_preparar(array $u,array $in,array $files): ?array {
    if(!$files)return null;
    if(count($files)!==1||!is_array($files['foto_nota']??null))throw new DomainException('Elegí sólo una foto de la nota.');
    $file=$files['foto_nota'];if(($file['error']??null)===UPLOAD_ERR_NO_FILE)return null;
    if($u['debe_cambiar'])throw new DomainException('Primero cambiá tu PIN.');
    $accion=texto($in,'accion',40,true);$cat=categoria(entero($in['categoria']??''));
    if($cat['codigo']!=='nota'||!in_array($accion,['ingreso','rapida'],true)||($u['rol']==='nino'&&$accion!=='ingreso')||($u['rol']==='admin'&&$accion!=='rapida'))throw new DomainException('Las fotos se adjuntan a una nota del colegio.');
    $actor=(int)$u['id'];$chico=$u['rol']==='nino'?$actor:entero($in['destino']??'');nino($chico);nota_datos($in);
    transaccion(function()use($actor){uno('SELECT id FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$actor]);$n=(int)q('SELECT COUNT(*) FROM '.t('notas_fotos_intentos').' WHERE actor=? AND fecha>?',[$actor,ahora(-3600)])->fetchColumn();if($n>=5)throw new DomainException('Ya probaste subir una foto 5 veces en una hora. Intentá más tarde.');q('INSERT INTO '.t('notas_fotos_intentos').' (actor,fecha) VALUES (?,?)',[$actor,ahora()]);});
    if(!isset($file['error'],$file['size'],$file['tmp_name'])||!is_int($file['error'])||!is_int($file['size'])||!is_string($file['tmp_name']))throw new DomainException('Elegí una foto JPG, PNG o WebP.');
    if(in_array($file['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)||$file['size']>10*1024*1024)throw new DomainException('La foto debe pesar hasta 10 MB.');
    if($file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new DomainException('No se pudo recibir la foto.');
    $tmp=$file['tmp_name'];$bytes=filesize($tmp);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$info=@getimagesize($tmp);$tipos=['image/jpeg'=>IMAGETYPE_JPEG,'image/png'=>IMAGETYPE_PNG,'image/webp'=>IMAGETYPE_WEBP];
    if(!$bytes||$bytes>10*1024*1024||!isset($tipos[$mime])||!$info||$info[2]!==$tipos[$mime])throw new DomainException('Usá una imagen JPG, PNG o WebP válida.');
    if($info[0]<1||$info[1]<1||max($info[0],$info[1])>12000||$info[0]*$info[1]>16000000)throw new DomainException('La foto supera los 16 megapíxeles permitidos.');
    $original=match($mime){'image/jpeg'=>@imagecreatefromjpeg($tmp),'image/png'=>@imagecreatefrompng($tmp),'image/webp'=>@imagecreatefromwebp($tmp)};
    if(!$original)throw new DomainException('La foto está dañada.');$salida=null;$path=null;$ok=false;
    try{
        if($mime==='image/jpeg')$original=imagen_orientar($original,imagen_orientacion_jpeg($tmp));
        $w=imagesx($original);$h=imagesy($original);$factor=min(1,1600/max($w,$h));$nw=max(1,(int)round($w*$factor));$nh=max(1,(int)round($h*$factor));
        $salida=imagecreatetruecolor($nw,$nh);imagefill($salida,0,0,imagecolorallocate($salida,255,255,255));
        if(!imagecopyresampled($salida,$original,0,0,0,0,$nw,$nh,$w,$h))throw new RuntimeException('No se pudo preparar la foto.');
        $nombre=bin2hex(random_bytes(16)).'.webp';$path=nota_foto_archivo($nombre);$mask=umask(0077);try{$stream=fopen($path,'xb');}finally{umask($mask);}
        if(!$stream)throw new RuntimeException('No se pudo guardar la foto.');try{if(!imagewebp($salida,$stream,85)||!fflush($stream))throw new RuntimeException('No se pudo guardar la foto.');}finally{fclose($stream);}
        $ok=true;return ['archivo'=>$nombre,'ancho'=>$nw,'alto'=>$nh,'usuario'=>$chico,'actor'=>$actor];
    }finally{imagedestroy($original);if($salida)imagedestroy($salida);if(!$ok&&$path&&is_file($path))unlink($path);}
}
