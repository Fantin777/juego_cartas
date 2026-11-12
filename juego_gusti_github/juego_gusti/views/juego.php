<?php
/**
 * views/juego.php — Pantalla del juego.
 * El HTML es el mismo del original; solo cambiaron las rutas de los
 * archivos (ahora en public/) y se agregaron las etiquetas <meta> que le
 * dicen a script.js dónde está la API.
 *
 * Variables: $yo, $misPuntos, $hayPartidaEnCurso
 */
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- NUEVO: token anti-CSRF que script.js envía en cada acción -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <!-- Rutas que usa script.js (funcionan con o sin mod_rewrite) -->
    <meta name="api-base" content="<?= e(url('api')) ?>">
    <meta name="login-url" content="<?= e(url('login')) ?>">
    <meta name="img-base" content="<?= e(asset('img/cartas/')) ?>">
    <title>🃏 Truco Argentino</title>

  <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
</head>
<body data-usuario="<?= e($yo['usuario']) ?>" data-puntos="<?= (int) $misPuntos ?>" data-partida-en-curso="<?= $hayPartidaEnCurso ? '1' : '0' ?>">
    <div class="game-container">
        <div id="menuPrincipal" class="menu-principal">
            <h1 class="titulo-menu">🃏 Truco Argentino 🃏</h1>
            <!-- NUEVO: usuario conectado y sus puntos -->
            <div class="usuario-barra">
                <span>👤 <strong id="usuario-nombre"><?= e($yo['usuario']) ?></strong></span>
                <span>⭐ <strong id="mis-puntos"><?= (int) $misPuntos ?></strong> pts</span>
            </div>
            <div class="menu-botones">
                <button id="btnJugar" class="btn-menu btn-jugar" onclick="iniciarPartida()"> JUGAR</button>
                <!-- NUEVO: continuar una partida guardada -->
                <button id="btnContinuar" class="btn-menu btn-continuar" onclick="continuarPartida()" style="<?= $hayPartidaEnCurso ? '' : 'display:none;' ?>">▶ CONTINUAR PARTIDA</button>
                <!-- NUEVO: ranking -->
                <button class="btn-menu btn-ranking" onclick="mostrarPanel('ranking')">🏆 TABLA DE PUNTAJES</button>
                <button class="btn-menu btn-reglas" onclick="mostrarPanel('reglas')"> REGLAS</button>
                <button class="btn-menu btn-config" onclick="mostrarPanel('config')">⚙ CONFIGURACIÓN</button>
                <button class="btn-menu btn-creditos" onclick="mostrarPanel('creditos')"> ACERCA DE</button>
                <!-- NUEVO: cerrar sesión (POST + CSRF) -->
                <form method="post" action="<?= e(url('logout')) ?>" class="form-logout">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="btn-menu btn-salir">🚪 CERRAR SESIÓN</button>
                </form>
            </div>
        </div>

        <!-- NUEVO: panel de ranking -->
        <div id="panel-ranking" class="panel-info" style="display:none;">
            <h2>🏆 Tabla de puntajes</h2>
            <div class="contenido-panel">
                <div id="ranking-yo" class="ranking-yo"></div>
                <div id="ranking-contenido" class="ranking-contenido">
                    <p class="ranking-cargando">Cargando…</p>
                </div>
                <p class="ranking-nota">Cada partido ganado contra Tata suma 10 puntos.</p>
            </div>
            <button class="btn-guardar" onclick="mostrarPanel('ranking')">🔄 ACTUALIZAR</button>
            <button class="btn-volver" onclick="volverAlMenu()">🔙 VOLVER</button>
        </div>

        <div id="panel-reglas" class="panel-info" style="display:none;">
            <h2>📖 Reglas del Truco</h2>
            <div class="contenido-panel">
                <h3> Objetivo</h3>
                <p>Ser el primero en llegar a 15 o 30 puntos (configurable)</p>
                <p>Cada partido que le ganes a Tata suma <strong>10 puntos</strong> a tu cuenta para la tabla de puntajes.</p>

                <h3>🃏 Valores de las cartas</h3>
                <ul>
                    <li>1 de espada (14) - Carta más alta</li>
                    <li>1 de basto (13)</li>
                    <li>7 de espada (12)</li>
                    <li>7 de oro (11)</li>
                    <li>Los 3 (10)</li>
                    <li>Los 2 (9)</li>
                    <li>1 de copa y oro (8)</li>
                    <li>12, 11, 10, 7 de basto/copa, 6, 5, 4 (valores descendentes)</li>
                </ul>

                <h3>🎲 Cantos</h3>
                <ul>
                    <li><strong>Truco:</strong> Vale 2 puntos si se acepta</li>
                    <li><strong>Retruco:</strong> Vale 3 puntos si se acepta</li>
                    <li><strong>Vale 4:</strong> Vale 4 puntos si se acepta</li>
                    <li><strong>Envido:</strong> 2 puntos</li>
                    <li><strong>Real Envido:</strong> 3 puntos</li>
                    <li><strong>Falta Envido:</strong> Puntos hasta ganar (lo que le falta al que va ganando; en partidos a 30, si los dos están en las malas, gana el partido)</li>
                    <li><strong>Empate en el envido:</strong> gana el que es mano</li>
                    <li>El envido se canta antes de tirar tu primera carta. Tata también lo canta, y se puede subir: Envido → Envido → Real Envido → Falta Envido.</li>
                    <li>Si se sube un canto (Retruco, Real Envido…) y el otro no quiere, gana lo que valía el canto anterior.</li>
                </ul>

                <h3>🤝 Pardas (empates de ronda)</h3>
                <ul>
                    <li>Parda la primera: gana el que gane la segunda.</li>
                    <li>Si la primera la ganó alguien y la segunda es parda, gana el de la primera.</li>
                    <li>Las tres pardas: gana el que es mano.</li>
                </ul>

                <h3>✋ La mano</h3>
                <p>Al empezar el partido se sortea quién es mano, y después se alterna en cada reparto.</p>

                <h3>🎮 Cómo jugar</h3>
                <p>Arrastrá o hacé clic en las cartas para jugarlas. Ganás la mano con 2 de 3 rondas.</p>
            </div>
            <button class="btn-volver" onclick="volverAlMenu()">🔙 VOLVER</button>
        </div>

        <div id="panel-config" class="panel-info" style="display:none;">
            <h2>⚙ Configuración</h2>
            <div class="contenido-panel">
                <div class="config-item">
                    <label>Tu nombre:</label>
                    <input type="text" id="nombre-jugador" placeholder="Vos" maxlength="10">
                </div>
                <div class="config-item">
                    <label>Nombre del oponente:</label>
                    <input type="text" id="nombre-oponente" placeholder="Tata" maxlength="10">
                </div>
                <div class="config-item">
                    <label>Puntos para ganar:</label>
                    <select id="puntos-objetivo">
                        <option value="15">15 puntos</option>
                        <option value="30">30 puntos</option>
                    </select>
                </div>
                <div class="config-item">
                    <label>Velocidad del juego:</label>
                    <select id="velocidad-juego">
                        <option value="rapido">Rápido</option>
                        <option value="normal" selected>Normal</option>
                        <option value="lento">Lento</option>
                    </select>
                </div>
            </div>
            <button class="btn-guardar" onclick="guardarConfiguracion()">💾 GUARDAR</button>
            <button class="btn-volver" onclick="volverAlMenu()">🔙 VOLVER</button>
        </div>

        <div id="panel-creditos" class="panel-info" style="display:none;">
            <h2> creditos</h2>
            <div class="contenido-panel">
                <p class="credito-titulo"> Truco Argentino Digital </p>
                <p>Versión 7.0 PRO </p>
                <br>
                <p>un juego clasico pasado a forma digital</p>
                <br>
                <p> si quieres apoyar a los creadores aca tenes su pedazo de alias CAPO para pasarles un poquito para la continuacion de esste pedazo de JUEgo EPICO SUper MEga ultra para mas actualizaciones 😎</p>
                <ul>
                    <li> creadores: besibesiii, yarbis, Fantin</li>
                    <li>ALIAS:</li>
                    <li>Fantin: benja.pasenmeplata.mp.</li>
                    <li>yarbis: mateo.javier22</li>
                    <li>Maurenzi: mugrenzi</li>
                    <li>agradecemos sus donaciones y mandamos saludos apartir de los 55 dolares 😘😘</li>
                </ul>
                <br>
                <p>espero disfrutes de nuestro increible juego!!!</p>
            </div>
            <button class="btn-volver" onclick="volverAlMenu()">🔙 VOLVER</button>
        </div>

        <div id="area-juego" style="display:none;">
            <h1> Truco Argentino</h1>

            <div class="marcador">
                <div class="jugador-info">
                    <div class="nombre" id="nombre-tata-display">Tata</div>
                    <div class="puntos" id="puntos-tata">0</div>
                    <div class="manos-ganadas">puntos</div>
                    <div class="mano-badge" id="mano-tata" style="display:none;">MANO</div>
                </div>
                <div class="vs">vs</div>
                <div class="jugador-info">
                    <div class="nombre" id="nombre-jugador-display">Vos</div>
                    <div class="puntos" id="puntos-jugador">0</div>
                    <div class="manos-ganadas">puntos</div>
                    <div class="mano-badge" id="mano-jugador" style="display:none;">MANO</div>
                </div>
            </div>

            <button class="btn-menu-juego" onclick="mostrarMenuPausa()">☰ MENÚ</button>

            <div class="cartas-container" id="cartas-tata"></div>

            <div class="mesa-layout">
                <div class="fila-cartas">
                    <div class="cartas-jugadas-label" id="label-tata-jugadas">Tata:</div>
                    <div class="cartas-jugadas" id="cartas-tata-jugadas"></div>
                </div>

                <div class="mesa" id="mesa">
                    <div class="mesa-mensaje" id="mensaje">¡Juguemos al Truco!</div>
                    <div class="mesa-info">
                        <div>Ronda: <span id="ronda">0</span>/3</div>
                        <div class="rondas-info" id="rondas-info"></div>
                        <div id="truco-info"></div>
                        <div id="envido-info"></div>
                    </div>
                </div>

                <div class="fila-cartas">
                    <div class="cartas-jugadas-label" id="label-jugador-jugadas">Vos:</div>
                    <div class="cartas-jugadas" id="cartas-jugador-jugadas"></div>
                </div>
            </div>

            <div class="controles" id="controles">
                <button id="botonTruco" class="btn-truco">Truco</button>
                <button id="btnSalir" class="btn-menu btn-salir">🚪 SALIR AL MENÚ</button>
            </div>

            <div class="cartas-container" id="cartas-jugador"></div>

            <div class="instruccion" id="instruccion"></div>
        </div>
    </div>

    <div id="menu-pausa" class="menu-pausa" style="display:none;">
        <div class="contenido-pausa">
            <h2>⏸ Juego Pausado</h2>
            <button class="btn-menu" onclick="continuarJuego()">▶ CONTINUAR</button>
            <button class="btn-menu" onclick="volverAlMenuDesdeJuego()"> MENÚ PRINCIPAL</button>
            <button class="btn-menu btn-salir" onclick="confirmarSalir()"> SALIR</button>
        </div>
    </div>

    <!-- NUEVO: aviso de fin de partido (reemplaza al alert) -->
    <div id="modal-fin" class="menu-pausa" style="display:none;">
        <div class="contenido-pausa contenido-fin">
            <h2 id="fin-titulo">🏆 ¡Ganaste!</h2>
            <p id="fin-detalle" class="fin-detalle"></p>
            <p id="fin-puntos" class="fin-puntos"></p>
            <div class="fin-botones">
                <button class="btn-menu btn-jugar" onclick="iniciarPartida()">🔄 JUGAR OTRA</button>
                <button class="btn-menu btn-ranking" onclick="verRankingDesdeFin()">🏆 VER RANKING</button>
                <button class="btn-menu" onclick="volverAlMenuDesdeJuego()">🏠 MENÚ PRINCIPAL</button>
            </div>
        </div>
    </div>

    <!-- NUEVO: avisos de error/conexión -->
    <div id="aviso" class="aviso" role="alert" style="display:none;"></div>


  <script src="<?= e(asset('js/script.js')) ?>"></script>
</body>
</html>
