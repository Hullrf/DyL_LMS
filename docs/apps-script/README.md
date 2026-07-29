# Importar cuestionarios desde Google Forms

`importar-cuestionario.gs` exporta las preguntas de un Google Form al
formato JSON que entiende el LMS (botón "Importar desde Google Forms"
al editar un cuestionario).

## Uso

1. Abre tu Google Form.
2. Extensiones → Apps Script.
3. Reemplaza el contenido de `Code.gs` por el de `importar-cuestionario.gs`.
4. En la barra de herramientas, selecciona la función `exportarCuestionario`
   y ejecútala.
5. La primera vez te pedirá autorizar permisos (acceso al Form y a Drive) —
   acéptalos.
6. Revisa `Ver → Registros de ejecución`: ahí aparece el link al archivo
   generado en tu Drive y cuántas preguntas se exportaron u omitieron.
7. Abre ese archivo en Drive, descárgalo y súbelo en el LMS.

## Qué se importa y qué no

- Opción múltiple, casillas de verificación y desplegable → preguntas de
  opción múltiple en el LMS (si la pregunta tiene exactamente dos opciones
  "Verdadero"/"Falso", el LMS la reconoce como Verdadero/Falso automáticamente).
- Respuesta corta y párrafo → preguntas de respuesta corta (se califican a
  mano en el LMS, igual que si se crearan ahí directamente).
- Si el Form tiene activado **Configuración → Convertir en cuestionario**
  con respuestas correctas marcadas, esa información se exporta y el LMS
  la usa directamente. Si no, las preguntas se importan igual, pero el LMS
  las marca como "pendientes de marcar la respuesta correcta" para que las
  corrijas ahí mismo.
- Escala lineal, cuadrícula, fecha, hora y carga de archivos **no se
  importan** — quedan listadas en el registro de ejecución del script
  (`Ver → Registros de ejecución`), el LMS nunca las recibe.
