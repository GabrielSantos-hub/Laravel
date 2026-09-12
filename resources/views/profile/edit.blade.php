@extends('layout')

@section('conteudo')
<div class="container-fluid pt-3" style="max-width: 640px; margin: 0 auto;">

    @if (session('sucesso'))
        <div class="alert alert-success mb-4" role="alert">{{ session('sucesso') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="avatar-feedback" class="alert mb-4" role="status" hidden></div>

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <h3 class="mb-0">Meu perfil</h3>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            Voltar ao Início
        </a>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 8px;">
        <div class="card-body p-4">
            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="d-flex align-items-center gap-3 mb-4">
                    <div id="profile-avatar">
                        @include('partials.user-avatar', ['user' => $user, 'size' => 72])
                    </div>
                    <div>
                        <p class="fw-semibold mb-1">{{ $user->name }}</p>
                        <p class="small text-muted mb-0">{{ $user->email }}</p>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label text-muted">Nome</label>
                    <input type="text" name="name" id="name" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" required maxlength="255" value="{{ old('name', $user->name) }}">
                </div>

                <div class="mb-4">
                    <label for="avatar-input" class="form-label text-muted">Foto de perfil</label>
                    <input type="file" id="avatar-input" class="form-control bg-light focus:ring-2 focus:ring-indigo-500 focus:outline-none" accept="image/*">
                    <p class="small text-muted mt-2 mb-0">JPG, PNG ou WEBP até 2 MB. A foto é cortada em 1:1 antes de salvar.</p>
                </div>

                <button type="submit" class="btn text-white px-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 6px;">
                    Salvar perfil
                </button>
            </form>
        </div>
    </div>
</div>

<div id="crop-modal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center" hidden>
    <div class="crop-modal-panel" role="dialog" aria-modal="true" aria-labelledby="crop-modal-title">
        <div class="crop-modal-header">
            <h2 id="crop-modal-title" class="h5 mb-0">Ajustar foto</h2>
            <button type="button" class="icon-btn focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-crop-cancel aria-label="Fechar">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <p class="small text-muted mb-3">Arraste e ajuste o recorte. A foto final fica quadrada (1:1).</p>
        <div class="crop-modal-preview max-w-lg max-h-[60vh]">
            <img id="crop-image" alt="Pré-visualização da foto selecionada">
        </div>
        <p id="crop-modal-status" class="small text-muted mt-3 mb-0" role="status" aria-live="polite"></p>
        <div class="crop-modal-footer">
            <button type="button" class="btn btn-outline-secondary focus:ring-2 focus:ring-indigo-500 focus:outline-none" data-crop-cancel>
                Cancelar
            </button>
            <button type="button" id="crop-save" class="btn text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="background-color: #5b4ce6; border-radius: 8px;">
                Salvar Foto
            </button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
    (function () {
        const input = document.getElementById('avatar-input');
        const modal = document.getElementById('crop-modal');
        const image = document.getElementById('crop-image');
        const saveBtn = document.getElementById('crop-save');
        const statusEl = document.getElementById('crop-modal-status');
        const feedback = document.getElementById('avatar-feedback');
        const uploadUrl = @json(route('profile.avatar'), JSON_UNESCAPED_SLASHES);
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        let cropper = null;
        let objectUrl = null;

        function mostrarFeedback(mensagem, tipo) {
            if (!feedback) {
                return;
            }
            feedback.hidden = false;
            feedback.className = 'alert mb-4 alert-' + tipo;
            feedback.textContent = mensagem;
        }

        function definirStatus(mensagem) {
            if (statusEl) {
                statusEl.textContent = mensagem ?? '';
            }
        }

        function aplicarAvatar(url) {
            document.querySelectorAll('.user-avatar').forEach((img) => {
                img.src = url;
            });

            document.querySelectorAll('.user-avatar-fallback').forEach((svg) => {
                const img = document.createElement('img');
                img.src = url;
                img.alt = '';
                img.className = 'user-avatar';
                img.width = Number(svg.getAttribute('width')) || 28;
                img.height = Number(svg.getAttribute('height')) || 28;
                svg.replaceWith(img);
            });
        }

        function destruirCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            image.removeAttribute('src');
        }

        function fecharModal() {
            destruirCropper();
            if (input) {
                input.value = '';
            }
            definirStatus('');
            if (saveBtn) {
                saveBtn.disabled = false;
            }
            modal.hidden = true;
        }

        function abrirModal(arquivo) {
            destruirCropper();
            objectUrl = URL.createObjectURL(arquivo);
            definirStatus('');
            modal.hidden = false;
            saveBtn.disabled = false;

            const iniciar = function () {
                cropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    responsive: true,
                    background: false,
                });
            };

            image.addEventListener('load', iniciar, { once: true });
            image.src = objectUrl;
        }

        input?.addEventListener('change', function () {
            const arquivo = this.files?.[0];
            if (!arquivo) {
                return;
            }
            if (!arquivo.type.startsWith('image/')) {
                mostrarFeedback('Selecione um arquivo de imagem.', 'danger');
                this.value = '';
                return;
            }
            abrirModal(arquivo);
        });

        modal.querySelectorAll('[data-crop-cancel]').forEach((botao) => {
            botao.addEventListener('click', fecharModal);
        });

        modal.addEventListener('click', function (evento) {
            if (evento.target === modal) {
                fecharModal();
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !modal.hidden) {
                fecharModal();
            }
        });

        saveBtn?.addEventListener('click', function () {
            if (!cropper) {
                return;
            }

            const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
            if (!canvas) {
                definirStatus('Não foi possível recortar a imagem.');
                return;
            }

            saveBtn.disabled = true;
            definirStatus('Salvando foto…');

            canvas.toBlob(function (blob) {
                if (!blob) {
                    saveBtn.disabled = false;
                    definirStatus('Não foi possível gerar o recorte.');
                    return;
                }

                const dados = new FormData();
                dados.append('avatar', blob, 'avatar.png');

                fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: dados,
                })
                    .then(async (resposta) => {
                        const corpo = await resposta.json().catch(() => ({}));
                        if (!resposta.ok) {
                            const erro = corpo.message
                                || corpo.errors?.avatar?.[0]
                                || 'Não foi possível salvar a foto.';
                            throw new Error(erro);
                        }
                        return corpo;
                    })
                    .then((corpo) => {
                        aplicarAvatar(corpo.avatar_url);
                        mostrarFeedback(corpo.message || 'Foto de perfil atualizada.', 'success');
                        fecharModal();
                    })
                    .catch((erro) => {
                        saveBtn.disabled = false;
                        definirStatus(erro.message || 'Não foi possível salvar a foto.');
                    });
            }, 'image/png');
        });
    })();
</script>
@endpush
