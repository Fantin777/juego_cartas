-- =====================================================================
--  TRUCO ARGENTINO - Base de datos
--  Compatible con MySQL 5.7+/8.x y MariaDB 10.3+ (WampServer)
--  Importar desde phpMyAdmin -> pestaña "Importar"
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `truco_argentino`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `truco_argentino`;

-- ---------------------------------------------------------------------
--  Cuentas de jugadores
--  * `usuario` es UNIQUE y, por la collation *_ci, no distingue
--    mayúsculas: "Benja" y "benja" son el mismo nombre.
--  * `password` guarda SOLO el hash generado por password_hash().
--  * `puntos` es el acumulado que muestra el ranking.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `usuario`         VARCHAR(20)   NOT NULL,
  `password`        VARCHAR(255)  NOT NULL,
  `puntos`          INT UNSIGNED  NOT NULL DEFAULT 0,
  `fecha_registro`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_login`    DATETIME      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
  KEY `idx_usuarios_ranking` (`puntos`, `usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Partidas contra Tata
--  * `codigo`: identificador único e impredecible de cada partida.
--  * `resultado`: 'en_curso' mientras se juega; al terminar pasa a
--    'victoria' o 'derrota' UNA sola vez (el servidor lo hace dentro de
--    una transacción con la fila bloqueada). 'empate' queda reservado:
--    un partido de truco a 15/30 no puede terminar empatado.
--    'abandonada' = el jugador empezó otra partida sin terminar ésta.
--  * `estado_juego`: estado completo del motor (mazo, mano secreta de
--    Tata, etc.). Nunca se envía al navegador.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `partidas` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `codigo`           CHAR(32)         NOT NULL,
  `usuario_id`       INT UNSIGNED     NOT NULL,
  `resultado`        ENUM('en_curso','victoria','derrota','empate','abandonada')
                                      NOT NULL DEFAULT 'en_curso',
  `puntos_objetivo`  TINYINT UNSIGNED NOT NULL,
  `puntos_jugador`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `puntos_tata`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `puntos_ganados`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `estado_juego`     MEDIUMTEXT       NULL,
  `fecha_inicio`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha`            DATETIME         NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partidas_codigo` (`codigo`),
  KEY `idx_partidas_usuario_resultado` (`usuario_id`, `resultado`),
  CONSTRAINT `fk_partidas_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_partidas_objetivo` CHECK (`puntos_objetivo` IN (15, 30))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Intentos fallidos de login (protección contra fuerza bruta)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intentos_login` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario`  VARCHAR(20)  NOT NULL,
  `ip`       VARCHAR(45)  NOT NULL,
  `fecha`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intentos_usuario_fecha` (`usuario`, `fecha`),
  KEY `idx_intentos_ip_fecha` (`ip`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
