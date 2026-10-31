<?php
declare(strict_types=1);
require __DIR__.'/../app/http.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');exit;}
$u=sesion();if(!$u){http_response_code(401);exit('Ingresá para ver la foto.');}
if($u['debe_cambiar']){http_response_code(403);exit('Primero cambiá tu PIN.');}
$id=entero($_GET['id']??'');$f=uno('SELECT * FROM '.t('notas_fotos').' WHERE id=?',[$id]);
if(!$f){http_response_code(404);exit('Foto no disponible.');}
if($u['rol']!=='admin'&&(int)$f['usuario']!==(int)$u['id']){http_response_code(403);exit('No podés ver esa foto.');}
// Token de lectura por foto derivado del CSRF: no expone el token de formularios en la URL.
if(!hash_equals(hash_hmac('sha256','nota-foto:'.$id,csrf()),get_texto('token',64))){http_response_code(403);exit('Abrí la foto desde tu libreta.');}
$p=nota_foto_archivo($f['archivo']);$s=@fopen($p,'rb');if(!$s){http_response_code(404);exit('Foto no disponible.');}
header('Content-Type: image/webp');header('Content-Disposition: inline; filename="nota.webp"');header('Cache-Control: private, no-store, max-age=0');header('X-Content-Type-Options: nosniff');header('Content-Length: '.(string)fstat($s)['size']);fpassthru($s);fclose($s);
