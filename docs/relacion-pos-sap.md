# Relación de parques con POS y SAP

El propietario confirmó que la relación SAP solicitada en el proceso debe basarse en [IDS.xlsx](referencias/IDS.xlsx). Este archivo aporta los catálogos de referencia para el trabajo del practicante. La finalidad es identificar correctamente las operaciones para relacionarlas con facturación y ventas; no se ha solicitado una conexión automática a SAP.

## Contenido verificado del archivo

| Hoja | Columnas originales | Registros |
| --- | --- | --- |
| POS | `IdSucursalPOS`, `SucursalPOS` | 54 |
| SAP | `CentroCostoSAP`, `NombreCentroCosto`, `DimensionSAP`, `ActivoSAP` | 299 |

SAP contiene 211 registros de dimensión 1 y 88 de dimensión 2. Hay 294 con `ActivoSAP=Y` y 5 con `ActivoSAP=N`. No se encontraron claves repetidas de sucursal POS ni pares repetidos `(DimensionSAP, CentroCostoSAP)`. La hoja POS no contiene una columna de estado activo.

**Las hojas son catálogos separados. El archivo no incluye una tabla que vincule `IdSucursalPOS` con `CentroCostoSAP`.** Debe prepararse y validarse esa correspondencia. No emparejar filas por posición, igualdad numérica o similitud de nombre sin revisión.

Ejemplos de valores existentes, no de equivalencias aprobadas:

- `POS!A9:B9`: `51` / `DIVERSIONES CAYALA`.
- `SAP!A149:D149`: `059` / `CAYALA` / dimensión `1` / activo `Y`.
- `SAP!A150:D150`: `059.01` / `Cayala Kiddies` / dimensión `1` / activo `Y`.

Los nombres sugieren candidatos para revisión; el archivo no establece si una sucursal usa el centro general, uno específico o varias dimensiones. La referencia importada de otras aplicaciones, `source_park_id`, tampoco debe suponerse equivalente a `IdSucursalPOS`.

## Encargo de implementación

1. Conservar ambos catálogos y los nombres originales de sus campos. Registrar su procedencia y permitir una carga repetible que no duplique filas ni sustituya asignaciones aprobadas silenciosamente.
2. Guardar `CentroCostoSAP` como texto. Conservar exactamente ceros, puntos, guiones y letras: `003.01`, `003-01` y `carpra01` no son números decimales ni códigos intercambiables.
3. Identificar cada entrada SAP por su dimensión y código. No descartar `DimensionSAP` ni deducirla de la puntuación del código.
4. Preparar una correspondencia administrable entre el centro operativo de Cumple, la sucursal POS y los centros SAP que le correspondan. Presentar la cardinalidad según las asignaciones revisadas; no imponer una única dimensión ni forzar que todos los centros del Excel sean parques.
5. Mostrar pendientes de correspondencia sin asignar un valor por defecto. Impedir nuevas asignaciones a códigos SAP inactivos y conservar referencias históricas. Definir con el revisor qué operación se bloquea cuando falta una correspondencia necesaria.
6. Obtener el centro de la cuenta autenticada. Administración configura la relación; el empleado no elige libremente POS/SAP al registrar o canjear.
7. Conservar por separado las referencias del **registro** y las del **canje**, junto con cuenta, fechas y factura. Cambiar la correspondencia del catálogo no debe cambiar lo registrado históricamente.
8. Incorporar esos identificadores en consultas/exportaciones autorizadas para permitir el cruce posterior con ventas. Incluir serie/número de factura y sistema emisor según el diseño acordado; un número de factura aislado puede repetirse entre sucursales.

No cargar todos los registros como parques operativos: el catálogo también contiene áreas administrativas, bodegas y subcentros. En POS existe `99 / DEMO`; su presencia en el archivo no acredita habilitación para operación real. La autorización operativa debe quedar explícita en Cumple.

## Casos que requieren revisión del practicante

- `SAP!A207:D207`: `carpra01` tiene el nombre vacío aunque está activo. Conservarlo en la referencia y señalarlo para revisión; no inventar un nombre.
- `SAP!A296:D296`: `0.57.05` está inactivo. No corregirlo automáticamente a otro código parecido.
- `SAP!A3:D3` contiene `003.01`, dimensión 1; `SAP!A208:D208` contiene `003-01`, dimensión 2. No fusionarlos al normalizar texto.
- POS contiene variantes de ubicación y puntos Kiddies. El nombre por sí solo no resuelve la correspondencia con un centro general o un subcentro.
- El archivo no contiene identificadores SemnoX ni reglas de beneficio por tecnología. No inferirlos de los códigos POS/SAP.

## Criterios de aceptación

- [ ] Se conservan los 54 registros POS y los 299 SAP de referencia, incluidos inactivos y observaciones, sin convertirlos automáticamente en parques habilitados.
- [ ] Repetir una carga no crea duplicados ni pierde ceros iniciales, separadores, dimensión o estado.
- [ ] Se entrega una lista de correspondencias aprobadas y otra de pendientes; no hay asignaciones silenciosas por semejanza.
- [ ] Se validan referencias existentes y permisos de administración al guardar una correspondencia.
- [ ] Un registro en un parque y un canje en otro conservan sus propias referencias POS/SAP y la factura del canje.
- [ ] Editar el catálogo no reescribe la identidad histórica de operaciones anteriores.
- [ ] Hay pruebas de código inactivo, nombre vacío, códigos similares de dimensiones distintas y cuenta sin correspondencia.

**Estado:** especificación para el practicante. No se importó este Excel en la base operativa ni se cambió el funcionamiento de la aplicación.
