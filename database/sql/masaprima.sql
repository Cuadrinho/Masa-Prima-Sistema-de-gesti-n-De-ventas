-- =====================================================================
--  Masa Prima · Venta mayorista de pizzas
--  Esquema PostgreSQL normalizado (1FN, 2FN, 3FN)
--  Compatible con PostgreSQL 12+
--
--  Uso:
--    createdb masaprima
--    psql -d masaprima -f masaprima.sql
-- =====================================================================

BEGIN;

DROP SCHEMA IF EXISTS masaprima CASCADE;
CREATE SCHEMA masaprima;
SET search_path TO masaprima;

-- ---------------------------------------------------------------------
-- 1. Catálogos (tablas de referencia)
-- ---------------------------------------------------------------------

CREATE TABLE tipo_comercio (
    id_tipo_comercio  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre            VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE localidad (
    id_localidad   INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre         VARCHAR(80) NOT NULL,
    partido        VARCHAR(80) NOT NULL,
    codigo_postal  VARCHAR(8),
    CONSTRAINT uq_localidad UNIQUE (nombre, partido)
);

CREATE TABLE condicion_iva (
    id_condicion_iva  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    descripcion       VARCHAR(60) NOT NULL UNIQUE,
    letra_factura     CHAR(1)     NOT NULL CHECK (letra_factura IN ('A', 'B'))
);

CREATE TABLE rol (
    id_rol  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre  VARCHAR(40) NOT NULL UNIQUE
);

CREATE TABLE categoria (
    id_categoria  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre        VARCHAR(40) NOT NULL UNIQUE
);

CREATE TABLE modalidad_entrega (
    id_modalidad  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre        VARCHAR(40) NOT NULL UNIQUE,
    requiere_direccion BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE franja_horaria (
    id_franja   INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre      VARCHAR(30) NOT NULL UNIQUE,
    hora_desde  TIME NOT NULL,
    hora_hasta  TIME NOT NULL,
    CONSTRAINT ck_franja_horas CHECK (hora_hasta > hora_desde)
);

CREATE TABLE forma_pago (
    id_forma_pago  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre         VARCHAR(40) NOT NULL UNIQUE
);

CREATE TABLE estado_pedido (
    id_estado  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre     VARCHAR(40) NOT NULL UNIQUE,
    orden      SMALLINT    NOT NULL UNIQUE
);

CREATE TABLE tipo_comprobante (
    id_tipo_comprobante  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre               VARCHAR(40) NOT NULL UNIQUE
);

-- ---------------------------------------------------------------------
-- 2. Personas: usuarios internos y clientes
-- ---------------------------------------------------------------------

CREATE TABLE usuario (
    id_usuario     INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre         VARCHAR(80)  NOT NULL,
    email          VARCHAR(120) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    id_rol         INTEGER      NOT NULL REFERENCES rol (id_rol),
    activo         BOOLEAN      NOT NULL DEFAULT TRUE
);
CREATE UNIQUE INDEX uq_usuario_email ON usuario (lower(email));

CREATE TABLE cliente (
    id_cliente              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    razon_social            VARCHAR(120) NOT NULL,
    cuit                    CHAR(13)     NOT NULL UNIQUE
                            CHECK (cuit ~ '^[0-9]{2}-[0-9]{8}-[0-9]$'),
    id_tipo_comercio        INTEGER      NOT NULL REFERENCES tipo_comercio (id_tipo_comercio),
    id_localidad            INTEGER      NOT NULL REFERENCES localidad (id_localidad),
    id_condicion_iva        INTEGER      NOT NULL REFERENCES condicion_iva (id_condicion_iva),
    id_vendedor             INTEGER      REFERENCES usuario (id_usuario),
    whatsapp                VARCHAR(20)  NOT NULL,
    email                   VARCHAR(120) NOT NULL,
    password_hash           VARCHAR(255),          -- nulo hasta que se aprueba la cuenta
    cajas_semana_estimadas  INTEGER      CHECK (cajas_semana_estimadas >= 0),
    estado_cuenta           VARCHAR(12)  NOT NULL DEFAULT 'pendiente'
                            CHECK (estado_cuenta IN ('pendiente', 'activa', 'suspendida', 'rechazada')),
    habilita_cta_cte        BOOLEAN      NOT NULL DEFAULT FALSE,
    dias_credito            SMALLINT     NOT NULL DEFAULT 0 CHECK (dias_credito >= 0),
    fecha_alta              TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_cliente_cta_cte CHECK (habilita_cta_cte OR dias_credito = 0)
);
CREATE UNIQUE INDEX uq_cliente_email ON cliente (lower(email));
CREATE INDEX ix_cliente_localidad ON cliente (id_localidad);
CREATE INDEX ix_cliente_vendedor  ON cliente (id_vendedor);

CREATE TABLE direccion_entrega (
    id_direccion    INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cliente      INTEGER     NOT NULL REFERENCES cliente (id_cliente) ON DELETE CASCADE,
    calle           VARCHAR(100) NOT NULL,
    numero          VARCHAR(10)  NOT NULL,
    piso_depto      VARCHAR(20),
    id_localidad    INTEGER     NOT NULL REFERENCES localidad (id_localidad),
    predeterminada  BOOLEAN     NOT NULL DEFAULT FALSE
);
CREATE INDEX ix_direccion_cliente ON direccion_entrega (id_cliente);
-- Una sola dirección predeterminada por cliente
CREATE UNIQUE INDEX uq_direccion_predeterminada
    ON direccion_entrega (id_cliente) WHERE predeterminada;

-- ---------------------------------------------------------------------
-- 3. Productos y precios
-- ---------------------------------------------------------------------

CREATE TABLE producto (
    id_producto        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo             VARCHAR(10)   NOT NULL UNIQUE,      -- PP-01, PC-03, MS-01...
    nombre             VARCHAR(80)   NOT NULL,
    id_categoria       INTEGER       NOT NULL REFERENCES categoria (id_categoria),
    unidades_por_caja  SMALLINT      NOT NULL CHECK (unidades_por_caja > 0),
    tamano_cm          NUMERIC(5,1)  CHECK (tamano_cm > 0),   -- nulo en bollos
    peso_g             NUMERIC(7,1)  CHECK (peso_g > 0),      -- solo bollos de masa
    precio_lista_caja  NUMERIC(12,2) NOT NULL CHECK (precio_lista_caja >= 0),
    activo             BOOLEAN       NOT NULL DEFAULT TRUE
);
CREATE INDEX ix_producto_categoria ON producto (id_categoria);

-- Los niveles no se editan: se cierra uno (vigente_hasta) y se crea otro.
CREATE TABLE nivel_precio (
    id_nivel              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre                VARCHAR(30)  NOT NULL,
    cajas_desde           INTEGER      NOT NULL CHECK (cajas_desde >= 0),
    cajas_hasta           INTEGER,                         -- nulo = sin tope
    porcentaje_descuento  NUMERIC(5,2) NOT NULL DEFAULT 0
                          CHECK (porcentaje_descuento BETWEEN 0 AND 100),
    vigente_desde         DATE         NOT NULL DEFAULT CURRENT_DATE,
    vigente_hasta         DATE,
    CONSTRAINT ck_nivel_rango   CHECK (cajas_hasta IS NULL OR cajas_hasta >= cajas_desde),
    CONSTRAINT ck_nivel_vigencia CHECK (vigente_hasta IS NULL OR vigente_hasta >= vigente_desde)
);

-- ---------------------------------------------------------------------
-- 4. Logística
-- ---------------------------------------------------------------------

CREATE TABLE camion (
    id_camion    INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    patente      VARCHAR(10) NOT NULL UNIQUE,
    descripcion  VARCHAR(60) NOT NULL,
    refrigerado  BOOLEAN     NOT NULL DEFAULT TRUE
);

CREATE TABLE reparto (
    id_reparto  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_camion   INTEGER     NOT NULL REFERENCES camion (id_camion),
    id_chofer   INTEGER     NOT NULL REFERENCES usuario (id_usuario),
    fecha       DATE        NOT NULL,
    ruta        VARCHAR(60),
    CONSTRAINT uq_reparto_camion_fecha UNIQUE (id_camion, fecha, ruta)
);
CREATE INDEX ix_reparto_fecha ON reparto (fecha);

-- ---------------------------------------------------------------------
-- 5. Pedidos
-- ---------------------------------------------------------------------

CREATE TABLE pedido (
    id_pedido          INTEGER GENERATED ALWAYS AS IDENTITY (START WITH 1001) PRIMARY KEY,
    id_cliente         INTEGER       NOT NULL REFERENCES cliente (id_cliente),
    fecha_hora_pedido  TIMESTAMPTZ   NOT NULL DEFAULT now(),
    id_nivel           INTEGER       NOT NULL REFERENCES nivel_precio (id_nivel),
    id_modalidad       INTEGER       NOT NULL REFERENCES modalidad_entrega (id_modalidad),
    id_direccion       INTEGER       REFERENCES direccion_entrega (id_direccion),  -- nulo si retira
    fecha_entrega      DATE          NOT NULL,
    id_franja          INTEGER       NOT NULL REFERENCES franja_horaria (id_franja),
    indicaciones       TEXT,
    id_forma_pago      INTEGER       NOT NULL REFERENCES forma_pago (id_forma_pago),
    costo_envio        NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (costo_envio >= 0),
    id_reparto         INTEGER       REFERENCES reparto (id_reparto),             -- nulo hasta asignar
    orden_parada       SMALLINT      CHECK (orden_parada > 0),
    CONSTRAINT ck_pedido_parada CHECK (orden_parada IS NULL OR id_reparto IS NOT NULL)
);
CREATE INDEX ix_pedido_cliente       ON pedido (id_cliente);
CREATE INDEX ix_pedido_fecha_entrega ON pedido (fecha_entrega);
CREATE INDEX ix_pedido_reparto       ON pedido (id_reparto);

CREATE TABLE detalle_pedido (
    id_pedido       INTEGER       NOT NULL REFERENCES pedido (id_pedido) ON DELETE CASCADE,
    id_producto     INTEGER       NOT NULL REFERENCES producto (id_producto),
    cantidad_cajas  INTEGER       NOT NULL CHECK (cantidad_cajas > 0),
    precio_caja     NUMERIC(12,2) NOT NULL CHECK (precio_caja >= 0),  -- precio pactado al vender
    cajas_listas    INTEGER       NOT NULL DEFAULT 0,                 -- avance en cocina
    PRIMARY KEY (id_pedido, id_producto),
    CONSTRAINT ck_detalle_listas CHECK (cajas_listas BETWEEN 0 AND cantidad_cajas)
);
CREATE INDEX ix_detalle_producto ON detalle_pedido (id_producto);

CREATE TABLE historial_estado (
    id_historial  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_pedido     INTEGER     NOT NULL REFERENCES pedido (id_pedido) ON DELETE CASCADE,
    id_estado     INTEGER     NOT NULL REFERENCES estado_pedido (id_estado),
    id_usuario    INTEGER     REFERENCES usuario (id_usuario),  -- nulo = sistema o cliente
    fecha_hora    TIMESTAMPTZ NOT NULL DEFAULT now(),
    observacion   TEXT
);
CREATE INDEX ix_historial_pedido ON historial_estado (id_pedido, fecha_hora DESC);

-- ---------------------------------------------------------------------
-- 6. Pagos y comprobantes
-- ---------------------------------------------------------------------

CREATE TABLE pago (
    id_pago        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_pedido      INTEGER       NOT NULL REFERENCES pedido (id_pedido),
    id_forma_pago  INTEGER       NOT NULL REFERENCES forma_pago (id_forma_pago),
    id_usuario     INTEGER       NOT NULL REFERENCES usuario (id_usuario),
    fecha_hora     TIMESTAMPTZ   NOT NULL DEFAULT now(),
    monto          NUMERIC(12,2) NOT NULL CHECK (monto > 0),
    referencia     VARCHAR(60)
);
CREATE INDEX ix_pago_pedido ON pago (id_pedido);

CREATE TABLE comprobante (
    id_comprobante       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_pedido            INTEGER  NOT NULL REFERENCES pedido (id_pedido),
    id_tipo_comprobante  INTEGER  NOT NULL REFERENCES tipo_comprobante (id_tipo_comprobante),
    punto_venta          SMALLINT NOT NULL CHECK (punto_venta > 0),
    numero               INTEGER  NOT NULL CHECK (numero > 0),
    fecha_emision        DATE     NOT NULL DEFAULT CURRENT_DATE,
    archivo_url          TEXT,
    CONSTRAINT uq_comprobante_numero UNIQUE (id_tipo_comprobante, punto_venta, numero)
);
CREATE INDEX ix_comprobante_pedido ON comprobante (id_pedido);

-- ---------------------------------------------------------------------
-- 7. Regla: si la modalidad requiere dirección, el pedido debe tenerla
-- ---------------------------------------------------------------------

CREATE FUNCTION fn_validar_direccion_pedido() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    v_requiere BOOLEAN;
BEGIN
    SELECT requiere_direccion INTO v_requiere
      FROM modalidad_entrega WHERE id_modalidad = NEW.id_modalidad;

    IF v_requiere AND NEW.id_direccion IS NULL THEN
        RAISE EXCEPTION 'El pedido % es con reparto y no tiene dirección de entrega', NEW.id_pedido;
    END IF;

    IF NOT v_requiere THEN
        NEW.id_direccion := NULL;
        NEW.costo_envio  := 0;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_pedido_direccion
    BEFORE INSERT OR UPDATE OF id_modalidad, id_direccion ON pedido
    FOR EACH ROW EXECUTE FUNCTION fn_validar_direccion_pedido();

-- ---------------------------------------------------------------------
-- 8. Vistas: valores derivados (no se guardan, se calculan)
-- ---------------------------------------------------------------------

-- Estado actual = último registro del historial
CREATE VIEW v_pedido_estado_actual AS
SELECT DISTINCT ON (h.id_pedido)
       h.id_pedido,
       e.id_estado,
       e.nombre AS estado,
       e.orden,
       h.fecha_hora AS desde
  FROM historial_estado h
  JOIN estado_pedido e ON e.id_estado = h.id_estado
 ORDER BY h.id_pedido, h.fecha_hora DESC, h.id_historial DESC;

-- Totales del pedido. IVA general 21 % sobre el neto (ajustar si corresponde).
CREATE VIEW v_pedido_totales AS
WITH d AS (
    SELECT id_pedido,
           SUM(cantidad_cajas)               AS total_cajas,
           SUM(cantidad_cajas * precio_caja) AS subtotal_lista
      FROM detalle_pedido
     GROUP BY id_pedido
)
SELECT p.id_pedido,
       d.total_cajas,
       d.subtotal_lista,
       ROUND(d.subtotal_lista * n.porcentaje_descuento / 100, 2)               AS descuento,
       p.costo_envio,
       ROUND(d.subtotal_lista * (1 - n.porcentaje_descuento / 100), 2)
         + p.costo_envio                                                         AS neto,
       ROUND((d.subtotal_lista * (1 - n.porcentaje_descuento / 100)
         + p.costo_envio) * 0.21, 2)                                             AS iva,
       ROUND((d.subtotal_lista * (1 - n.porcentaje_descuento / 100)
         + p.costo_envio) * 1.21, 2)                                             AS total
  FROM pedido p
  JOIN d            ON d.id_pedido = p.id_pedido
  JOIN nivel_precio n ON n.id_nivel = p.id_nivel;

-- Saldo, estado de pago y vencimiento
CREATE VIEW v_pedido_cobranza AS
SELECT t.id_pedido,
       t.total,
       COALESCE(SUM(pg.monto), 0)                         AS pagado,
       t.total - COALESCE(SUM(pg.monto), 0)               AS saldo,
       CASE WHEN COALESCE(SUM(pg.monto), 0) >= t.total
            THEN 'Pagado' ELSE 'Por pagar' END            AS estado_pago,
       p.fecha_entrega + c.dias_credito                   AS vencimiento
  FROM v_pedido_totales t
  JOIN pedido  p  ON p.id_pedido  = t.id_pedido
  JOIN cliente c  ON c.id_cliente = p.id_cliente
  LEFT JOIN pago pg ON pg.id_pedido = t.id_pedido
 GROUP BY t.id_pedido, t.total, p.fecha_entrega, c.dias_credito;

-- Producción del día por gusto (pantalla de cocina)
CREATE VIEW v_produccion_por_producto AS
SELECT p.fecha_entrega,
       pr.codigo,
       pr.nombre,
       cat.nombre                AS categoria,
       SUM(d.cantidad_cajas)     AS cajas_totales,
       SUM(d.cajas_listas)       AS cajas_listas,
       SUM(d.cantidad_cajas - d.cajas_listas) AS cajas_faltan
  FROM detalle_pedido d
  JOIN pedido    p   ON p.id_pedido     = d.id_pedido
  JOIN producto  pr  ON pr.id_producto  = d.id_producto
  JOIN categoria cat ON cat.id_categoria = pr.id_categoria
  JOIN v_pedido_estado_actual ea ON ea.id_pedido = p.id_pedido
 WHERE ea.estado IN ('Por preparar', 'En horno', 'Listo')
 GROUP BY p.fecha_entrega, pr.codigo, pr.nombre, cat.nombre;

-- ---------------------------------------------------------------------
-- 9. Datos iniciales de catálogos
-- ---------------------------------------------------------------------

INSERT INTO tipo_comercio (nombre) VALUES
    ('Pizzería / rotisería'), ('Almacén / autoservicio'), ('Comedor / catering'),
    ('Revendedor'), ('Eventos / club'), ('Kiosco');

INSERT INTO localidad (nombre, partido) VALUES
    ('Gregorio de Laferrere', 'La Matanza'), ('San Justo', 'La Matanza'),
    ('Isidro Casanova', 'La Matanza'),       ('Villa Luzuriaga', 'La Matanza'),
    ('Ramos Mejía', 'La Matanza'),           ('González Catán', 'La Matanza'),
    ('Morón', 'Morón');

INSERT INTO condicion_iva (descripcion, letra_factura) VALUES
    ('Responsable Inscripto', 'A'), ('Monotributo', 'B'),
    ('Exento', 'B'),               ('Consumidor Final', 'B');

INSERT INTO rol (nombre) VALUES ('Admin'), ('Cocina'), ('Reparto'), ('Vendedor');

INSERT INTO categoria (nombre) VALUES ('Prepizza'), ('Precocida'), ('Congelada'), ('Masa');

INSERT INTO modalidad_entrega (nombre, requiere_direccion) VALUES
    ('Reparto a domicilio', TRUE), ('Retiro por fábrica', FALSE);

INSERT INTO franja_horaria (nombre, hora_desde, hora_hasta) VALUES
    ('Mañana', '08:00', '12:00'), ('Tarde', '14:00', '18:00');

INSERT INTO forma_pago (nombre) VALUES ('Transferencia'), ('Mercado Pago'), ('Cuenta corriente');

INSERT INTO estado_pedido (nombre, orden) VALUES
    ('Solicitud', 1), ('Por preparar', 2), ('En horno', 3), ('Listo', 4),
    ('En reparto', 5), ('Entregado', 6), ('Finalizado', 7), ('Rechazado', 99);

INSERT INTO tipo_comprobante (nombre) VALUES ('Factura A'), ('Factura B'), ('Remito');

-- Rangos y porcentajes de ejemplo: reemplazar por los valores reales.
INSERT INTO nivel_precio (nombre, cajas_desde, cajas_hasta, porcentaje_descuento) VALUES
    ('Inicial',       5,    9,  0),
    ('Comercio',     10,   49, 10),
    ('Distribuidor', 50, NULL, 15);

-- Catálogo de productos. Unidades, tamaños y precios son de ejemplo.
INSERT INTO producto (codigo, nombre, id_categoria, unidades_por_caja, tamano_cm, peso_g, precio_lista_caja)
SELECT v.codigo, v.nombre, c.id_categoria, v.unidades, v.tamano, v.peso, v.precio
  FROM (VALUES
        ('PP-01', 'Prepizza clásica con salsa', 'Prepizza',  12, 30.0, NULL::numeric, 0),
        ('PP-02', 'Prepizza integral',          'Prepizza',  12, 30.0, NULL, 0),
        ('PC-01', 'Muzzarella precocida',       'Precocida',  6, 30.0, NULL, 0),
        ('PC-02', 'Napolitana precocida',       'Precocida',  6, 30.0, NULL, 0),
        ('PC-03', 'Fugazzeta precocida',        'Precocida',  6, 30.0, NULL, 0),
        ('CG-01', 'Calabresa congelada',        'Congelada',  6, 30.0, NULL, 0),
        ('CG-02', 'Especial congelada',         'Congelada',  6, 30.0, NULL, 0),
        ('MS-01', 'Bollos de masa fresca',      'Masa',      20, NULL, 250, 0)
       ) AS v(codigo, nombre, categoria, unidades, tamano, peso, precio)
  JOIN categoria c ON c.nombre = v.categoria;

COMMIT;
