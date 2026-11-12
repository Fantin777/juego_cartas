# 🧪 Probar el backend del Truco con Postman

Postman es un programa que manda peticiones al servidor **sin usar la página del juego**. Con él podés comprobar cada parte del backend por separado: crear cuentas, iniciar sesión, jugar cartas, cantar truco y ver el ranking.

---

## 1. Antes de empezar

1. **Encendé WampServer** y esperá a que el ícono se ponga **verde**.
2. Comprobá que el juego abre en el navegador: `http://localhost/juego_gusti/`. Si ves el login, el servidor funciona.
3. **Instalá Postman** desde <https://www.postman.com/downloads/>. Usá la **aplicación de escritorio**: la versión web no puede conectarse a `localhost`.

---

## 2. Importar la colección (lo más rápido)

La colección ya trae todas las peticiones armadas.

1. En Postman: **Import** (arriba a la izquierda) → arrastrá el archivo
   `docs/postman/Truco_Argentino.postman_collection.json`.
2. Aparece **"Truco Argentino - API"** en la barra lateral, con 5 carpetas:

| Carpeta | Para qué sirve |
|---|---|
| 0 · Cuenta y sesión | Registrar, iniciar sesión, ver quién sos |
| 1 · Partida | Jugar contra Tata paso a paso |
| 2 · Ranking | Tabla de puntajes |
| 3 · Pruebas de errores | Peticiones que **deben** fallar (para comprobar la seguridad) |
| 4 · Terminar | Abandonar la partida y cerrar sesión |

### Variables de la colección
Hacé clic en el nombre de la colección → pestaña **Variables**:

| Variable | Valor inicial | Qué es |
|---|---|---|
| `baseUrl` | `http://localhost/juego_gusti/api` | Dirección base de la API |
| `usuario` | `postman_1` | Cuenta que se crea para probar |
| `password` | `secreto123` | Su contraseña |
| `csrf` | *(vacío)* | Se llena **solo** al iniciar sesión |
| `cartaParaJugar` | *(vacío)* | Se llena **sola** con la primera carta de tu mano |
| `siguientePaso` | *(vacío)* | Te dice qué petición conviene mandar después |

> **¿La URL base no funciona?**
> - Si la carpeta del juego tiene otro nombre, cambiá `juego_gusti` en `baseUrl`.
> - Si tu Apache no tiene activado `mod_rewrite`, usá `http://localhost/juego_gusti/index.php/api`. El resto es igual.
> - En Wamp se activa así: ícono de Wamp → Apache → Módulos de Apache → `rewrite_module`.

---

## 3. Cómo funciona la seguridad (lo que Postman hace por vos)

El servidor exige dos cosas para todo lo que **modifica** datos:

1. **La cookie de sesión `TRUCOSESSID`.** Se crea al iniciar sesión. Postman la guarda y la reenvía sola, igual que un navegador. Podés verla en **Cookies** (debajo del botón Send).
2. **La cabecera `X-CSRF-Token`.** Es un código secreto que el servidor entrega al iniciar sesión. Demuestra que la petición viene de alguien que tiene la sesión abierta y no de otra página. La petición *Iniciar sesión* lo guarda en `{{csrf}}`, y todas las peticiones POST y DELETE ya lo mandan.

Además, los datos se envían en **JSON**, con la cabecera `Content-Type: application/json`, que ya está configurada.

---

## 4. Lista de endpoints

**Base:** `{{baseUrl}}` = `http://localhost/juego_gusti/api`

**Regla general:** `GET` consulta sin cambiar nada · `POST` ejecuta una acción · `DELETE` abandona.

| Método | Ruta | Sesión | CSRF | Cuerpo JSON | Qué hace |
|---|---|:-:|:-:|---|---|
| POST | `/auth/registro` | – | – | `usuario`, `password`, `confirmar` | Crea una cuenta |
| POST | `/auth/login` | – | – | `usuario`, `password` | Inicia sesión y devuelve `csrf` |
| GET | `/auth/sesion` | ✔ | – | – | Quién está conectado, sus puntos y el `csrf` |
| POST | `/auth/logout` | ✔ | ✔ | – | Cierra la sesión |
| POST | `/partidas` | ✔ | ✔ | `objetivo` (15/30), `nombreJugador`, `nombreOponente` | Partido nuevo (reparte la 1.ª mano) |
| GET | `/partidas/actual` | ✔ | – | – | Estado de la partida en curso |
| DELETE | `/partidas/actual` | ✔ | ✔ | – | Abandona la partida (no suma puntos) |
| POST | `/partidas/actual/manos` | ✔ | ✔ | – | Reparte la siguiente mano |
| POST | `/partidas/actual/cartas` | ✔ | ✔ | `carta` (ej. `"7espada"`) | Tira una carta |
| POST | `/partidas/actual/truco` | ✔ | ✔ | – | Canta Truco / Retruco / Vale 4 |
| POST | `/partidas/actual/truco/respuesta` | ✔ | ✔ | `respuesta`: `aceptar` · `rechazar` · `subir` | Responde un truco de Tata |
| POST | `/partidas/actual/envido` | ✔ | ✔ | `tipo`: `envido` · `realenvido` · `faltaenvido` | Canta envido |
| POST | `/partidas/actual/envido/respuesta` | ✔ | ✔ | `respuesta`: `quiero` · `noquiero` · `envido` · `realenvido` · `faltaenvido` | Responde un envido de Tata |
| POST | `/partidas/actual/mazo` | ✔ | ✔ | – | Te vas al mazo |
| GET | `/ranking` | ✔ | – | – | Tabla de puntajes y tus estadísticas |

**Cartas:** el id es *valor + palo*, sin espacios: `1espada`, `1basto`, `7oro`, `3copa`, `12basto`... Los valores posibles son 1–7 y 10–12; los palos, `espada`, `basto`, `oro` y `copa`.

---

## 5. Paso a paso: tu primera prueba

Abrí la **Consola de Postman** (abajo a la izquierda, *Console*): ahí aparecen los mensajes del juego ("🃏 Tata ganó la ronda…") y el **siguiente paso** recomendado (👉).

### Paso 1 · Registrar cuenta
`POST {{baseUrl}}/auth/registro`
```json
{ "usuario": "postman_1", "password": "secreto123", "confirmar": "secreto123" }
```
Respuesta **201 Created**:
```json
{ "ok": true, "mensaje": "¡Cuenta creada! Ya podés iniciar sesión.", "usuario": { "usuario": "postman_1", "puntos": 0 } }
```
Si la mandás dos veces, la segunda da **409** ("Ese nombre de usuario ya está registrado"). Es lo correcto.

### Paso 2 · Iniciar sesión
`POST {{baseUrl}}/auth/login`
```json
{ "usuario": "postman_1", "password": "secreto123" }
```
Respuesta **200 OK**:
```json
{
  "ok": true,
  "usuario": { "usuario": "postman_1", "puntos": 0 },
  "partidaEnCurso": false,
  "csrf": "d2acc301e14044172c0f2a68536f2f7bdf0445fe49ab1d3c47fd78c34ae79f3e"
}
```
✅ **Cómo comprobarlo:** en Variables, `csrf` ya tiene ese valor, y en *Cookies* aparece `TRUCOSESSID` para `localhost`.

### Paso 3 · Crear partida
`POST {{baseUrl}}/partidas` (cabecera `X-CSRF-Token: {{csrf}}`)
```json
{ "objetivo": 15, "nombreJugador": "Vos", "nombreOponente": "Tata" }
```
Respuesta **201 Created** (resumida):
```json
{
  "ok": true,
  "eventos": [
    {
      "pausa": 0,
      "mensaje": "Tata es mano - Juega primero",
      "estado": {
        "turno": "tata", "ronda": 0, "quienEsMano": "tata", "ganadorRondas": [],
        "jugador": { "nombre": "Vos", "puntos": 0,
                     "mano": [ {"valor":7,"palo":"basto","id":"7basto"}, {"valor":10,"palo":"oro","id":"10oro"}, {"valor":2,"palo":"oro","id":"2oro"} ],
                     "jugadas": [] },
        "tata":    { "nombre": "Tata", "puntos": 0, "cantidadMano": 3, "jugadas": [] },
        "truco":   { "nivel": 0, "valor": 1, "esperandoRespuesta": false, "quienCanto": null },
        "envido":  { "esperandoRespuesta": false, "jugadorPuedeCantar": false, "cadena": [] },
        "juegoIniciado": true, "partidoTerminado": false
      }
    },
    { "pausa": 1, "mensaje": "🗣 Tata canta: \"¡Envido!\" - ¿Querés?", "estado": { "...": "..." } }
  ],
  "partida":  { "codigo": "438a1de9dc6157a2e39a75cbd3259a7c", "enCurso": true },
  "usuario":  { "puntos": 0 },
  "resultado": null
}
```

**Cómo leer la respuesta:**
- `eventos` es la lista de cosas que pasaron, **en orden**. El navegador las muestra una por una, esperando `pausa` × la velocidad configurada.
- El **último** evento tiene el estado actual. Mirá ahí `turno`, `truco.esperandoRespuesta` y `envido.esperandoRespuesta` para saber qué hacer.
- De Tata solo se ve `cantidadMano`, **nunca sus cartas**. Esto es a propósito, para que no se pueda hacer trampa.
- `resultado` deja de ser `null` cuando termina el partido.

### Paso 4 · Jugar
Repetí según lo que diga 👉 en la consola:

| Si el último estado dice... | Mandá |
|---|---|
| `envido.esperandoRespuesta: true` y `quienCanto: "tata"` | **Responder envido** → `{"respuesta":"quiero"}` |
| `truco.esperandoRespuesta: true` y `quienCanto: "tata"` | **Responder truco** → `{"respuesta":"aceptar"}` |
| `turno: "jugador"` | **Jugar carta** → `{"carta":"{{cartaParaJugar}}"}` (o escribí el id de otra carta de tu mano) |
| `juegoIniciado: false` | **Repartir nueva mano** |
| `partidoTerminado: true` | Se terminó: mirá `resultado` |

Al ganar un partido, la respuesta trae:
```json
"resultado": { "resultado": "victoria", "puntosGanados": 10, "puntosTotales": 10, "puntosJugador": 15, "puntosTata": 9 }
```

### Paso 5 · Ver el ranking
`GET {{baseUrl}}/ranking`
```json
{
  "ok": true,
  "ranking": [ { "posicion": 1, "usuario": "postman_1", "puntos": 10, "victorias": 1, "esVos": true } ],
  "totalJugadores": 3,
  "yo": { "usuario": "postman_1", "puntos": 10, "posicion": 1, "victorias": 1, "derrotas": 0, "abandonadas": 0 }
}
```
✅ **Cómo comprobarlo:** después de cada victoria, `yo.puntos` sube exactamente 10. Las derrotas no suman nada.

---

## 6. Comprobar que todo funciona

Cada petición de la colección trae **tests automáticos**. Después de *Send*, mirá la pestaña **Test Results** (abajo): cada línea verde es una comprobación que pasó. Por ejemplo: "Código HTTP 200", "Responde JSON" y "ok = true".

**Probar todo de una vez (Collection Runner):**
1. Clic derecho sobre la colección → **Run collection**.
2. Destildá *Jugar carta*, *Cantar truco*, *Responder truco*, *Cantar envido*, *Responder envido*, *Irse al mazo* y *Repartir nueva mano*: dependen del momento de la partida y se prueban a mano.
3. Cambiá la variable `usuario` por uno nuevo (por ejemplo `postman_2`), para que *Registrar* no dé 409.
4. **Run**. Todo debería salir en verde.

También podés revisar los datos en phpMyAdmin → base `truco_argentino`:
- `usuarios`: tu cuenta, con la contraseña guardada como hash (`$2y$...`) y tus `puntos`.
- `partidas`: cada partido con su `resultado` (`en_curso`, `victoria`, `derrota`, `abandonada`).

---

## 7. Errores y respuestas inesperadas

Todas las respuestas de error tienen la misma forma:
```json
{ "ok": false, "error": "Explicación en castellano de qué pasó" }
```

| Código | Significa | Qué hacer |
|---|---|---|
| **400** | El cuerpo no es un JSON válido | Revisá comas y comillas. En *Body* elegí **raw** + **JSON** |
| **401** | No hay sesión, venció o la contraseña es incorrecta | Volvé a mandar **Iniciar sesión** |
| **403** | Falta el `X-CSRF-Token` o es viejo | Mandá **Iniciar sesión** o **Ver sesión actual** (actualizan `{{csrf}}`) |
| **404** | La ruta no existe, o no tenés partida en curso | Revisá la URL; o mandá **Crear partida nueva** |
| **405** | Método equivocado (ej. POST en `/ranking`) | Usá el método de la tabla. La cabecera `Allow` dice cuál va |
| **409** | Las reglas no permiten esa jugada ("Todavía no es tu turno", "No tenés esa carta"...) | Mandá **Ver partida en curso**. La respuesta 409 también trae `estado` con la situación real |
| **415** | No mandaste JSON | Agregá `Content-Type: application/json` |
| **422** | Un dato está mal o falta (ej. `objetivo: 99`) | El mensaje dice qué dato y qué valores se aceptan |
| **429** | Demasiados logins fallidos | Esperá 15 minutos (se configura en `.env`) |
| **500** | Error del servidor o de la base de datos | ¿Wamp está en verde? ¿Los datos de `.env` son correctos? Mirá el *PHP error log* de Wamp. Para ver el detalle, poné `APP_DEBUG=true` en `.env` y después volvé a `false` |

**No responde nada / "Could not send request":** Apache está apagado, la URL está mal o estás usando Postman Web. Abrí `http://localhost/juego_gusti/` en el navegador para descartar.

**La respuesta es HTML en lugar de JSON:** probablemente falta `/api` en la URL, o falló `mod_rewrite`. Probá con `.../juego_gusti/index.php/api/...`.

---

## 8. Probar sin Postman (opcional, con curl)

En una terminal de Windows (`cmd`), desde cualquier carpeta:
```bat
curl -c c.txt -b c.txt -H "Content-Type: application/json" -d "{\"usuario\":\"postman_1\",\"password\":\"secreto123\"}" http://localhost/juego_gusti/api/auth/login
curl -c c.txt -b c.txt http://localhost/juego_gusti/api/ranking
```
