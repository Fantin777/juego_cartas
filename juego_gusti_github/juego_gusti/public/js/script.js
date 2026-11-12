// =====================================================================
//  TRUCO ARGENTINO - script.js
//
//  CAMBIO DE ARQUITECTURA (para que los puntos no se puedan trucar):
//  El motor del juego (clases Carta, Mazo, Jugador, EstadoTruco,
//  EstadoEnvido, EstrategiaIA y Arbitro) se movió a PHP:
//      motor/Arbitro.php, motor/EstrategiaIA.php, motor/Cartas.php
//  con las mismas reglas y los mismos nombres de métodos. El navegador ya
//  no baraja, no conoce las cartas de Tata y no decide quién gana.
//
//  Acá queda:
//   - ClienteAPI ........ fetch() a la API REST del servidor
//                         (rutas en routes/api.php, guía en docs/POSTMAN.md)
//   - ArbitroRemoto ..... misma interfaz que usaba ControladorUI del
//                         Arbitro original, pero cada acción va al servidor
//                         y se reproducen los eventos con las demoras de
//                         siempre (según la velocidad configurada).
//   - ControladorUI ..... igual que antes (+ ranking y fin de partido)
//   - Aplicacion ........ fachada para los onclick del HTML
// =====================================================================

// ===== RUTAS DEL SERVIDOR =====
// Las escribe el servidor en las etiquetas <meta> de views/juego.php, así
// el juego funciona en cualquier carpeta y con o sin mod_rewrite.
const meta = (nombre, porDefecto) => document.querySelector(`meta[name="${nombre}"]`)?.content || porDefecto;
const RUTAS = {
  api: meta('api-base', 'api').replace(/\/$/, ''),
  login: meta('login-url', 'login'),
  imagenes: meta('img-base', 'public/img/cartas/'),
};

// ===== CONFIGURACIÓN Y CARTAS =====
const IMAGENES_CARTAS = {
  '1espada': 'macho.png', '2espada': '2deespada.png', '3espada': '3deespada.png',
  '4espada': '4deespada.png', '5espada': '5deespada.png', '6espada': '6deespada.png',
  '7espada': '7deespada.png', '10espada': '10deespada.png', '11espada': '11deespada.png',
  '12espada': '12deespada.png', '1basto': 'hembra.png', '2basto': '2debasto.png',
  '3basto': '3debasto.png', '4basto': '4debasto.png', '5basto': '5debasto.png',
  '6basto': '6debasto.png', '7basto': '7debasto.png', '10basto': '10debasto.png',
  '11basto': '11debasto.png', '12basto': '12debasto.png', '1oro': '1falsodeoro.png',
  '2oro': '2deoro.png', '3oro': '3deoro.png', '4oro': '4deoro.png', '5oro': '5deoro.png',
  '6oro': '6deoro.png', '7oro': '7deoro.png', '10oro': '10deoro.png', '11oro': '11deoro.png',
  '12oro': '12deoro.png', '1copa': '1falsodecopa.png', '2copa': '2decopa.png',
  '3copa': '3decopa.png', '4copa': '4decopa.png', '5copa': '5decopa.png', '6copa': '6decopa.png',
  '7copa': '7decopa.png', '10copa': '10decopa.png', '11copa': '11decopa.png',
  '12copa': '12decopa.png', 'dorso': 'dorso.png'
};
// Todas las imágenes están en public/img/cartas/
Object.keys(IMAGENES_CARTAS).forEach(k => { IMAGENES_CARTAS[k] = RUTAS.imagenes + IMAGENES_CARTAS[k]; });
// Si dorso.png no existe se usa el dorso violeta de respaldo. Se recuerda
// el fallo para no volver a pedir la imagen en cada carta de Tata.
let dorsoDisponible = true;

const simbolosPalos = { espada: '⚔', basto: '🏒', oro: '💰', copa: '🏆' };
const coloresPalos = { oro: '#D4AF37', copa: '#D4AF37', espada: '#2C1810', basto: '#2C1810' };

// ===== CONFIG GLOBAL =====
let config = { nombreJugador: 'Vos', nombreOponente: 'Tata', puntosObjetivo: 15, velocidad: 'normal' };
const delay = () => ({ rapido: 800, lento: 2500, normal: 1500 }[config.velocidad]);
const esperar = (ms) => new Promise(r => setTimeout(r, ms));

// ==========================
// COMUNICACIÓN CON EL SERVIDOR
// ==========================
class ErrorAPI extends Error {
  constructor(mensaje, datos = {}, status = 0) { super(mensaje); this.datos = datos; this.status = status; }
}

class ClienteAPI {
  constructor() {
    this.csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  async _procesar(resp) {
    let datos = null;
    try { datos = await resp.json(); } catch { datos = null; }
    if (resp.status === 401 || datos?.login) {
      // Sesión vencida: al login.
      window.location.href = RUTAS.login;
      throw new ErrorAPI('Tu sesión expiró.', datos || {}, 401);
    }
    if (!resp.ok || !datos?.ok) {
      throw new ErrorAPI(datos?.error || 'No se pudo conectar con el servidor.', datos || {}, resp.status);
    }
    return datos;
  }

  /**
   * Petición genérica a la API.
   *   GET    → consultar        (sin cuerpo)
   *   POST   → ejecutar acción  (cuerpo JSON + X-CSRF-Token)
   *   DELETE → abandonar        (X-CSRF-Token)
   */
  async pedir(metodo, ruta, datos = null) {
    const opciones = { method: metodo, credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } };
    if (metodo !== 'GET') opciones.headers['X-CSRF-Token'] = this.csrf;
    if (datos) {
      opciones.headers['Content-Type'] = 'application/json';
      opciones.body = JSON.stringify(datos);
    }
    let resp;
    try {
      resp = await fetch(`${RUTAS.api}/${ruta}`, opciones);
    } catch {
      throw new ErrorAPI('Sin conexión con el servidor. ¿Está encendido WampServer?');
    }
    return this._procesar(resp);
  }

  ranking() { return this.pedir('GET', 'ranking'); }
}

// ==========================
// ÁRBITRO REMOTO
// Reemplaza al Arbitro local. Mantiene los nombres de métodos que usa
// ControladorUI (jugarCarta, cantarTruco, responderTrucoDelJugador,
// cantarEnvidoJugador, irseAlMazo, iniciarMano, obtenerEstado,
// juegoIniciado) para que la UI casi no cambie.
// ==========================
class ArbitroRemoto {
  constructor({ api, onMessage = () => {}, onUpdate = () => {}, onFinPartido = () => {}, onPuntosUsuario = () => {}, onError = () => {} } = {}) {
    this.api = api;
    this.onMessage = onMessage; this.onUpdate = onUpdate;
    this.onFinPartido = onFinPartido; this.onPuntosUsuario = onPuntosUsuario; this.onError = onError;
    this.estado = null;       // último estado PÚBLICO recibido del servidor
    this.ocupado = false;     // true mientras se espera/reproduce una respuesta
  }

  // La UI consulta esto antes de dejar jugar una carta: mientras se
  // reproducen las jugadas de Tata no se aceptan clics.
  get juegoIniciado() { return !!this.estado?.juegoIniciado && !this.ocupado; }

  get hayPartidaEnCurso() { return !!this.estado && !this.estado.partidoTerminado; }

  obtenerEstado() { return this.estado; }

  async _enviar(metodo, ruta, datos = null) {
    if (this.ocupado) return null;
    this.ocupado = true;
    try {
      const resp = await this.api.pedir(metodo, ruta, datos);
      await this._reproducir(resp.eventos || []);
      if (resp.usuario) this.onPuntosUsuario(resp.usuario.puntos);
      if (resp.resultado) this.onFinPartido(resp.resultado, this.estado);
      return resp;
    } catch (err) {
      if (err.status !== 401) {
        this.onError(err.message);
        if (err.datos?.estado) { this.estado = err.datos.estado; }
      }
      return null;
    } finally {
      this.ocupado = false;
      if (this.estado) this.onUpdate(this.estado);
    }
  }

  // Reproduce los eventos del servidor con las mismas pausas que tenían
  // los setTimeout del motor original.
  async _reproducir(eventos) {
    for (const ev of eventos) {
      if (ev.pausa > 0) await esperar(ev.pausa * delay());
      if (ev.mensaje) this.onMessage(ev.mensaje);
      this.estado = ev.estado;
      this.onUpdate(this.estado);
    }
  }

  // ===== Acciones (cada una es un endpoint de routes/api.php) =====
  nuevaPartida() {
    this.estado = null;
    return this._enviar('POST', 'partidas', {
      objetivo: config.puntosObjetivo,
      nombreJugador: config.nombreJugador,
      nombreOponente: config.nombreOponente
    });
  }
  reanudar() { return this._enviar('GET', 'partidas/actual'); }
  iniciarMano() { return this._enviar('POST', 'partidas/actual/manos'); }
  jugarCarta(jugadorKey, carta) {
    if (jugadorKey !== 'jugador' || !carta) return;
    return this._enviar('POST', 'partidas/actual/cartas', { carta: carta.id });
  }
  cantarTruco(by = 'jugador') { if (by === 'jugador') return this._enviar('POST', 'partidas/actual/truco'); }
  responderTrucoDelJugador(accion) { return this._enviar('POST', 'partidas/actual/truco/respuesta', { respuesta: accion }); }
  cantarEnvidoJugador(tipo) { return this._enviar('POST', 'partidas/actual/envido', { tipo }); }
  // Responder un envido que cantó Tata (quiero / no quiero / subir)
  responderEnvidoDelJugador(respuesta) { return this._enviar('POST', 'partidas/actual/envido/respuesta', { respuesta }); }
  irseAlMazo(by = 'jugador') { if (by === 'jugador') return this._enviar('POST', 'partidas/actual/mazo'); }
}

// ==========================
// UI ORIENTADA A OBJETOS
// ==========================
class ControladorUI {
  constructor(engine) {
    this.engine = engine;
    this.estadoUI = { cartaArrastrada: null };
    this.cacheDOM();
    this.wireDragDrop();
    this.wireExtraButtons();
  }

  cacheDOM() {
    this.dom = {
      menuPrincipal: document.getElementById('menuPrincipal'),
      areaJuego: document.getElementById('area-juego'),
      panelReglas: document.getElementById('panel-reglas'),
      panelConfig: document.getElementById('panel-config'),
      panelCreditos: document.getElementById('panel-creditos'),
      panelRanking: document.getElementById('panel-ranking'),
      nombreJugadorInput: document.getElementById('nombre-jugador'),
      nombreOponenteInput: document.getElementById('nombre-oponente'),
      puntosObjetivoSelect: document.getElementById('puntos-objetivo'),
      velocidadJuegoSelect: document.getElementById('velocidad-juego'),
      nombreJugadorDisplay: document.getElementById('nombre-jugador-display'),
      nombreTataDisplay: document.getElementById('nombre-tata-display'),
      labelJugadorJugadas: document.getElementById('label-jugador-jugadas'),
      labelTataJugadas: document.getElementById('label-tata-jugadas'),
      manoTata: document.getElementById('mano-tata'),
      manoJugador: document.getElementById('mano-jugador'),
      puntosTata: document.getElementById('puntos-tata'),
      puntosJugador: document.getElementById('puntos-jugador'),
      mesa: document.getElementById('mesa'),
      mensaje: document.getElementById('mensaje'),
      ronda: document.getElementById('ronda'),
      rondasInfo: document.getElementById('rondas-info'),
      trucoInfo: document.getElementById('truco-info'),
      envidoInfo: document.getElementById('envido-info'),
      cartasTata: document.getElementById('cartas-tata'),
      cartasJugador: document.getElementById('cartas-jugador'),
      cartasTataJugadas: document.getElementById('cartas-tata-jugadas'),
      cartasJugadorJugadas: document.getElementById('cartas-jugador-jugadas'),
      controles: document.getElementById('controles'),
      instruccion: document.getElementById('instruccion'),
      menuPausa: document.getElementById('menu-pausa'),
      btnSalir: document.getElementById('btnSalir'),
      btnTruco: document.getElementById('botonTruco'),
      // NUEVO
      btnContinuar: document.getElementById('btnContinuar'),
      misPuntos: document.getElementById('mis-puntos'),
      rankingYo: document.getElementById('ranking-yo'),
      rankingContenido: document.getElementById('ranking-contenido'),
      modalFin: document.getElementById('modal-fin'),
      finTitulo: document.getElementById('fin-titulo'),
      finDetalle: document.getElementById('fin-detalle'),
      finPuntos: document.getElementById('fin-puntos'),
      aviso: document.getElementById('aviso'),
    };
  }

  wireExtraButtons() {
    if (this.dom.btnTruco) this.dom.btnTruco.addEventListener('click', () => { if (this.engine?.juegoIniciado) this.engine.cantarTruco('jugador'); });
    if (this.dom.btnSalir) this.dom.btnSalir.addEventListener('click', () => window.app.volverAlMenuDesdeJuego());
  }

  actualizarMensaje(texto) { this.dom.mensaje.textContent = texto; }

  renderizarCarta(carta, oculta = false, draggable = false) {
    const div = document.createElement('div'); div.className = 'carta';
    if (oculta) {
      if (IMAGENES_CARTAS.dorso && dorsoDisponible) {
        const img = document.createElement('img'); img.src = IMAGENES_CARTAS.dorso; img.className = 'carta-imagen';
        img.onerror = () => { dorsoDisponible = false; img.style.display = 'none'; div.className += ' carta-oculta'; div.innerHTML = '✨'; };
        div.appendChild(img);
      } else { div.className += ' carta-oculta'; div.innerHTML = '✨'; }
      return div;
    }
    const key = `${carta.valor}${carta.palo}`;
    if (IMAGENES_CARTAS[key]) {
      const img = document.createElement('img');
      img.src = IMAGENES_CARTAS[key]; img.className = 'carta-imagen'; img.alt = `${carta.valor} de ${carta.palo}`;
      img.onerror = () => { img.style.display = 'none'; this.renderizarCartaDefecto(div, carta); };
      div.appendChild(img);
    } else { this.renderizarCartaDefecto(div, carta); }

    if (draggable) {
      this._wireCardDragEvents(div, carta);
    }
    return div;
  }

  renderizarCartaDefecto(div, carta) {
    const color = coloresPalos[carta.palo]; const simbolo = simbolosPalos[carta.palo];
    div.className += ' carta-visible';
    div.innerHTML = `
      <div class="carta-contenido">
        <div class="carta-valor-top" style="color:${color}"><div>${carta.valor}</div><div>${simbolo}</div></div>
        <div class="carta-simbolo-centro">${simbolo}</div>
        <div class="carta-simbolo-fondo" style="color:${color}">${simbolo}</div>
        <div class="carta-valor-bottom" style="color:${color}"><div>${carta.valor}</div><div>${simbolo}</div></div>
      </div>`;
  }

  actualizarUIConSnapshot(snap) {
    if (!snap) return;
    const d = this.dom;
    d.puntosTata.textContent = snap.tata.puntos;
    d.puntosJugador.textContent = snap.jugador.puntos;
    d.manoTata.style.display = snap.quienEsMano === 'tata' ? 'block' : 'none';
    d.manoJugador.style.display = snap.quienEsMano === 'jugador' ? 'block' : 'none';
    d.ronda.textContent = snap.ronda;

    const rondasInfo = snap.ganadorRondas.map((g, i) => `R${i+1}: ${g === 'jugador' ? '✓ Vos' : g === 'tata' ? `✗ ${snap.tata.nombre}` : '='}`).join(' ');
    d.rondasInfo.textContent = rondasInfo;

    if (snap.truco.nivel > 0) {
      const nombres = ['', 'TRUCO', 'RETRUCO', 'VALE 4'];
      d.trucoInfo.innerHTML = `<div class="truco-badge">¡${nombres[snap.truco.nivel]} CANTADO! (Vale ${Number(snap.truco.valor)} puntos)</div>`;
    } else { d.trucoInfo.innerHTML = ''; }

    // NUEVO: envido en juego
    if (snap.envido.esperandoRespuesta && snap.envido.cadena?.length) {
      const nombres = { envido: 'ENVIDO', realenvido: 'REAL ENVIDO', faltaenvido: 'FALTA ENVIDO' };
      d.envidoInfo.innerHTML = '';
      const badge = document.createElement('div'); badge.className = 'truco-badge envido-badge';
      badge.textContent = `${snap.envido.cadena.map(t => nombres[t]).join(' + ')} (${snap.envido.puntosEnJuego} pts)`;
      d.envidoInfo.appendChild(badge);
    } else { d.envidoInfo.innerHTML = ''; }

    // El servidor solo informa CUÁNTAS cartas tiene Tata, nunca cuáles.
    d.cartasTata.innerHTML = ''; for (let i = 0; i < snap.tata.cantidadMano; i++) d.cartasTata.appendChild(this.renderizarCarta({}, true));
    d.cartasJugador.innerHTML = ''; snap.jugador.mano.forEach(c => d.cartasJugador.appendChild(this.renderizarCarta(c, false, true)));
    d.cartasTataJugadas.innerHTML = ''; snap.tata.jugadas.forEach(c => d.cartasTataJugadas.appendChild(this.renderizarCarta(c)));
    d.cartasJugadorJugadas.innerHTML = ''; snap.jugador.jugadas.forEach(c => d.cartasJugadorJugadas.appendChild(this.renderizarCarta(c)));

    d.instruccion.textContent = (snap.turno === 'jugador' && snap.juegoIniciado && snap.jugador.mano.length > 0 && !snap.truco.esperandoRespuesta && !snap.envido.esperandoRespuesta) ? '🖐 Arrastrá o clickeá una carta para jugarla' : '';

    this.actualizarControlesConSnapshot(snap);
  }

  actualizarControlesConSnapshot(snap) {
    const el = this.dom.controles;
    el.innerHTML = '';

    // NUEVO: partido terminado -> otra partida o volver al menú
    if (snap.partidoTerminado) {
      const btnN = document.createElement('button'); btnN.className = 'btn-nueva-mano'; btnN.textContent = '🔄 Nueva Partida'; btnN.onclick = () => window.app.iniciarPartida(); el.appendChild(btnN);
      const btnM = document.createElement('button'); btnM.className = 'btn-reiniciar'; btnM.textContent = '🏠 Menú principal'; btnM.onclick = () => window.app.volverAlMenuDesdeJuego(); el.appendChild(btnM);
      return;
    }

    if (!snap.juegoIniciado) {
      const btn = document.createElement('button'); btn.className = 'btn-nueva-mano'; btn.textContent = 'Nueva Mano'; btn.onclick = () => window.app.iniciarJuego(); el.appendChild(btn);
      if (snap.jugador.puntos > 0 || snap.tata.puntos > 0) {
        // Antes ponía el marcador en 0 en el navegador. Ahora empieza un
        // partido nuevo en el servidor (el actual queda como abandonado).
        const btnR = document.createElement('button'); btnR.className = 'btn-reiniciar'; btnR.textContent = 'Reiniciar Estadísticas';
        btnR.onclick = () => { if (confirm('¿Empezar un partido nuevo desde 0? El actual se da por abandonado y no suma puntos.')) window.app.iniciarPartida(); };
        el.appendChild(btnR);
      }
      return;
    }

    // NUEVO: Tata te cantó envido -> Quiero / No quiero / subir
    if (snap.envido.esperandoRespuesta && snap.envido.quienCanto === 'tata') {
      const btnQ = document.createElement('button'); btnQ.className = 'btn-quiero'; btnQ.textContent = '✓ QUIERO'; btnQ.onclick = () => this.engine.responderEnvidoDelJugador('quiero'); el.appendChild(btnQ);
      const btnN = document.createElement('button'); btnN.className = 'btn-no-quiero'; btnN.textContent = '✗ NO QUIERO'; btnN.onclick = () => this.engine.responderEnvidoDelJugador('noquiero'); el.appendChild(btnN);
      (snap.envido.subidas || []).forEach((tipo) => {
        const btn = document.createElement('button');
        btn.className = `btn-${tipo === 'realenvido' ? 'real-' : tipo === 'faltaenvido' ? 'falta-' : ''}envido`;
        btn.textContent = tipo === 'realenvido' ? '¡Real Envido!' : tipo === 'faltaenvido' ? '¡Falta Envido!' : '¡Envido!';
        btn.onclick = () => this.engine.responderEnvidoDelJugador(tipo); el.appendChild(btn);
      });
      const btnM = document.createElement('button'); btnM.className = 'btn-mazo'; btnM.textContent = '🏳 Me voy al mazo'; btnM.onclick = () => this.engine.irseAlMazo('jugador'); el.appendChild(btnM);
      return;
    }

    if (snap.truco.esperandoRespuesta && snap.truco.quienCanto === 'tata') {
      const btnQ = document.createElement('button'); btnQ.className = 'btn-quiero'; btnQ.textContent = '✓ QUIERO'; btnQ.onclick = () => this.engine.responderTrucoDelJugador('aceptar'); el.appendChild(btnQ);
      const btnN = document.createElement('button'); btnN.className = 'btn-no-quiero'; btnN.textContent = '✗ NO QUIERO'; btnN.onclick = () => this.engine.responderTrucoDelJugador('rechazar'); el.appendChild(btnN);
      // CORRECCIÓN: antes el botón aparecía también ante un "Vale 4" y
      // provocaba un error; ahora solo si se puede subir.
      const nivelCantado = snap.truco.paraResponder?.nivel ?? snap.truco.nivel;
      if (nivelCantado < 3) {
        const btnR = document.createElement('button'); btnR.className = 'btn-truco';
        btnR.textContent = ['','','¡Retruco!','¡Vale 4!'][nivelCantado + 1] || '¡Subir!';
        btnR.onclick = () => this.engine.responderTrucoDelJugador('subir'); el.appendChild(btnR);
      }
      return;
    }

    if (snap.juegoIniciado) {
      const puedeCantar = snap.truco.nivel === 0 || snap.truco.ultimoQueCanto === 'tata';
      if (snap.truco.nivel < 3 && puedeCantar) {
        const btn = document.createElement('button'); btn.className = 'btn-truco';
        // CORRECCIÓN: la lista estaba corrida un lugar; con el Truco ya
        // querido el botón decía "¡Truco!" en vez de "¡Retruco!".
        btn.textContent = ['¡Truco!', '¡Retruco!', '¡Vale 4!'][snap.truco.nivel];
        btn.disabled = snap.ganadorRondas.length >= 3 || (snap.jugador.jugadas.length === 0 && snap.tata.jugadas.length === 0);
        btn.onclick = () => this.engine.cantarTruco('jugador'); el.appendChild(btn);
      }
      const btnM = document.createElement('button'); btnM.className = 'btn-mazo'; btnM.textContent = '🏳 Me voy al mazo'; btnM.onclick = () => this.engine.irseAlMazo('jugador'); el.appendChild(btnM);

      if (snap.envido.jugadorPuedeCantar) {
        ['envido', 'realenvido', 'faltaenvido'].forEach((tipo) => {
          const btn = document.createElement('button');
          btn.className = `btn-${tipo === 'realenvido' ? 'real-' : tipo === 'faltaenvido' ? 'falta-' : ''}envido`;
          btn.textContent = tipo === 'realenvido' ? '¡Real Envido!' : tipo === 'faltaenvido' ? '¡Falta Envido!' : '¡Envido!';
          btn.onclick = () => this.engine.cantarEnvidoJugador(tipo); el.appendChild(btn);
        });
      }
    }
  }

  // ===== Paneles y Menús (UI) =====
  mostrarPanel(panel) {
    this.dom.menuPrincipal.style.display = 'none';
    document.getElementById(`panel-${panel}`).style.display = 'block';
    if (panel === 'config') this.cargarConfig();
    if (panel === 'ranking') this.cargarRanking();
  }

  volverAlMenu() {
    ['reglas', 'config', 'creditos', 'ranking'].forEach(p => document.getElementById(`panel-${p}`).style.display = 'none');
    this.dom.menuPrincipal.style.display = 'block';
    this.actualizarBotonContinuar();
  }

  _mostrarAreaJuego() {
    ['reglas', 'config', 'creditos', 'ranking'].forEach(p => document.getElementById(`panel-${p}`).style.display = 'none');
    this.dom.modalFin.style.display = 'none';
    this.dom.menuPausa.style.display = 'none';
    this.dom.menuPrincipal.style.display = 'none';
    this.dom.areaJuego.style.display = 'block';
    document.body.classList.add('jugando');
  }

  _limpiarMesa(mensaje) {
    const d = this.dom;
    d.puntosTata.textContent = 0; d.puntosJugador.textContent = 0;
    d.cartasTata.innerHTML = ''; d.cartasJugador.innerHTML = '';
    d.cartasTataJugadas.innerHTML = ''; d.cartasJugadorJugadas.innerHTML = '';
    d.controles.innerHTML = ''; d.trucoInfo.innerHTML = ''; d.rondasInfo.textContent = '';
    d.ronda.textContent = 0; d.instruccion.textContent = '';
    this.actualizarMensaje(mensaje);
  }

  async iniciarPartida() {
    this._mostrarAreaJuego();
    this.actualizarNombres();
    this._limpiarMesa('Repartiendo…');
    const resp = await window.app.motor.nuevaPartida();
    if (!resp) this.volverAlMenuDesdeJuego();
  }

  // NUEVO: retomar la partida guardada en el servidor
  async continuarPartida() {
    this._mostrarAreaJuego();
    this._limpiarMesa('Recuperando partida…');
    const resp = await window.app.motor.reanudar();
    if (!resp || !resp.partida) {
      if (resp) document.body.dataset.partidaEnCurso = '0';
      this.mostrarAviso('No tenés una partida en curso.');
      this.volverAlMenuDesdeJuego();
      return;
    }
    const snap = window.app.motor.obtenerEstado();
    if (snap) {
      this.dom.nombreJugadorDisplay.textContent = snap.jugador.nombre;
      this.dom.nombreTataDisplay.textContent = snap.tata.nombre;
      this.dom.labelJugadorJugadas.textContent = snap.jugador.nombre + ':';
      this.dom.labelTataJugadas.textContent = snap.tata.nombre + ':';
    }
  }

  mostrarMenuPausa() { this.dom.menuPausa.style.display = 'flex'; }
  continuarJuego() { this.dom.menuPausa.style.display = 'none'; }
  volverAlMenuDesdeJuego() {
    this.dom.menuPausa.style.display = 'none'; this.dom.modalFin.style.display = 'none';
    this.dom.areaJuego.style.display = 'none';
    this.dom.menuPrincipal.style.display = 'block'; document.body.classList.remove('jugando');
    this.actualizarBotonContinuar();
  }
  confirmarSalir() {
    if (confirm('¿Seguro querés salir? La partida queda guardada y podés continuarla desde el menú.')) { window.close?.(); this.volverAlMenuDesdeJuego(); }
  }

  actualizarBotonContinuar() {
    if (!this.dom.btnContinuar) return;
    const motor = window.app?.motor;
    const hay = motor?.estado ? motor.hayPartidaEnCurso : document.body.dataset.partidaEnCurso === '1';
    this.dom.btnContinuar.style.display = hay ? '' : 'none';
  }

  cargarConfig() {
    this.dom.nombreJugadorInput.value = config.nombreJugador === 'Vos' ? '' : config.nombreJugador;
    this.dom.nombreOponenteInput.value = config.nombreOponente === 'Tata' ? '' : config.nombreOponente;
    this.dom.puntosObjetivoSelect.value = config.puntosObjetivo;
    this.dom.velocidadJuegoSelect.value = config.velocidad;
  }

  guardarConfiguracion() {
    config.nombreJugador = this.dom.nombreJugadorInput.value.trim() || 'Vos';
    config.nombreOponente = this.dom.nombreOponenteInput.value.trim() || 'Tata';
    config.puntosObjetivo = parseInt(this.dom.puntosObjetivoSelect.value, 10);
    config.velocidad = this.dom.velocidadJuegoSelect.value;
    // Los nombres y los puntos para ganar se aplican a la PRÓXIMA partida;
    // la velocidad se aplica enseguida.
    this.actualizarNombres();
    this.volverAlMenu();
  }

  actualizarNombres() {
    this.dom.nombreJugadorDisplay.textContent = config.nombreJugador;
    this.dom.nombreTataDisplay.textContent = config.nombreOponente;
    this.dom.labelJugadorJugadas.textContent = config.nombreJugador + ':';
    this.dom.labelTataJugadas.textContent = config.nombreOponente + ':';
  }

  // ===== NUEVO: puntos del usuario, fin de partido, avisos =====
  actualizarMisPuntos(puntos) {
    if (this.dom.misPuntos && Number.isFinite(puntos)) this.dom.misPuntos.textContent = puntos;
  }

  mostrarFinPartido(res, snap) {
    const tata = snap?.tata?.nombre || 'Tata';
    const gano = res.resultado === 'victoria';
    this.dom.finTitulo.textContent = gano ? '🏆 ¡FELICITACIONES!' : `😔 Ganó ${tata}`;
    this.dom.finDetalle.textContent = gano
      ? `¡Ganaste el partido ${res.puntosJugador} a ${res.puntosTata}!`
      : `${tata} ganó el partido ${res.puntosTata} a ${res.puntosJugador}.`;
    this.dom.finPuntos.textContent = gano
      ? `+${res.puntosGanados} puntos para tu cuenta · Total: ${res.puntosTotales}`
      : `No se suman puntos esta vez · Total: ${res.puntosTotales}`;
    this.dom.finPuntos.className = 'fin-puntos' + (gano ? ' fin-puntos-gano' : '');
    setTimeout(() => { this.dom.modalFin.style.display = 'flex'; }, 600);
  }

  mostrarAviso(texto) {
    const a = this.dom.aviso; if (!a) return;
    a.textContent = texto; a.style.display = 'block';
    clearTimeout(this._avisoTimer);
    this._avisoTimer = setTimeout(() => { a.style.display = 'none'; }, 4000);
  }

  // ===== NUEVO: Tabla de puntajes =====
  async cargarRanking() {
    const cont = this.dom.rankingContenido, yo = this.dom.rankingYo;
    cont.innerHTML = '<p class="ranking-cargando">Cargando…</p>';
    yo.textContent = '';
    let datos;
    try { datos = await window.app.api.ranking(); }
    catch (err) { cont.innerHTML = ''; const p = document.createElement('p'); p.className = 'ranking-vacio'; p.textContent = err.message; cont.appendChild(p); return; }

    // Mis datos (siempre visibles, sin importar la posición)
    const me = datos.yo;
    this.actualizarMisPuntos(me.puntos);
    yo.innerHTML = '';
    const fila = (etiqueta, valor) => {
      const div = document.createElement('div'); div.className = 'ranking-yo-dato';
      const s = document.createElement('span'); s.textContent = etiqueta;
      const b = document.createElement('strong'); b.textContent = valor;
      div.append(s, b); return div;
    };
    yo.append(
      fila('👤 Jugador', me.usuario),
      fila('⭐ Tus puntos', me.puntos),
      fila('📊 Tu posición', me.posicion ? `#${me.posicion}` : 'Sin posición todavía'),
      fila('✅ Ganadas / ❌ Perdidas', `${me.victorias} / ${me.derrotas}`)
    );

    cont.innerHTML = '';
    if (!datos.ranking.length) {
      const p = document.createElement('p'); p.className = 'ranking-vacio';
      p.textContent = datos.totalJugadores > 0
        ? 'Todavía nadie le ganó un partido a Tata. ¡Sé el primero en la tabla!'
        : 'Todavía no hay jugadores registrados.';
      cont.appendChild(p);
      return;
    }

    const tabla = document.createElement('table'); tabla.className = 'tabla-ranking';
    const thead = document.createElement('thead');
    thead.innerHTML = '<tr><th>Posición</th><th>Jugador</th><th class="num">Victorias</th><th class="num">Puntos</th></tr>';
    const tbody = document.createElement('tbody');
    const medallas = { 1: '🥇', 2: '🥈', 3: '🥉' };
    datos.ranking.forEach(r => {
      const tr = document.createElement('tr');
      if (r.esVos) tr.className = 'ranking-vos';
      const celdas = [
        `${medallas[r.posicion] || ''} ${r.posicion}`.trim(),
        r.usuario + (r.esVos ? ' (vos)' : ''),
        r.victorias,
        r.puntos
      ];
      celdas.forEach((v, i) => {
        const td = document.createElement('td'); td.textContent = v;   // textContent => sin XSS
        if (i >= 2) td.className = 'num';
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    tabla.append(thead, tbody);
    cont.appendChild(tabla);
  }

  // ===== LÓGICA DRAG AND DROP (D&D) =====
  wireDragDrop() {
    const mesa = this.dom.mesa;
    if (!mesa) return;
    mesa.ondragover = e => {
      if (this.estadoUI.cartaArrastrada && this.engine?.obtenerEstado()?.turno === 'jugador' && this.engine.juegoIniciado) {
        e.preventDefault(); mesa.classList.add('drop-zone');
      }
    };
    mesa.ondragleave = () => mesa.classList.remove('drop-zone');
    mesa.ondrop = e => {
      e.preventDefault(); mesa.classList.remove('drop-zone');
      if (this.estadoUI.cartaArrastrada && this.engine?.obtenerEstado()?.turno === 'jugador' && this.engine.juegoIniciado) {
        this.engine.jugarCarta('jugador', this.estadoUI.cartaArrastrada);
        this.estadoUI.cartaArrastrada = null;
      }
    };
  }

  _wireCardDragEvents(div, carta) {
    div.className += ' draggable'; div.draggable = true;
    div.ondragstart = () => { this.estadoUI.cartaArrastrada = carta; div.classList.add('dragging'); };
    div.ondragend = () => { this.estadoUI.cartaArrastrada = null; div.classList.remove('dragging'); };
    div.onclick = () => { if (this.engine?.juegoIniciado && this.engine.obtenerEstado()?.turno === 'jugador') this.engine.jugarCarta('jugador', carta); };
  }
}

// ==========================
// APLICACIÓN (fachada para el HTML)
// ==========================
class Aplicacion {
  constructor() {
    this.api = new ClienteAPI();
    this.motor = null;
    this.ui = null;
    this.crearArbitro();
  }

  crearArbitro() {
    this.motor = new ArbitroRemoto({
      api: this.api,
      onMessage: (m) => this.ui?.actualizarMensaje(m),
      onUpdate: (snap) => this.ui?.actualizarUIConSnapshot(snap),
      onFinPartido: (res, snap) => this.ui?.mostrarFinPartido(res, snap),
      onPuntosUsuario: (p) => this.ui?.actualizarMisPuntos(p),
      onError: (msg) => this.ui?.mostrarAviso(msg),
    });
    if (!this.ui) this.ui = new ControladorUI(this.motor);
    else this.ui.engine = this.motor;
  }

  iniciarJuego() { if (!this.motor) this.crearArbitro(); this.motor.iniciarMano(); }

  // Puentes para los onclick del HTML
  iniciarPartida() { this.ui.iniciarPartida(); }
  continuarPartida() { this.ui.continuarPartida(); }
  mostrarPanel(panel) { this.ui.mostrarPanel(panel); }
  volverAlMenu() { this.ui.volverAlMenu(); }
  guardarConfiguracion() { this.ui.guardarConfiguracion(); }
  actualizarNombres() { this.ui.actualizarNombres(); }
  mostrarMenuPausa() { this.ui.mostrarMenuPausa(); }
  continuarJuego() { this.ui.continuarJuego(); }
  volverAlMenuDesdeJuego() { this.ui.volverAlMenuDesdeJuego(); }
  confirmarSalir() { this.ui.confirmarSalir(); }
  verRankingDesdeFin() { this.ui.volverAlMenuDesdeJuego(); this.ui.mostrarPanel('ranking'); }
}

// ===== INICIALIZACIÓN =====
window.addEventListener('DOMContentLoaded', () => {
  window.app = new Aplicacion();
  window.app.actualizarNombres();

  // Exponer funciones globales para que el HTML existente siga funcionando sin cambios
  window.iniciarPartida = () => window.app.iniciarPartida();
  window.continuarPartida = () => window.app.continuarPartida();
  window.mostrarPanel = (p) => window.app.mostrarPanel(p);
  window.volverAlMenu = () => window.app.volverAlMenu();
  window.guardarConfiguracion = () => window.app.guardarConfiguracion();
  window.mostrarMenuPausa = () => window.app.mostrarMenuPausa();
  window.continuarJuego = () => window.app.continuarJuego();
  window.volverAlMenuDesdeJuego = () => window.app.volverAlMenuDesdeJuego();
  window.confirmarSalir = () => window.app.confirmarSalir();
  window.verRankingDesdeFin = () => window.app.verRankingDesdeFin();
});
