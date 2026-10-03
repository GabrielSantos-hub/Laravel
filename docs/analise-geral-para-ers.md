# Análise geral do GUEASS para reescrita da ERS

| Campo | Valor |
| --- | --- |
| Data da análise | 2026-10-03 |
| Commit analisado | `fe59959` (`fe599596277805d3f7cb841a368f52effad26d3e`) |
| Branch | `hardening/seguranca` |
| Testes | 774 passed, 4007 assertions, 17,97 s (`php artisan test --compact` com o PHP do Laragon) |
| PHP da suíte | 8.3.30 (cli) ZTS, `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| PHP em `PATH` | 8.2.12, `C:\xampp\php\php.exe` — não foi o interpretador da suíte |

Este texto descreve somente o que o código, o esquema MySQL em uso, os locks e os comandos executados mostram. Onde o código não define o fato, a célula ou a frase diz **NÃO ENCONTRADO** ou **INCERTO**, com o motivo. Documentos em `docs/` foram tratados como hipótese e só entram quando o código confirma ou quando a divergência é o próprio achado.

Sigla usada na ERS antiga extraída de `storage/app/_ers_unzip/doc/word/document.xml`: GUEASS = Gerador de Prompts Especializado. O código não redefine a sigla; `config/app.php` usa `env('APP_NAME', 'Laravel')` e `.env.example` define `APP_NAME=Gueass`. O valor efetivo de `APP_NAME` no processo em execução **não foi lido** (arquivo `.env` não consultado).

---

O texto passou de 100.000 caracteres e foi dividido:

- `docs/analise-geral-para-ers-parte-1.md` — seções 0 a 7 (91.128 caracteres)
- `docs/analise-geral-para-ers-parte-2.md` — seções 8 a 17 e a verificação por amostragem (51.502 caracteres)
