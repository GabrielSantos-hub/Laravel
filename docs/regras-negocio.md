# Regras de negócio do GUEASS

Valores e invariantes extraídos do código. Arquivo e símbolo entre
parênteses. O que não existir no repositório está marcado **NÃO ENCONTRADO**.

## Geração de prompts

- Intenção: obrigatória, 10–1000 caracteres
  (`IntentAnalyzer::MIN_INPUT_LENGTH` / `MAX_INPUT_LENGTH`,
  `GeneratePromptRequest`).
- Mensagens: «Descreva o que você precisa gerar.»; mínimo «são necessários
  ao menos :min caracteres.»; máximo «o limite é de :max caracteres.»
- Variáveis dinâmicas: chaves `^[A-Za-z_][A-Za-z0-9_]*$`
  (`PromptController::variaveisDinamicas`); valor no máximo 2000 caracteres.
- Opt-out de histórico: `nao_salvar_historico` (boolean).
- Idempotência: 5 segundos (`GENERATE_IDEMPOTENCY_SECONDS`,
  `config/security.php`), chave
  `generate-idempotency:{userId}:{sha256(intencao|nao_salvar)}`.
- Falha genérica HTTP: «Não foi possível processar a solicitação.»
  (`PromptController::GENERIC_FAILURE_MESSAGE`).
- Intenção pouco clara: `PromptGeneratorService::UNCLEAR_MESSAGE`.
- Sem template: `NoCompatibleTemplateException::forIntent()`.
- Timeout do provedor: «O provedor de IA demorou demais para responder.
  Tente novamente.»
- Guardrail de sanidade: «A instrução fornecida parece inválida ou
  desconexa. Por favor, descreva uma necessidade clara.»
  (`InputUnprocessableException::MESSAGE`).

## Modo lean

`InputSanityGuardrail::isLean` decide **antes** da montagem. É lean quando
o pedido já foi aceito **e**:

- texto cru ≤ **60** caracteres; **e**
- ≤ **10** tokens; **e**
- nenhum `RICH_DETAIL_MARKERS`; **e**
- diagnóstico / sem verbo de implementação, **ou** implementação com ≤ **5**
  tokens.

`LEAN_MAX_LINES = 25` **não** entra nessa decisão. É invariante de
`PromptBuilderService::assembleLean`: se o envelope lean ultrapassar 25
linhas, lança `PromptAssemblyException`. Pedido rico (acima desses limiares
ou com marcadores de detalhe) usa o envelope completo (PAPEL, TAREFA,
RESTRIÇÕES, ESQUEMA, AUTO-VALIDAÇÃO).

## Templates e catálogo

Blocos (`Template::BLOCOS`):

| Bloco | Rótulo |
| --- | --- |
| A | Features / Funcionalidades |
| B | Raciocínio / Lógica |
| C | Análise / Etapa 0 |

`corpo_template` max 500000 (`StoreTemplateRequest` / `UpdateTemplateRequest`).
`bloco` ∈ {A,B,C}.

Pesos do seletor (`TemplateSelector`): linguagem 4, framework 3,
arquitetura 2, tipo 2, categoria 8, tag 3 (teto 9), stack teto 7,
penalidade de estilo 6, dica textual 1 (teto 3).

- Linguagem não se exclui se houver frameworks ou templates ligados.
- Arquitetura não se exclui se houver pivot de templates.
- Framework: exclusão direta; `prompts.framework_id` é `nullOnDelete`.

## Autenticação e papéis

- Papéis: `ADM` e `USU` (`users.role`, default `USU`).
- `role` **não** está em `User::$fillable` (cadastro público não promove).
- Gate `admin` = `User::isAdmin()` (`AppServiceProvider`).
- Último administrador não pode ser excluído nem rebaixado
  (`CannotRemoveLastAdminException::MESSAGE`).
- Senha: mínimo 8, letras e números (`PasswordRules`); `uncompromised()` só
  em `production`.
- `must_change_password`: middleware `password.changed`
  (`EnsurePasswordIsChanged`). Reset admin gera senha de 16 caracteres e
  liga a flag.
- Login: `throttle:5,1` + `login-email-ip` (5/min por e-mail\|IP).
- Registro: `throttle:10,1`.
- Recuperação self-service de senha: **NÃO ENCONTRADO** (modal aponta para
  `suportegueass@gmail.com`; rotas `/forgot-password` 404).

## Histórico, privacidade e retenção

- Histórico pessoal: `Prompt::user()` / `User::prompts()`; dono autorizado
  em `PromptController::autorizarDono`.
- Exclusão de conta: `DELETE /perfil` com confirmação de senha,
  `throttle:5,1`.
- Retenção: `PROMPT_RETENTION_DAYS` padrão **90**;
  `php artisan gueass:prune-prompts` (`routes/console.php` daily).
- Canal de segurança: `SECURITY_LOG_DAYS` padrão **30**; JSON em
  `storage/logs/security.log`.

## Avatar

- MIME jpeg/png/webp; SVG recusado; max **2048** KB
  (`UpdateAvatarRequest`).
- Reprocessamento GD, lado máximo **512** px, saída PNG
  (`AvatarSanitizer::MAX_EDGE`).
- Mensagem de recusa: «A imagem enviada não pôde ser aceita.»

## Auditoria

`audit_logs` somente inserção. A tela lista nome, e-mail mascarado e `#id`
do ator. Um `authorization_denied` por 403 do painel (`Gate::after`; o
callback duplicado em `bootstrap/app.php` foi removido).
