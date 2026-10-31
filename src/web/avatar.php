<?php
declare(strict_types=1);
require __DIR__.'/../app/http.php';
require_once __DIR__.'/../app/avatares.php';
$u=sesion();
if (!$u) ir('/');
if ($u['debe_cambiar']) ir('/?vista=cuenta');
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        $mime=strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]));
        if ($mime==='multipart/form-data') {
            if ((int)($_SERVER['CONTENT_LENGTH']??0)>6*1024*1024) { http_response_code(413); exit('La imagen debe pesar hasta 5 MB.'); }
            if (!origen_permitido($_SERVER)) { http_response_code(403); exit('Origen no permitido.'); }
            foreach($_POST as $k=>$v) if (!is_string($v)||strlen($k)>64||strlen($v)>1600) throw new DomainException('Formulario inválido.');
            try { verificar_csrf(texto($_POST,'csrf',64,true)); } catch(DomainException $e) { http_response_code(403); throw $e; }
            $in=$_POST;
            if (texto($in,'accion',40,true)!=='avatar_subir' || count($_FILES)!==1 || !is_array($_FILES['imagen']??null)) throw new DomainException('Elegí una imagen para tu propio avatar.');
            // El dueño siempre sale de la sesión; nunca de un campo enviado.
            if (isset($in['usuario']) && texto($in,'usuario',20,true)!==(string)$u['id']) { http_response_code(403); exit('No podés cambiar otro avatar.'); }
            avatar_subir((int)$u['id'],(int)$u['id'],$_FILES['imagen']);
        } else {
            $in=entrada();
            if (texto($in,'accion',40,true)!=='avatar_quitar') throw new DomainException('Acción no disponible.');
            $id=isset($in['usuario'])?entero($in['usuario']):(int)$u['id'];
            if ($id!==(int)$u['id']) { http_response_code(403); exit('Sólo podés cambiar tu avatar.'); }
            avatar_quitar((int)$u['id'],$id);
        }
        sesion(true); ir('/?vista=avatar&mensaje=guardado');
    } catch(DomainException $e) {
        if (http_response_code()<400) http_response_code(400);
        comenzar('Cambiar avatar',$u); ?><section class="tarjeta"><h1>No pudimos cambiar el avatar</h1><p class="error" role="alert"><?=h($e->getMessage())?></p><a class="boton" href="<?=h(ruta('/'))?>">Volver a mi libreta</a></section><?php terminar(); exit;
    }
}
$id=isset($_GET['usuario'])?entero($_GET['usuario']):(int)$u['id'];
if ($u['rol']!=='admin' && $id!==(int)$u['id']) { http_response_code(403); exit('No podés ver otro avatar.'); }
avatar_autorizar((int)$u['id'],$id); $f=avatar_actual($id);
if (!$f) { http_response_code(404); exit('Sin avatar.'); }
$file=avatar_archivo($f['archivo']); $stream=@fopen($file,'rb');
if (!$stream) { http_response_code(404); exit('Avatar no disponible.'); }
header('Content-Type: image/webp'); header('Content-Disposition: inline; filename="avatar.webp"');
header('Cache-Control: private, no-store, max-age=0'); header('X-Content-Type-Options: nosniff');
header('Content-Length: '.(string)fstat($stream)['size']);
fpassthru($stream); fclose($stream);
