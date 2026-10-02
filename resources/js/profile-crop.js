import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const root = document.getElementById('profile-crop');
const input = document.getElementById('avatar-input');
const modal = document.getElementById('crop-modal');
const image = document.getElementById('crop-image');
const saveBtn = document.getElementById('crop-save');
const statusEl = document.getElementById('crop-modal-status');
const feedback = document.getElementById('avatar-feedback');
const uploadUrl = root?.dataset.uploadUrl ?? '';
const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

let cropper = null;
let objectUrl = null;

function mostrarFeedback(mensagem, tipo) {
    if (! feedback) {
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
    image?.removeAttribute('src');
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
    if (modal) {
        modal.hidden = true;
    }
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
    if (! arquivo) {
        return;
    }
    if (! arquivo.type.startsWith('image/')) {
        mostrarFeedback('Selecione um arquivo de imagem.', 'danger');
        this.value = '';
        return;
    }
    abrirModal(arquivo);
});

modal?.querySelectorAll('[data-crop-cancel]').forEach((botao) => {
    botao.addEventListener('click', fecharModal);
});

modal?.addEventListener('click', function (evento) {
    if (evento.target === modal) {
        fecharModal();
    }
});

document.addEventListener('keydown', function (evento) {
    if (evento.key === 'Escape' && modal && ! modal.hidden) {
        fecharModal();
    }
});

saveBtn?.addEventListener('click', function () {
    if (! cropper) {
        return;
    }

    const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
    if (! canvas) {
        definirStatus('Não foi possível recortar a imagem.');
        return;
    }

    saveBtn.disabled = true;
    definirStatus('Salvando foto…');

    canvas.toBlob(function (blob) {
        if (! blob) {
            saveBtn.disabled = false;
            definirStatus('Não foi possível gerar o recorte.');
            return;
        }

        const dados = new FormData();
        dados.append('avatar', blob, 'avatar.png');

        fetch(uploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: dados,
        })
            .then(async (resposta) => {
                const corpo = await resposta.json().catch(() => ({}));
                if (! resposta.ok) {
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
