/* Sin dependencias. Ningún PIN, mail ni saldo se guarda en el navegador. */
(() => {
  'use strict';
  const base = document.querySelector('meta[name="app-base"]')?.content || '';
  const ruta = sufijo => base + sufijo;
  const pin = document.getElementById('pin');
  for (const boton of document.querySelectorAll('[data-tecla]')) boton.addEventListener('click', () => {
    if (!pin) return;
    const tecla = boton.dataset.tecla;
    if (tecla === 'borrar') pin.value = pin.value.slice(0, -1);
    else if (tecla === 'ver') { pin.type = pin.type === 'password' ? 'text' : 'password'; boton.textContent = pin.type === 'password' ? 'Ver' : 'Ocultar'; }
    else if (pin.value.length < 6) pin.value += tecla;
    pin.dispatchEvent(new Event('input', {bubbles: true}));
  });
  const destino = document.querySelector('[name="destino"]');
  function elegirDestino() {
    if (!destino) return;
    for (const bloque of document.querySelectorAll('[data-monto-usuario]')) {
      const visible = destino.value === bloque.dataset.montoUsuario;
      bloque.hidden = !visible;
      for (const campo of bloque.querySelectorAll('input')) campo.disabled = !visible;
    }
  }
  if (destino) { destino.addEventListener('change', elegirDestino); elegirDestino(); }
  for (const bloque of document.querySelectorAll('[data-premios]')) {
    const select = bloque.querySelector('[data-nota-grado]'), salida = bloque.querySelector('[data-nota-importe]');
    const premios = JSON.parse(bloque.dataset.premios);
    const recalcular = () => { const grado = Number(select.value); salida.textContent = '$ ' + new Intl.NumberFormat('es-AR').format(grado < 7 ? 0 : (premios[grado] || 0)); };
    select.addEventListener('change', recalcular); recalcular();
  }
  for (const boton of document.querySelectorAll('[data-confirmar]')) boton.addEventListener('click', evento => {
    if (!window.confirm(boton.dataset.confirmar)) evento.preventDefault();
  });
  const botonesEnviados = new Set();
  for (const form of document.querySelectorAll('form[method="post"]')) form.addEventListener('submit', () => {
    // Esperar a que el navegador serialice el botón que aporta el estado de una meta.
    setTimeout(() => { for (const boton of form.querySelectorAll('button[type="submit"],button:not([type])')) {
      if (!boton.disabled) { botonesEnviados.add(boton); boton.disabled = true; }
    } }, 0);
  });
  window.addEventListener('pageshow', e => {
    if (e.persisted && document.body.dataset.visto) location.reload();
    for (const boton of botonesEnviados) boton.disabled = false;
    botonesEnviados.clear();
  });
  if (!document.body.dataset.visto) return;
  const csrf = document.querySelector('meta[name="csrf"]').content;
  const aviso = document.getElementById('aviso-sesion');
  let visto = Number(document.body.dataset.visto), desfase = 0, ocupado = false, ultimaPeticion = 0, terminando = false, renovacionPendiente = false;
  async function consultar(renovar = false) {
    if (terminando) return;
    if (ocupado) { if (renovar) renovacionPendiente = true; return; }
    ocupado = true;
    if (renovar) ultimaPeticion = Date.now(); // Sólo interacción, nunca el sondeo pasivo.
    const control = new AbortController();
    const limite = setTimeout(() => control.abort(), 8000);
    try {
      const opciones = {cache: 'no-store', credentials: 'same-origin', redirect: 'error', signal: control.signal};
      if (renovar) Object.assign(opciones, {method: 'POST', body: new URLSearchParams({accion: 'seguir', csrf})});
      const respuesta = await fetch(ruta('/sesion.php'), opciones);
      if (respuesta.status === 401) { location.replace(ruta('/?mensaje=inactividad')); return; }
      if (!respuesta.ok) return;
      const datos = await respuesta.json();
      visto = datos.visto; desfase = datos.ahora - Date.now();
      if (aviso) aviso.hidden = Date.now() + desfase - visto < 540000;
    } catch (_) { /* No renovar localmente sin confirmación del servidor. */ }
    finally {
      clearTimeout(limite); ocupado = false;
      if (renovacionPendiente && !terminando) { renovacionPendiente = false; consultar(true); }
    }
  }
  async function terminarSesion() {
    if (terminando) return;
    terminando = true;
    // El servidor ya rechaza sesiones ociosas aunque falle esta petición o no haya JS.
    try { await Promise.race([fetch(ruta('/sesion.php'), {method:'POST', credentials:'same-origin', body:new URLSearchParams({accion:'salir', csrf}), keepalive:true}), new Promise(resolve => setTimeout(resolve, 1500))]); }
    finally { location.replace(ruta('/?mensaje=inactividad')); }
  }
  document.getElementById('seguir')?.addEventListener('click', () => consultar(true));
  for (const evento of ['pointerdown', 'keydown']) document.addEventListener(evento, e => {
    if (e.isTrusted && Date.now() - ultimaPeticion > 30000 && !document.hidden) consultar(true);
  }, {passive:true});
  document.addEventListener('visibilitychange', () => { if (!document.hidden) consultar(); });
  setInterval(() => {
    const tiempo = Date.now() + desfase - visto;
    if (tiempo >= 600000) terminarSesion();
    else if (aviso) aviso.hidden = tiempo < 540000;
  }, 1000);
  setInterval(() => consultar(), 30000); // Lectura pasiva: no renueva la sesión.
  consultar();
})();
