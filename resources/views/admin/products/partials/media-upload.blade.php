@php
    $thumbnail = $thumbnail ?? '';
    $main_media = $main_media ?? '';
    $ext_media = $ext_media ?? '';
@endphp

<div class="product-media-upload">
    <div class="mb-3">
        <label class="form-label">Thumbnail</label>
        <div class="d-flex flex-column gap-2">
            <input type="text" name="thumbnail" id="pm-thumbnail" class="form-control font-monospace small"
                   value="{{ $thumbnail }}" placeholder="OSS object key (upload or paste)">
            <input type="file" class="form-control" id="pm-thumbnail-file" accept="image/*">
        </div>
        <div class="form-text text-muted">Single file; form submits the OSS path (object key), not a public URL.</div>
        <div class="text-danger small mt-1 d-none" id="pm-thumbnail-err" role="alert"></div>
    </div>

    <div class="mb-3">
        <label class="form-label">Main media</label>
        <input type="hidden" name="main_media" id="pm-main_media" value="{{ $main_media }}">
        <div id="pm-main_media-chips" class="d-flex flex-wrap gap-1 mb-2 min-h-0"></div>
        <input type="file" class="form-control" id="pm-main_media-files" multiple>
        <div class="form-text text-muted">Multiple files; stored as comma-separated OSS paths.</div>
        <div class="text-danger small mt-1 d-none" id="pm-main_media-err" role="alert"></div>
    </div>

    <div class="mb-3">
        <label class="form-label">Ext media</label>
        <input type="hidden" name="ext_media" id="pm-ext_media" value="{{ $ext_media }}">
        <div id="pm-ext_media-chips" class="d-flex flex-wrap gap-1 mb-2 min-h-0"></div>
        <input type="file" class="form-control" id="pm-ext_media-files" multiple>
        <div class="form-text text-muted">Multiple files; stored as comma-separated OSS paths.</div>
        <div class="text-danger small mt-1 d-none" id="pm-ext_media-err" role="alert"></div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            'use strict';

            var uploadUrl = @json(route('admin.uploads.store'));
            var meta = document.querySelector('meta[name="csrf-token"]');
            var csrf = meta ? meta.getAttribute('content') : '';

            function parseCsv(s) {
                if (!s || typeof s !== 'string') {
                    return [];
                }
                return s.split(',').map(function (x) {
                    return x.trim();
                }).filter(Boolean);
            }

            function joinCsv(arr) {
                return arr.join(',');
            }

            function showErr(id, msg) {
                var el = document.getElementById(id);
                if (!el) {
                    return;
                }
                el.textContent = msg || '';
                el.classList.toggle('d-none', !msg);
            }

            function uploadOne(file) {
                var fd = new FormData();
                fd.append('file', file);
                return fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: fd,
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json().then(function (j) {
                        if (!r.ok) {
                            var msg = j.message || '';
                            if (j.errors && j.errors.file && j.errors.file[0]) {
                                msg = j.errors.file[0];
                            }
                            throw new Error(msg || 'Upload failed');
                        }
                        if (!j.path) {
                            throw new Error('Invalid upload response');
                        }
                        return j.path;
                    });
                });
            }

            function renderMulti(chipsId, hiddenId, paths) {
                var chips = document.getElementById(chipsId);
                var hidden = document.getElementById(hiddenId);
                if (!chips || !hidden) {
                    return;
                }
                chips.innerHTML = '';
                paths.forEach(function (p) {
                    var span = document.createElement('span');
                    span.className = 'badge bg-secondary d-inline-flex align-items-center gap-1 py-2 px-2';
                    span.style.maxWidth = '100%';
                    var code = document.createElement('code');
                    code.className = 'text-wrap text-start small';
                    code.textContent = p;
                    code.style.wordBreak = 'break-all';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn-close btn-close-white ms-1';
                    btn.setAttribute('aria-label', 'Remove');
                    btn.addEventListener('click', function () {
                        var i = paths.indexOf(p);
                        if (i >= 0) {
                            paths.splice(i, 1);
                        }
                        renderMulti(chipsId, hiddenId, paths);
                    });
                    span.appendChild(code);
                    span.appendChild(btn);
                    chips.appendChild(span);
                });
                hidden.value = joinCsv(paths);
            }

            document.addEventListener('DOMContentLoaded', function () {
                var thumbInput = document.getElementById('pm-thumbnail');
                var thumbFile = document.getElementById('pm-thumbnail-file');
                if (thumbFile && thumbInput) {
                    thumbFile.addEventListener('change', function () {
                        var f = thumbFile.files && thumbFile.files[0];
                        showErr('pm-thumbnail-err', '');
                        if (!f) {
                            return;
                        }
                        uploadOne(f).then(function (path) {
                            thumbInput.value = path;
                            thumbFile.value = '';
                        }).catch(function (e) {
                            showErr('pm-thumbnail-err', e.message || 'Upload failed');
                            thumbFile.value = '';
                        });
                    });
                }

                var mainPaths = parseCsv(document.getElementById('pm-main_media') && document.getElementById('pm-main_media').value);
                var extPaths = parseCsv(document.getElementById('pm-ext_media') && document.getElementById('pm-ext_media').value);

                renderMulti('pm-main_media-chips', 'pm-main_media', mainPaths);
                renderMulti('pm-ext_media-chips', 'pm-ext_media', extPaths);

                function wireMulti(fileInputId, chipsId, hiddenId, errId, paths) {
                    var fi = document.getElementById(fileInputId);
                    if (!fi) {
                        return;
                    }
                    fi.addEventListener('change', function () {
                        showErr(errId, '');
                        var files = fi.files ? Array.prototype.slice.call(fi.files) : [];
                        if (!files.length) {
                            return;
                        }
                        var chain = Promise.resolve();
                        files.forEach(function (file) {
                            chain = chain.then(function () {
                                return uploadOne(file).then(function (path) {
                                    paths.push(path);
                                });
                            });
                        });
                        chain.then(function () {
                            renderMulti(chipsId, hiddenId, paths);
                            fi.value = '';
                        }).catch(function (e) {
                            showErr(errId, e.message || 'Upload failed');
                            renderMulti(chipsId, hiddenId, paths);
                            fi.value = '';
                        });
                    });
                }

                wireMulti('pm-main_media-files', 'pm-main_media-chips', 'pm-main_media', 'pm-main_media-err', mainPaths);
                wireMulti('pm-ext_media-files', 'pm-ext_media-chips', 'pm-ext_media', 'pm-ext_media-err', extPaths);
            });
        })();
    </script>
@endpush
