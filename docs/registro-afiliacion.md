# Especificación del registro de afiliación

Esta especificación se deriva del formato de afiliación de Jinetes del Estado de Veracruz OJEV, A.C., fechado en 2022.

## Campos del formato

| Campo | Captura | Observaciones |
|---|---|---|
| Fecha | Automática y editable | No permite una fecha futura |
| Número de folio | Automática | Se asigna al recibir la solicitud |
| Nombre(s) | OCR y revisión | Separado de los apellidos |
| Apellido paterno | OCR y revisión | Obligatorio |
| Apellido materno | OCR y revisión | Opcional para casos donde no exista |
| CURP | OCR y revisión | Validación de estructura y unicidad |
| Fecha de nacimiento | CURP/OCR y revisión | Debe ser anterior a la fecha actual |
| Domicilio | OCR y revisión | Calle y números |
| Colonia | OCR y revisión | Obligatorio |
| Localidad | OCR y revisión | Obligatorio |
| Municipio | Manual y revisión | Obligatorio |
| Entidad | CURP y revisión | Veracruz por defecto |
| Código postal | OCR y revisión | Cinco dígitos |
| Teléfono de casa/oficina | Manual | Opcional |
| Teléfono celular | Manual | Obligatorio |
| Correo electrónico | Manual | Obligatorio |
| Ocupación u oficio | Manual | Obligatorio |
| Asociación ganadera | Manual | Opcional |
| Delegación/grupo filial OJEV | Manual | Obligatorio |
| Foto del afiliado | Cámara/archivo | Opcional en el primer MVP |
| Firma/declaración | Confirmación explícita | Requiere nombre completo y aceptación |
| Copia de INE | Cámara/archivo | Frente y reverso obligatorios |

## Reglas del OCR

1. La imagen no se considera fuente infalible.
2. Cada valor extraído incluye nivel de confianza y procedencia.
3. Los campos sugeridos permanecen editables.
4. CURP, fecha de nacimiento, nombre y domicilio se validan antes del registro.
5. El texto bruto del OCR no se persiste desde el navegador.
6. Las imágenes definitivas se almacenan cifradas.

## Pendientes previos a producción

- definir quién puede consultar y descargar identificaciones;
- establecer tiempo de retención y procedimiento de eliminación;
- confirmar el valor jurídico de la aceptación digital o integrar firma autógrafa/electrónica;
- sustituir o complementar Tesseract con un proveedor especializado si la precisión real no alcanza el umbral acordado;
- probar con los distintos modelos vigentes de credencial INE y fotografías tomadas en condiciones reales;
- agregar revisión administrativa y estados de aprobación/rechazo.
