# Historial de cambios del juego (versiones anteriores)

Este archivo conserva la explicación de cómo se pasó el juego del navegador al servidor y qué reglas se corrigieron. La reorganización actual está explicada en el README.md principal.

## Cómo estaba construido el juego

- **Tecnologías:** HTML + CSS + JavaScript puro (sin frameworks), todo en el navegador. No tenía servidor ni base de datos.
- **`script.js`**, organizado en clases:
  - `Carta`, `Mazo`, `Jugador`, `EstadoTruco`, `EstadoEnvido`: modelo del juego.
  - `EstrategiaIA`: el bot **Tata** (decide qué carta tirar y cómo responder truco y envido).
  - `Arbitro`: el motor. Reparte, resuelve rondas y manos, maneja truco, envido y mazo.
  - `ControladorUI`: dibuja la mesa, las cartas, los botones y el drag & drop.
  - `Aplicacion`: fachada para los `onclick` del HTML.
- **Cómo se detectaba la victoria:** en `Arbitro._resolverMano()`, cuando `jugador.puntos >= puntosObjetivo` (15 o 30) se mostraba un `alert("¡Ganaste el partido…")`.

### Qué cambió y por qué
Como todo corría en el navegador, cualquiera podía abrir la consola y "ganar". Para que **el servidor** decida la victoria:

| Antes (script.js) | Ahora |
|---|---|
| `Mazo`, `Carta`, `Arbitro`, `EstrategiaIA` | Portados **regla por regla** a PHP en `motor/` (Arbitro.php, EstrategiaIA.php, Cartas.php) (mismos nombres de métodos) |
| El navegador barajaba y conocía las cartas de Tata | El servidor baraja con `random_int()` y la mano de Tata **nunca** se envía al navegador |
| `Arbitro` local con `setTimeout` | `ArbitroRemoto`: manda la acción a la API (`/api/partidas/...`) y reproduce los eventos con las mismas demoras (rápido/normal/lento) |
| `alert()` al terminar | Ventana "¡FELICITACIONES!" o "Ganó Tata", con los puntos sumados |
| "Reiniciar Estadísticas" ponía el marcador en 0 en el navegador | Empieza un partido nuevo en el servidor; el anterior queda como *abandonado* |
| `index.html` | `views/juego.php` (igual, más el ranking, tus puntos y cerrar sesión) |

El diseño, los colores, los botones, las animaciones, el drag & drop, las reglas, la configuración y los créditos se mantienen.

### Errores del original que se corrigieron
1. **La victoria no se detectaba si llegabas a 15/30 por envido, "no quiero" o "me voy al mazo"**: el partido seguía. Ahora se revisa después de cada punto.
2. **Tata se trababa:** si cantaba retruco o vale 4 siendo el primero en tirar y vos aceptabas, nunca jugaba su carta. También podía intentar cantar dos veces seguidas y quedarse congelado. Ahora sigue jugando normalmente.
3. **El botón "subir" fallaba ante un Vale 4** (no existe un nivel más alto). Ahora no aparece en ese caso.
4. Mientras Tata espera tu respuesta al truco, no se puede tirar una carta: primero hay que responder.

5. **Falta Envido:** antes valía 5 fijos. Ahora vale lo que le falta al que va ganando para llegar al objetivo. En partidos a 30, si los dos están "en las malas" (menos de 15), la falta envido gana el partido.
6. **La mano:** al empezar el partido se sortea quién es mano (50 % y 50 %), y después se alterna en cada reparto. Antes empezaba siempre el jugador y la mano pasaba al que ganaba la mano anterior.
7. **Envido empatado:** gana el que es mano (antes, siempre el jugador). El mensaje lo aclara, por ejemplo: "(empate: gana Tata, que es mano)".
8. **Tata canta Truco primero:** según la fuerza de su mano (55 % con muy buena mano, 30 % con mano media y 12 % de farol con mano floja). Respeta las mismas restricciones que vos: no lo canta antes de que se tire la primera carta ni mientras todavía podés cantar envido ("el envido está primero").

### Segunda tanda de correcciones
9. **Tata ya no es "imbécil":** antes decidía mirando solo su carta más alta y aceptaba el truco o el envido el 50 % de las veces aunque no tuviera nada. Ahora calcula su probabilidad real de ganar. Para eso mira sus cartas, las que ya están en la mesa, las rondas ganadas y perdidas y, si se jugó el envido, los tantos que dijiste, e imagina cientos de manos posibles tuyas. Con eso decide si quiere, no quiere, sube, canta o qué carta tirar. Nunca mira tu mano real. Blufea a veces, pero poco. Ejemplo: con un 4 de oro en la última ronda ya no te quiere el truco (0 de 200 pruebas).
10. **Tata canta envido por su cuenta** cuando tiene tantos, antes de tirar su primera carta. Vos respondés con QUIERO / NO QUIERO o subís (Envido, Real Envido, Falta Envido). Tata también puede subirte el envido cuando tiene muchos tantos.
11. **El envido se cuenta con las 3 cartas** aunque ya hayas tirado una. Además, si sos mano y ya tiraste, Tata (que es pie) todavía puede cantarlo, como en el truco real.
12. **Botón "¡Retruco!":** después de querer un Truco, el botón para subir decía "¡Truco!". Ahora dice "¡Retruco!" (y "¡Vale 4!" después).
13. **Subir el truco y que el otro no quiera:** antes daba 1 punto. Ahora da lo que valía el canto anterior (Retruco no querido = 2, Vale 4 no querido = 3).
14. **Pardas:** la mano termina en cuanto está definida ("parda la primera, gana la segunda") y con las tres pardas gana el que es mano (antes, siempre el jugador).
15. Se quitó el texto transparente "© juego truco 2025…" que flotaba encima de los botones del menú.

### Detalles del original que siguen igual
- Falta la imagen `dorso.png`, así que las cartas de Tata se ven con el dorso violeta de respaldo (igual que antes).

---

