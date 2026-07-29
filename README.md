# Afiliados OJEV

Plataforma digital de afiliación para Jinetes del Estado de Veracruz OJEV, A.C.

## Estado actual

La primera entrega implementa el registro público de afiliados:

- captura frontal y posterior de la INE;
- OCR local en español con Tesseract;
- sugerencias editables con indicador de procedencia;
- formulario basado en el formato de afiliación OJEV;
- validación de CURP y datos obligatorios;
- folio automático `OJEV-AÑO-000000`;
- almacenamiento privado y cifrado de INE y foto;
- aceptación explícita de la declaración de afiliación;
- interfaz responsiva para computadora y teléfono.

El OCR nunca confirma datos automáticamente. La persona debe revisar y corregir cada campo antes de enviar.

## Stack

- PHP 8.4
- Laravel 13
- React 19 + TypeScript
- Inertia 3
- Tailwind CSS 4
- PostgreSQL en producción; SQLite para desarrollo y pruebas
- Tesseract OCR `spa+eng`
- Pest

## Desarrollo local

```bash
cd backend
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

Tesseract y el paquete de idiomas deben estar disponibles:

```bash
brew install tesseract tesseract-lang
```

La aplicación queda disponible en `http://localhost:8000/afiliacion`.

## Docker

El entorno de producción incluye PHP 8.4, Nginx, PostgreSQL, Redis, el
trabajador de colas, el programador de tareas y Tesseract OCR con español.

```bash
APP_URL=http://localhost:8088 docker compose up -d --build
```

La aplicación queda disponible en `http://localhost:8088/afiliacion`.
`APP_KEY` y la contraseña de PostgreSQL se generan dentro del volumen privado
`app_secrets`; no se guardan en Git ni en el archivo Compose.

Para revisar el estado:

```bash
docker compose ps
docker compose logs --tail=100 web
```

## Verificación

```bash
cd backend
php artisan test
npm run lint:check
npm run types:check
npm run build
```

## Privacidad

Las imágenes se guardan fuera del directorio público y cifradas con la llave de la aplicación. No deben registrarse imágenes, texto OCR, CURP ni otros datos personales en logs.

Antes de producción se debe definir una política formal de privacidad, retención y eliminación de identificaciones.
