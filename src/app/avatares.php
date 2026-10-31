<?php
declare(strict_types=1);
// Orientación compartida por avatares y comprobantes.
require_once __DIR__.'/imagenes.php';
function avatar_actual(int $id): ?array {return uno('SELECT * FROM '.t('avatares').' WHERE usuario=?',[$id]);}
function avatar_autorizar(int $actor,int $id,bool $editar=false): array {
    $a=uno('SELECT * FROM '.t('usuarios').' WHERE id=? AND activo=1',[$actor]);
    $u=uno('SELECT * FROM '.t('usuarios').' WHERE id=? AND activo=1',[$id]);
    if(!$a||!$u||$a['debe_cambiar']||($editar?$actor!==$id:($a['rol']!=='admin'&&$actor!==$id)))throw new DomainException('Avatar no disponible para esta cuenta.');
    return $u;
}
function avatar_directorio(): string {
    $dir=datos().'/avatares';if(is_link($dir))throw new RuntimeException('Directorio inválido.');
    if(!is_dir($dir)){$old=umask(0077);try{if(!@mkdir($dir,0700)&&!is_dir($dir))throw new RuntimeException('No se pudo crear el directorio.');}finally{umask($old);}}
    if(realpath($dir)!==$dir||!is_writable($dir))throw new RuntimeException('Directorio privado no disponible.');return $dir;
}
function avatar_archivo(string $nombre): string {
    if(!preg_match('/\A[a-f0-9]{32}\.webp\z/D',$nombre))throw new RuntimeException('Archivo inválido.');
    $file=avatar_directorio().'/'.$nombre;if(is_link($file))throw new RuntimeException('Archivo inválido.');return $file;
}
function avatar_borrar_archivo(string $nombre): void {
    $file=avatar_archivo($nombre);if(is_file($file)&&!unlink($file))throw new RuntimeException('No se pudo borrar el avatar anterior.');
}
function avatar_subir(int $actor,int $id,array $file): void {
    avatar_autorizar($actor,$id,true);
    transaccion(function()use($id){
        uno('SELECT id FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$id]);
        $n=(int)q('SELECT COUNT(*) FROM '.t('avatares_eventos')." WHERE usuario=? AND accion='intento' AND fecha>?",[$id,ahora(-3600)])->fetchColumn();
        if($n>=5)throw new DomainException('Ya probaste subir un avatar 5 veces en una hora. Intentá más tarde.');
        q('INSERT INTO '.t('avatares_eventos').' (usuario,accion,fecha) VALUES (?,?,?)',[$id,'intento',ahora()]);
    });
    if(!isset($file['error'],$file['size'],$file['tmp_name'])||!is_int($file['error'])||!is_int($file['size'])||!is_string($file['tmp_name']))throw new DomainException('Elegí una imagen JPG, PNG o WebP.');
    if(in_array($file['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)||$file['size']>5*1024*1024)throw new DomainException('La imagen debe pesar hasta 5 MB.');
    if($file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new DomainException('No se pudo recibir la imagen.');
    $tmp=$file['tmp_name'];$size=filesize($tmp);if($size===false||$size<1||$size>5*1024*1024)throw new DomainException('La imagen debe pesar hasta 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$info=@getimagesize($tmp);$tipos=['image/jpeg'=>IMAGETYPE_JPEG,'image/png'=>IMAGETYPE_PNG,'image/webp'=>IMAGETYPE_WEBP];
    if(!isset($tipos[$mime])||!$info||$info[2]!==$tipos[$mime])throw new DomainException('Usá JPG, PNG o WebP; no SVG, GIF ni HEIC.');
    if($info[0]<1||$info[1]<1||$info[0]>12000||$info[1]>12000||$info[0]*$info[1]>16000000)throw new DomainException('La imagen supera los 16 megapíxeles permitidos.');
    if(!function_exists('imagewebp'))throw new RuntimeException('GD con WebP requerido.');
    $original=match($mime){'image/jpeg'=>@imagecreatefromjpeg($tmp),'image/png'=>@imagecreatefrompng($tmp),'image/webp'=>@imagecreatefromwebp($tmp)};
    if(!$original)throw new DomainException('La imagen está dañada.');
    $salida=null;$path=null;$guardado=false;$nombre=bin2hex(random_bytes(16)).'.webp';
    try{
        if($mime==='image/jpeg')$original=imagen_orientar($original,imagen_orientacion_jpeg($tmp));
        $w=imagesx($original);$h=imagesy($original);$lado=min($w,$h);$x=intdiv($w-$lado,2);$y=intdiv($h-$lado,2);
        $salida=imagecreatetruecolor(256,256);imagefill($salida,0,0,imagecolorallocate($salida,255,255,255));
        if(!imagecopyresampled($salida,$original,0,0,$x,$y,256,256,$lado,$lado))throw new RuntimeException('No se pudo recortar el avatar.');
        $path=avatar_archivo($nombre);$old=umask(0077);try{$s=fopen($path,'xb');}finally{umask($old);}
        if(!$s)throw new RuntimeException('No se pudo guardar el avatar.');
        try{if(!imagewebp($salida,$s,85)||!fflush($s))throw new RuntimeException('No se pudo codificar el avatar.');}finally{fclose($s);}
        if(filesize($path)<1)throw new RuntimeException('Avatar vacío.');
        $prev=transaccion(function()use($actor,$id,$nombre){
            uno('SELECT id FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$id]);avatar_autorizar($actor,$id,true);$prev=avatar_actual($id);
            q('INSERT INTO '.t('avatares').' (usuario,archivo,actualizado) VALUES (?,?,?) ON DUPLICATE KEY UPDATE archivo=VALUES(archivo),actualizado=VALUES(actualizado)',[$id,$nombre,ahora()]);
            q('INSERT INTO '.t('avatares_eventos').' (usuario,accion,fecha) VALUES (?,?,?)',[$id,'subir',ahora()]);return $prev;
        });$guardado=true;if($prev)avatar_borrar_archivo($prev['archivo']);
    }finally{imagedestroy($original);if($salida)imagedestroy($salida);if(!$guardado&&$path&&is_file($path))unlink($path);}
}
function avatar_quitar(int $actor,int $id): void {
    avatar_autorizar($actor,$id,true);
    $prev=transaccion(function()use($actor,$id){
        uno('SELECT id FROM '.t('usuarios').' WHERE id=? FOR UPDATE',[$id]);avatar_autorizar($actor,$id,true);$prev=avatar_actual($id);
        q('DELETE FROM '.t('avatares').' WHERE usuario=?',[$id]);if($prev)q('INSERT INTO '.t('avatares_eventos').' (usuario,accion,fecha) VALUES (?,?,?)',[$id,'quitar',ahora()]);return $prev;
    });if($prev)avatar_borrar_archivo($prev['archivo']);
}
