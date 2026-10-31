<?php
declare(strict_types=1);
require_once __DIR__.'/lib.php';
function imagen_orientacion_app1(string $exif): int {
    if (!str_starts_with($exif,"Exif\0\0")) return 1;
    $t=substr($exif,6); $len=strlen($t); $orden=substr($t,0,2);
    if ($len<8 || !in_array($orden,['II','MM'],true)) return 1;
    $u16=static fn(int $p): ?int => $p>=0&&$p+2<=$len ? unpack($orden==='II'?'v':'n',substr($t,$p,2))[1] : null;
    $u32=static fn(int $p): ?int => $p>=0&&$p+4<=$len ? unpack($orden==='II'?'V':'N',substr($t,$p,4))[1] : null;
    if ($u16(2)!==42) return 1;
    $ifd=$u32(4); if ($ifd===null || $ifd<8) return 1;
    $n=$u16($ifd); if ($n===null || $ifd+2+12*$n+4>$len) return 1;
    for($i=0;$i<$n;$i++) {
        $p=$ifd+2+12*$i;
        if ($u16($p)===0x0112 && $u16($p+2)===3 && $u32($p+4)===1) {
            $v=$u16($p+8); return $v!==null&&$v>=1&&$v<=8 ? $v : 1;
        }
    }
    return 1;
}
function imagen_orientacion_jpeg(string $file,bool $usarExtension=true): int {
    if ($usarExtension && function_exists('exif_read_data')) {
        $r=@exif_read_data($file,'IFD0',true,false);
        $v=is_array($r)?($r['IFD0']['Orientation']??null):null;
        if (is_int($v)&&$v>=1&&$v<=8) return $v;
    }
    // APP1 mide como máximo 65533 bytes. No se interpretan GPS, miniaturas ni offsets externos.
    $s=@fopen($file,'rb'); if (!$s) return 1;
    try {
        if (fread($s,2)!=="\xff\xd8") return 1;
        while(!feof($s)) {
            if (fread($s,1)!=="\xff") return 1;
            do { $c=fread($s,1); } while($c==="\xff");
            if ($c==='') return 1; $marker=ord($c);
            if ($marker===0xda||$marker===0xd9) return 1;
            if ($marker===0x01||($marker>=0xd0&&$marker<=0xd7)) continue;
            $raw=fread($s,2); if (strlen($raw)!==2) return 1;
            $n=unpack('n',$raw)[1]-2; if ($n<0) return 1;
            if ($marker===0xe1) {
                $data=$n>0?fread($s,$n):''; if(strlen($data)!==$n) return 1;
                if(str_starts_with($data,"Exif\0\0")) return imagen_orientacion_app1($data);
            } elseif(fseek($s,$n,SEEK_CUR)!==0) return 1;
        }
    } finally { fclose($s); }
    return 1;
}
function imagen_orientar(GdImage $imagen,int $orientacion): GdImage {
    // GD gira en sentido antihorario. Primero espejo horizontal para 2/5/7.
    if (in_array($orientacion,[2,5,7],true) && !imageflip($imagen,IMG_FLIP_HORIZONTAL)) throw new RuntimeException('No se pudo orientar la imagen.');
    if ($orientacion===4 && !imageflip($imagen,IMG_FLIP_VERTICAL)) throw new RuntimeException('No se pudo orientar la imagen.');
    $grados=match($orientacion) {3=>180,5=>90,6=>-90,7=>-90,8=>90,default=>0};
    if ($grados!==0) {
        $girada=imagerotate($imagen,$grados,0xffffff);
        if (!$girada) throw new RuntimeException('No se pudo orientar la imagen.');
        imagedestroy($imagen); return $girada;
    }
    return $imagen;
}

