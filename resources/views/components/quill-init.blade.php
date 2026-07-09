<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function() {
    const el = document.getElementById('quill-descripcion');
    if (!el) return;
    const quill = new Quill('#quill-descripcion', {
        theme: 'snow',
        placeholder: 'Escribe la descripción aquí...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'align': [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });

    const hidden = document.getElementById('descripcion');
    if (hidden) quill.root.innerHTML = hidden.value || '';

    const form = el.closest('form');
    if (form) {
        form.addEventListener('submit', function() {
            if (hidden) hidden.value = quill.root.innerHTML;
        });
    }

    quill.getModule('toolbar').addHandler('image', function () {
        const input = document.createElement('input');
        input.type  = 'file';
        input.accept = 'image/*';
        input.click();
        input.addEventListener('change', async function () {
            const file = input.files[0];
            if (!file) return;
            if (file.size > 4 * 1024 * 1024) {
                alert('La imagen no debe superar 4 MB.');
                return;
            }
            const body = new FormData();
            body.append('imagen', file);
            body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            try {
                const route = '{{ route("upload.imagen-editor") }}';
                const res  = await fetch(route, { method: 'POST', body });
                const data = await res.json();
                const range = quill.getSelection(true);
                quill.insertEmbed(range.index, 'image', data.url, Quill.sources.USER);
                quill.setSelection(range.index + 1, Quill.sources.SILENT);
            } catch(e) {
                alert('Error al subir la imagen.');
            }
        });
    });
})();
</script>
