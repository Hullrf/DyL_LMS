/**
 * Exporta las preguntas del Google Form activo al formato JSON que
 * espera el importador de cuestionarios del LMS DyL Quality.
 * Ver README.md en esta misma carpeta para instrucciones de uso.
 */
function exportarCuestionario() {
  const form = FormApp.getActiveForm();
  const items = form.getItems();
  const preguntas = [];
  const omitidas = [];

  items.forEach(function (item) {
    const tipo = item.getType();

    if (tipo === FormApp.ItemType.MULTIPLE_CHOICE || tipo === FormApp.ItemType.CHECKBOX || tipo === FormApp.ItemType.LIST) {
      preguntas.push(mapearOpcionMultiple(item, tipo));
    } else if (tipo === FormApp.ItemType.TEXT || tipo === FormApp.ItemType.PARAGRAPH_TEXT) {
      preguntas.push({ texto: item.getTitle(), tipo: 'respuesta_corta' });
    } else {
      omitidas.push(item.getTitle() + ' (' + tipo + ')');
    }
  });

  const resultado = { version: 1, preguntas: preguntas };
  const nombreArchivo = 'cuestionario-' + form.getId() + '.json';
  const archivo = DriveApp.createFile(nombreArchivo, JSON.stringify(resultado, null, 2), MimeType.PLAIN_TEXT);

  Logger.log('Archivo generado: ' + archivo.getUrl());
  Logger.log(preguntas.length + ' preguntas exportadas.');
  if (omitidas.length > 0) {
    Logger.log('Omitidas (tipo no soportado por el LMS): ' + omitidas.join(', '));
  }
}

function mapearOpcionMultiple(item, tipo) {
  const wrapped = tipo === FormApp.ItemType.MULTIPLE_CHOICE
    ? item.asMultipleChoiceItem()
    : (tipo === FormApp.ItemType.CHECKBOX ? item.asCheckboxItem() : item.asListItem());

  const opciones = wrapped.getChoices().map(function (choice) {
    var correcta = null;
    try {
      correcta = choice.isCorrectAnswer();
    } catch (e) {
      correcta = null; // el Form no tiene activado el modo "Cuestionario"
    }
    return { texto: choice.getValue(), correcta: correcta };
  });

  return {
    texto: item.getTitle(),
    tipo: 'opcion_multiple',
    multiple: tipo === FormApp.ItemType.CHECKBOX,
    opciones: opciones
  };
}
