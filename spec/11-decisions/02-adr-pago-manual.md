# ADR 02: Adopción de Validación Manual de Pagos en MVP

> **Estado vigente: Aceptado para el MVP.** La clasificación histórica “Superado” ya no aplica: transferencia y Deuna con comprobante, Pago en revisión y decisión auditada son el flujo oficial. ADR-04 queda diferida.

**Estado**: Superado por [ADR 04](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/04-adr-integracion-pasarela.md)

## 1. Contexto y Problema
En la fase de planificación inicial del MVP para la tienda virtual de **Almacenes al Costo**, se consideró definir cómo se procesarían los pagos de los clientes, optando inicialmente por un sistema puramente manual por simplicidad de desarrollo inicial. Sin embargo, para responder a las expectativas del mercado moderno y elevar las ventas del MVP, se decidió transicionar a un esquema híbrido y desacoplado que incorpore una pasarela de pagos automatizada.

## 2. Decisión Tomada
Se decidió inicialmente adoptar un sistema exclusivo de validación manual de pagos para la versión MVP (V1) mediante transferencia y Deuna. Esta decisión ha sido superada para dar cabida a una arquitectura flexible de pagos que incluya cobros automatizados con tarjeta mediante pasarela desde el lanzamiento (V1.0), manteniendo los flujos de validación manual de transferencia y Deuna como opciones alternativas secundarias.

## 3. Consecuencias
*   *Positivo*: Rápido despliegue, nulos costos de transacción externa, control directo de fondos por la administración.
*   *Negativo*: Operación manual asíncrona; retardo en la aprobación final de pedidos en base a la disponibilidad del administrador.
*   *Preparación*: El sistema debe contar con interfaces de pagos desacopladas para inyectar pasarelas automatizadas en la V2.

## 4. Referencias y Dependencias
*   [11-decisions/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/README.md)
*   [03-architecture/01-system-architecture.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/01-system-architecture.md)
*   [08-security/02-upload-security.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/02-upload-security.md)
