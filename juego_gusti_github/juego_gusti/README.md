# 🃏 Truco Argentino — Instalación y organización del proyecto

Juego de Truco contra Tata, con cuentas, puntajes y ranking.
**Tecnologías:** PHP 7.4+ (sin frameworks), MySQL/MariaDB y HTML + CSS + JavaScript puro, sobre WampServer.

---

## 1. Estructura de carpetas (MVC)

```
juego_gusti/
├── index.php              ← PUNTO DE ENTRADA ÚNICO (equivale al server.js de Express)
├── .env                   ← Variables de entorno: contraseña de MySQL, puerto, etc.  ← EDITAR ACÁ
├── .env.example           ← Copia de referencia con los valores por defecto
├── .htaccess              ← URLs lindas y bloqueo de archivos privados
│
├── config/
│   ├── config.php         ← Lee .env y define las constantes
│   └── db.php             ← Conexión a la base de datos (PDO) y transacciones
├── core/                  ← "Maquinaria" común a todo el proyecto
│   ├── bootstrap.php      ← Arranque: carga config, sesión y clases
│   ├── Router.php         ← Relaciona MÉTODO + ruta con un controlador
│   ├── http.php           ← URLs, JSON, redirecciones, vistas
│   └── sesion.php         ← Sesión, login, token CSRF
├── routes/
│   ├── web.php            ← Páginas: /, /login, /registro, /logout
│   └── api.php            ← Endpoints JSON (GET / POST / DELETE)
├── controllers/           ← Reciben la petición y devuelven la respuesta
│   ├── JuegoController.php       (pantalla del juego)
│   ├── AuthController.php        (formularios de login/registro)
│   ├── AuthApiController.php     (login/registro en JSON)
│   ├── PartidaController.php     (acciones de juego)
│   └── RankingController.php     (tabla de puntajes)
├── services/              ← Lógica de negocio (reglas de la app)
│   ├── AuthService.php           (validar registro, login, fuerza bruta)
│   ├── PartidaService.php        (une el motor con la BD, suma puntos)
│   └── RankingService.php
├── models/                ← SOLO consultas SQL (SELECT / INSERT / UPDATE)
│   ├── Usuario.php
│   ├── Partida.php
│   └── IntentoLogin.php
├── motor/                 ← El juego de Truco en sí (reglas + IA de Tata)
│   ├── Arbitro.php
│   ├── EstrategiaIA.php
│   ├── Cartas.php
│   └── AccionInvalida.php
├── views/                 ← HTML que ve el jugador
│   ├── juego.php
│   ├── auth/login.php, auth/registro.php
│   ├── layouts/           (encabezado, pie y mensajes de login/registro)
│   └── error.php
├── public/                ← ÚNICA carpeta de archivos que descarga el navegador
│   ├── css/styles.css
│   ├── js/script.js
│   └── img/cartas/*.png, img/fondos/*.jpg
├── database/
│   └── truco_argentino.sql
└── docs/
    ├── POSTMAN.md                         ← Guía para probar la API
    ├── postman/Truco_Argentino.postman_collection.json
    └── HISTORIAL.md                       ← Cambios de versiones anteriores
```

### Cómo viaja una petición
```
Navegador / Postman
      │  POST /juego_gusti/api/partidas/actual/cartas   {"carta":"7espada"}
      ▼
index.php ──► core/bootstrap.php (config, sesión)
      │
      ▼
routes/api.php ──► Router ──► controllers/PartidaController::jugarCarta()
                                   │  verifica sesión + CSRF y lee el JSON
                                   ▼
                          services/PartidaService::jugar()
                                   │  abre una transacción y bloquea la partida
                         ┌─────────┴──────────┐
                         ▼                    ▼
                 motor/Arbitro.php      models/Partida.php, Usuario.php
                 (aplica las reglas)    (guardan en MySQL)
                                   │
                                   ▼
                        JSON { ok, eventos, partida, usuario, resultado }
```

Cada carpeta tiene una sola responsabilidad. Para cambiar una consulta SQL vas a `models/`. Para cambiar una regla del Truco, a `motor/`. Para agregar un endpoint, a `routes/api.php` y su controlador.

---

## 2. Instalación en WampServer

1. **Iniciá WampServer** y esperá el ícono **verde**.
2. **Copiá la carpeta** `juego_gusti` a `C:\wamp64\www\`. Si ya tenías la versión anterior, **reemplazala entera**: los archivos viejos como `login.php`, `api/partida.php` o `includes/` ya no existen.
3. **Base de datos:** si ya la tenías creada, **no hace falta tocar nada** (las tablas son las mismas y las cuentas y puntos se conservan). Si es la primera vez: `http://localhost/phpmyadmin` → pestaña **Importar** → `database/truco_argentino.sql` → **Importar**.
4. **Revisá `.env`** (se abre con el Bloc de notas):
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306        # MariaDB en Wamp = 3307
   DB_NAME=truco_argentino
   DB_USER=root
   DB_PASS=            # si root tiene contraseña, escribila acá
   ```
5. **(Recomendado) Activá `mod_rewrite`:** ícono de Wamp → *Apache* → *Módulos de Apache* → tildá **rewrite_module**. Así las direcciones quedan cortas (`/juego_gusti/login`). Si no lo activás, el juego **funciona igual**, con direcciones como `/juego_gusti/index.php/login`.
6. Abrí **`http://localhost/juego_gusti/`**.

> Si algo falla, mirá el log de errores (ícono de Wamp → PHP → *PHP error log*). Para ver el detalle del error en pantalla, poné `APP_DEBUG=true` en `.env`, y volvé a `false` al terminar.

**Sin Wamp (opcional):** con PHP y MySQL instalados, desde la carpeta del juego ejecutá `php -S localhost:8000 index.php` y abrí `http://localhost:8000/`.

---

## 3. Qué se cambió y por qué

### Organización
| Antes | Ahora | Por qué |
|---|---|---|
| `index.php`, `login.php`, `registro.php` y `logout.php` mezclaban SQL, validaciones y HTML | Rutas → controladores → servicios → modelos → vistas | Cada cosa en un solo lugar: más fácil de entender y de cambiar |
| `includes/motor_truco.php` (1274 líneas) | `motor/` dividido en 4 archivos (uno por clase) | Se encuentra más rápido lo que se busca |
| Credenciales escritas en `config/config.php` | `.env` + `config/config.php` que lo lee | La configuración de cada PC queda separada del código |
| La validación del login y el registro estaba solo en los formularios | `services/AuthService.php`, usado por los formularios **y** por la API | Misma regla en los dos lados, escrita una sola vez |
| 40 imágenes, CSS y JS sueltos en la raíz | `public/css`, `public/js`, `public/img` | El navegador solo puede acceder a `public/`. El resto está bloqueado |
| Cada PHP público se ejecutaba por separado | Un solo `index.php` con router | Una sola puerta de entrada: seguridad y errores tratados igual en todos lados |

### API (antes → ahora)
Antes había **un solo endpoint**, `POST api/partida.php`, que recibía `{"accion": "..."}` para todo, incluso para *consultar*. Ahora cada operación tiene su ruta y su método HTTP:

| Antes (`accion`) | Ahora |
|---|---|
| `nueva_partida` | `POST /api/partidas` → **201** |
| `estado` (era POST y además guardaba en la BD) | `GET /api/partidas/actual`: solo lee, no escribe nada |
| `abandonar` | `DELETE /api/partidas/actual` |
| `nueva_mano` | `POST /api/partidas/actual/manos` |
| `jugar_carta` | `POST /api/partidas/actual/cartas` |
| `cantar_truco` / `responder_truco` | `POST /api/partidas/actual/truco` y `/truco/respuesta` |
| `cantar_envido` / `responder_envido` | `POST /api/partidas/actual/envido` y `/envido/respuesta` |
| `mazo` | `POST /api/partidas/actual/mazo` |
| `GET api/ranking.php` | `GET /api/ranking` |
| *(no existía)* | `POST /api/auth/registro`, `POST /api/auth/login`, `GET /api/auth/sesion`, `POST /api/auth/logout`, para poder probar todo con Postman |

**Códigos de error claros:** 404 (no hay partida o la ruta no existe), 405 (método equivocado), 409 (jugada no permitida), 422 (dato inválido, por ejemplo `"objetivo": 99` o `"respuesta": "tal vez"`), 415 (no es JSON) y 429 (demasiados intentos). Antes casi todo salía como 400 o 409.

### Rendimiento y correcciones
- **Ranking:** contaba las victorias con una subconsulta **por cada jugador**. Ahora usa un único `JOIN` agrupado.
- **Consultar el estado** ya no abre una transacción, no bloquea filas y no reescribe la partida en la BD.
- **Dorso de las cartas:** como `dorso.png` no existe, el navegador lo pedía **en cada carta de Tata y en cada jugada** (14 errores 404 en una partida corta). Ahora recuerda que falta y deja de pedirlo. Se ve igual.
- Se corrigió un espacio faltante en la clase CSS `carta-oculta` (en un caso de respaldo del JS).
- **Login:** además de los intentos del usuario, borra los intentos fallidos viejos, para que la tabla `intentos_login` no crezca sin fin.
- Las redirecciones después de un formulario usan **303 See Other** (lo correcto para Post → Redirect → Get).
- Los valores de los datos (`respuesta`, `tipo`, `carta`) se validan en el controlador **antes** de abrir la transacción.
- Se bloquea la descarga de `.env`, `.sql`, `.md` y `.json`, y de todas las carpetas internas.

### Frontend
- **El diseño no cambió.** Se comparó con capturas en Chrome: la pantalla de login es idéntica píxel por píxel, y el menú y el ranking solo difieren en el nombre del usuario de prueba.
- `public/css/styles.css`: solo cambiaron las 2 rutas de las imágenes de fondo.
- `public/js/script.js`: `ClienteAPI` usa los nuevos endpoints REST (`pedir(método, ruta, datos)`) y lee las direcciones de las etiquetas `<meta>` que escribe el servidor. Así funciona con o sin `mod_rewrite` y en cualquier carpeta. La interfaz, las animaciones, el drag & drop y las demoras siguen igual.

### Lo que NO cambió
Las reglas del Truco, la IA de Tata, la base de datos (mismas tablas: no hay que reimportar), las cuentas y los puntos, y toda la seguridad anterior: CSRF, contraseñas con hash, transacciones con `FOR UPDATE` y puntos que se suman una sola vez.

---

## 4. Lista de pruebas

| # | Prueba | Resultado esperado |
|---|---|---|
| 1 | Abrir `http://localhost/juego_gusti/` sin sesión | Redirige al login |
| 2 | Registrar con campos vacíos, usuario `a b` o contraseñas distintas | Mensajes de error en la página |
| 3 | Registrar un usuario que ya existe | "Ese nombre de usuario ya está registrado." |
| 4 | Login con contraseña incorrecta 5 veces | "Demasiados intentos fallidos…" |
| 5 | Login correcto | Menú con tu usuario y ⭐ puntos |
| 6 | JUGAR → ganar un partido (15 puntos, velocidad Rápido) | "¡FELICITACIONES!" y +10 puntos |
| 7 | MENÚ → MENÚ PRINCIPAL en mitad de una partida | Aparece "▶ CONTINUAR PARTIDA" y la retoma |
| 8 | 🏆 TABLA DE PUNTAJES | Ordenada de mayor a menor, tu fila en verde |
| 9 | Abrir `/juego_gusti/.env`, `/config/config.php` o `/database/truco_argentino.sql` | 403 Prohibido |
| 10 | Importar la colección de Postman y seguir `docs/POSTMAN.md` | Todos los tests en verde |
