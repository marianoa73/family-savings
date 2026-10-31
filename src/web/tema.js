/* Tema compartido. Se ejecuta en <head>, sin defer y sin dependencias. */
(function () {
  'use strict';
  var raiz = document.documentElement;
  // El perfil viene en <html> antes de cargar CSS. Nunca heredar otro usuario del navegador.
  var perfil = raiz.getAttribute('data-tema-perfil') === '1';
  var elegido = perfil ? (raiz.getAttribute('data-tema') === 'oscuro' ? 'oscuro' : 'claro') : leer();
  var guardando = false;
  var boton;
  var texto;
  var icono;
  var estado;

  // 20/10/2026: por defecto SIEMPRE claro; sólo cambia si el visitante elige oscuro.
  // Se guarda en localStorage y en una cookie de primera parte (respaldo si el almacenamiento está bloqueado).
  function leer() {
    var valor = null;
    try { valor = window.localStorage.getItem('fs-tema'); } catch (e) { /* sin almacenamiento */ }
    if (valor !== 'oscuro' && valor !== 'claro') {
      var m = null;
      try { m = document.cookie.match(/(?:^|;\s*)fs-tema=(oscuro|claro)/); } catch(e) { /* navegador sin cookies */ }
      valor = m ? m[1] : null;
    }
    return valor === 'oscuro' || valor === 'claro' ? valor : null;
  }
  function guardar(tema) {
    try { window.localStorage.setItem('fs-tema', tema); } catch (e) { /* queda la cookie */ }
    var base = document.querySelector('meta[name="app-base"]');
    try { document.cookie = 'fs-tema=' + tema + '; path=' + ((base && base.content) || '') + '/; max-age=31536000; SameSite=Lax; Secure'; } catch(e) { /* preferencia de perfil sigue disponible */ }
  }
  function sistema() { return 'claro'; }
  function aplicar(tema) {
    raiz.setAttribute('data-tema', tema);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', tema === 'oscuro' ? '#0B1019' : '#F1F4F9');
    if (boton) {
      var oscuro = tema === 'oscuro';
      boton.setAttribute('aria-label', 'Cambiar a modo ' + (oscuro ? 'claro' : 'oscuro'));
      boton.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
      texto.textContent = oscuro ? 'Modo claro' : 'Modo oscuro';
      icono.setAttribute('d', oscuro
        ? 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4'
        : 'M20.8 13A9 9 0 0 1 11 3.2 9 9 0 1 0 20.8 13Z');
    }
  }
  aplicar(elegido || sistema());
  if (perfil) guardar(elegido); // Login sincroniza el navegador con este usuario para la salida posterior.

  async function cambiar() {
    if (guardando) return;
    var nuevo = raiz.getAttribute('data-tema') === 'oscuro' ? 'claro' : 'oscuro';
    if (!perfil) { elegido = nuevo; aplicar(elegido); guardar(elegido); return; }
    guardando = true; boton.disabled = true; boton.setAttribute('aria-busy','true'); estado.textContent = '';
    var controlador = new AbortController();
    var espera = setTimeout(function(){controlador.abort();},10000);
    try {
      var base = document.querySelector('meta[name="app-base"]');
      var csrf = document.querySelector('meta[name="csrf"]');
      var cuerpo = new URLSearchParams({accion:'tema',tema:nuevo,csrf:csrf ? csrf.content : ''});
      var respuesta = await fetch(((base && base.content) || '') + '/sesion.php', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:cuerpo,signal:controlador.signal});
      var datos = await respuesta.json();
      if (!respuesta.ok || datos.ok !== true || datos.tema !== nuevo) throw new Error('No guardado');
      elegido = nuevo; aplicar(elegido); guardar(elegido); estado.textContent = 'Tema guardado.';
    } catch(e) {
      estado.textContent = 'No pudimos guardar el tema. Probá de nuevo o volvé a ingresar.';
    } finally {
      clearTimeout(espera); guardando = false; boton.disabled = false; boton.removeAttribute('aria-busy');
    }
  }

  function iniciar() {
    if (document.getElementById('fs-tema')) return;
    var barra = document.createElement('div');
    barra.className = 'tema-controles';
    boton = document.createElement('button');
    boton.id = 'fs-tema';
    boton.type = 'button';
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '20');
    svg.setAttribute('height', '20');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    icono = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    svg.appendChild(icono);
    texto = document.createElement('span');
    boton.appendChild(svg);
    boton.appendChild(texto);
    boton.addEventListener('click', cambiar);
    barra.appendChild(boton);
    estado = document.createElement('p'); estado.className = 'tema-estado'; estado.setAttribute('role','status'); estado.setAttribute('aria-live','polite'); barra.appendChild(estado);
    var cabecera = document.querySelector('.cabecera');
    if (cabecera) cabecera.appendChild(barra);
    else document.body.insertBefore(barra, document.body.firstChild);
    aplicar(raiz.getAttribute('data-tema'));
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
  else iniciar();
  window.addEventListener('storage', function (evento) {
    if (perfil) return; // Una pestaña de otra cuenta no sustituye la elección autenticada.
    if (evento.key === 'fs-tema' || evento.key === null) {
      elegido = leer();
      aplicar(elegido || sistema());
    }
  });
})();
