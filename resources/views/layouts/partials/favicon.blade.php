{{-- Icono de la pestaña del navegador (mismos archivos de marca que la landing).
     El ?v= esquiva el caché del CDN y del navegador, que guardaron el favicon.ico vacío de antes;
     súbelo si algún día cambia el archivo. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="48x48">
<link rel="icon" type="image/png" sizes="256x256" href="{{ asset('images/logo/logo-icon-light-256.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/logo/logo-icon-light-256.png') }}">
