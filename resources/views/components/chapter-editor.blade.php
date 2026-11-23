{{--
    Rich-text editor (Word-like toolbar) for chapter content.
    Props: $value (stored/old content), $invalid (bool).
    The textarea[name=content] keeps the HTML and is what gets submitted; the server sanitizes it again.
--}}
@php($initial = \App\Support\ChapterContent::toHtml($value ?? ''))
@once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <style>
        .chapter-editor .ql-toolbar { position: sticky; top: 0; z-index: 5; background: #f8f9fa; border-radius: .375rem .375rem 0 0; }
        .chapter-editor .ql-container { border-radius: 0 0 .375rem .375rem; font-size: 1.05rem; }
        .chapter-editor .ql-editor { min-height: 420px; line-height: 1.8; }
        .chapter-editor.is-invalid .ql-toolbar, .chapter-editor.is-invalid .ql-container { border-color: #dc3545; }
        .chapter-editor .ql-snow .ql-picker.ql-size .ql-picker-label::before,
        .chapter-editor .ql-snow .ql-picker.ql-size .ql-picker-item::before { content: 'Cỡ chữ'; }
        .chapter-editor .ql-snow .ql-picker.ql-size .ql-picker-label[data-value=small]::before,
        .chapter-editor .ql-snow .ql-picker.ql-size .ql-picker-item[data-value=small]::before { content: 'Nhỏ'; }
        .chapter-editor .ql-snow .ql-picker.ql-size .ql-picker-label[data-value=large]::before,
        .chapter-editor .ql-snow .ql-picker.ql-size .ql-picker-item[data-value=large]::before { content: 'Lớn'; }
        .chapter-editor .ql-snow .ql-picker.ql-size .ql-picker-label[data-value=huge]::before,
        .chapter-editor .ql-snow .ql-picker.ql-size .ql-picker-item[data-value=huge]::before { content: 'Rất lớn'; }
        .chapter-editor .ql-picker.ql-size { width: 90px; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
@endonce

<div class="chapter-editor @if ($invalid ?? false) is-invalid @endif" id="chapter-editor-wrap">
    <div id="chapter-toolbar">
        <span class="ql-formats">
            <button type="button" class="ql-undo" title="Hoàn tác (Ctrl+Z)" aria-label="Hoàn tác">↶</button>
            <button type="button" class="ql-redo" title="Làm lại (Ctrl+Y)" aria-label="Làm lại">↷</button>
        </span>
        <span class="ql-formats">
            <select class="ql-header" title="Kiểu đoạn" aria-label="Kiểu đoạn">
                <option value="1">Tiêu đề 1</option>
                <option value="2">Tiêu đề 2</option>
                <option value="3">Tiêu đề 3</option>
                <option selected>Văn bản</option>
            </select>
            <select class="ql-size" title="Cỡ chữ" aria-label="Cỡ chữ">
                <option value="small"></option>
                <option selected></option>
                <option value="large"></option>
                <option value="huge"></option>
            </select>
        </span>
        <span class="ql-formats">
            <button type="button" class="ql-bold" title="Đậm (Ctrl+B)" aria-label="Đậm"></button>
            <button type="button" class="ql-italic" title="Nghiêng (Ctrl+I)" aria-label="Nghiêng"></button>
            <button type="button" class="ql-underline" title="Gạch chân (Ctrl+U)" aria-label="Gạch chân"></button>
            <button type="button" class="ql-strike" title="Gạch ngang" aria-label="Gạch ngang"></button>
        </span>
        <span class="ql-formats">
            <select class="ql-color" title="Màu chữ" aria-label="Màu chữ"></select>
            <select class="ql-background" title="Màu nền chữ" aria-label="Màu nền chữ"></select>
        </span>
        <span class="ql-formats">
            <button type="button" class="ql-script" value="sub" title="Chỉ số dưới" aria-label="Chỉ số dưới"></button>
            <button type="button" class="ql-script" value="super" title="Chỉ số trên" aria-label="Chỉ số trên"></button>
        </span>
        <span class="ql-formats">
            <button type="button" class="ql-list" value="ordered" title="Danh sách số" aria-label="Danh sách số"></button>
            <button type="button" class="ql-list" value="bullet" title="Danh sách dấu đầu dòng" aria-label="Danh sách dấu đầu dòng"></button>
            <button type="button" class="ql-indent" value="-1" title="Giảm thụt lề" aria-label="Giảm thụt lề"></button>
            <button type="button" class="ql-indent" value="+1" title="Tăng thụt lề" aria-label="Tăng thụt lề"></button>
        </span>
        <span class="ql-formats">
            <select class="ql-align" title="Căn lề" aria-label="Căn lề"></select>
            <button type="button" class="ql-blockquote" title="Trích dẫn" aria-label="Trích dẫn"></button>
            <button type="button" class="ql-link" title="Chèn liên kết" aria-label="Chèn liên kết"></button>
        </span>
        <span class="ql-formats">
            <button type="button" class="ql-clean" title="Xóa định dạng" aria-label="Xóa định dạng"></button>
        </span>
    </div>
    <div id="chapter-editor">{!! $initial !!}</div>
</div>

{{-- Without JavaScript the raw textarea below stays visible and is sanitized by the server. --}}
<textarea id="chapter-content" name="content" rows="18" class="form-control mt-2">{{ $initial }}</textarea>

<script>
    (function () {
        const textarea = document.getElementById('chapter-content');
        const holder = document.getElementById('chapter-editor');
        if (!textarea || !holder || typeof Quill === 'undefined') {
            return; // Fall back to the plain textarea.
        }

        textarea.hidden = true;

        const quill = new Quill(holder, {
            theme: 'snow',
            placeholder: 'Nhập nội dung chương...',
            modules: {
                history: { delay: 800, maxStack: 200, userOnly: true },
                toolbar: {
                    container: '#chapter-toolbar',
                    handlers: {
                        undo() { this.quill.history.undo(); },
                        redo() { this.quill.history.redo(); },
                    },
                },
            },
        });

        const sync = () => {
            textarea.value = quill.getText().trim() === '' ? '' : quill.getSemanticHTML();
        };
        quill.on('text-change', sync);
        sync();

        textarea.form?.addEventListener('submit', function (event) {
            sync();
            if (textarea.value === '') {
                event.preventDefault();
                document.getElementById('chapter-editor-wrap').classList.add('is-invalid');
                quill.focus();
                alert('Vui lòng nhập nội dung chương.');
            }
        });
    })();
</script>
