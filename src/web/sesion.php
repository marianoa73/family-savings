<?php
declare(strict_types=1);
require __DIR__.'/../app/http.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $u=sesion();
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        $in=entrada(); $accion=texto($in,'accion',20,true);
        if ($accion==='salir') { cerrar($u); echo '{"ok":true}'; exit; }
        if ($accion==='tema') {
            if (!$u) { http_response_code(401); echo '{"error":"sesión terminada"}'; exit; }
            $tema=texto($in,'tema',6,true);
            if (!in_array($tema,['claro','oscuro'],true)) { http_response_code(400); echo '{"error":"tema inválido"}'; exit; }
            // El destinatario sale exclusivamente de la sesión, nunca del formulario.
            guardar_tema((int)$u['id'],$tema); sesion(true);
            echo json_encode(['ok'=>true,'tema'=>$tema],JSON_THROW_ON_ERROR); exit;
        }
        if ($accion!=='seguir') { http_response_code(400); echo '{"error":"acción"}'; exit; }
        if ($u) $u=sesion(true);
    }
    if (!$u) { http_response_code(401); echo '{"error":"sesión terminada"}'; exit; }
    echo json_encode(['ok'=>true,'visto'=>strtotime($u['visto'].' UTC')*1000,'ahora'=>time()*1000],JSON_THROW_ON_ERROR);
} catch(DomainException $e) { http_response_code(403); echo '{"error":"página vencida"}'; }
