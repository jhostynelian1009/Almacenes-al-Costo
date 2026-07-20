# Documento: Alcance e Integraciones Futuras

> **Incluye:** pasarela automática, tarjetas, adaptadores, webhooks, HMAC, replay protection, reintentos e idempotencia/conciliación externa.

## 1. Objetivos del Documento
Documentar las características y requerimientos planificados para versiones posteriores al MVP para guiar el crecimiento comercial del sistema.

## 2. Características Futuras Planificadas
*   **Integración con Couriers Locales (Envío Automático)**: Conexión con servicios de entrega como Servientrega, Laar Courier o Cooperativas para cotización automática de fletes en checkout y generación automática de guías de despacho.
*   **Facturación Electrónica en Ecuador**: Envío automático de datos de la orden aprobada a un facturador electrónico para generar el archivo XML y PDF autorizado por el SRI de forma inmediata.
*   **Notificaciones por WhatsApp Automatizadas**: Reemplazar enlaces directos por notificaciones asíncronas de plantilla oficial de WhatsApp API (ej. Twilio) al cambiar estado a "PAGADO" o "Despachado".

## 3. Referencias y Dependencias
*   [12-roadmap/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/12-roadmap/README.md)
*   [03-architecture/01-system-architecture.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/01-system-architecture.md)
