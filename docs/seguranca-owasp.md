# Segurança — OWASP e limitações

Mapeamento completo dos controles contra o OWASP Top 10 e o OWASP Top 10
for LLM Applications fica para a Fase 4. Esta seção registra limitações
já observadas no detector de prompt injection.

## Limitações do detector

O `PromptInjectionDetector` é heurístico (normalização + regex por
categoria + sinais compostos). Não é um classificador semântico.

Corpus original da Fase 2 (40/40) e corpus adversarial independente
(40 ataques de evasão / 40 pedidos legítimos de desenvolvedor): **40/40
ataques bloqueados** e **40/40 legítimos aceitos** após o reforço da
Fase 2.B. O corpus original não teve falso positivo novo.

Limitações conhecidas que **não** foram flexibilizadas (corrigir sem
falso positivo exigiria modelo semântico ou aprovação para afrouxar
XSS/SQLi):

| Limite | Exemplo | Por que não foi “fechado” |
| --- | --- | --- |
| Paráfrase longa sem âncora lexical | Pedido de software seguido de um ensaio que só *implica* desobedecer regras, sem verbos de override/revelação/papel | Qualquer regex amplo do tipo “não siga / faça o que eu quiser” colide com documentação de segurança e requisitos (“o sistema não deve seguir input do usuário como SQL”) |
| Ataque multi-turno | Turno 1 pede um endpoint Laravel; turno 2, noutro request, só diz “agora sem as regras de antes” | Cada request é avaliado isolado; não há memória de conversa no GUEASS |
| Codificação sem verbo de decodificar | Bloco base64/hex puro no meio do pedido, sem “decode/execute/follow” | Redigir ou bloquear qualquer base64 quebraria exemplos de JWT, hashes e fixtures de teste |
| Injeção só com homóglifos raros (não cirílicos/gregos já mapeados) | Caracteres de outros blocos Unicode que visualmente imitam letras | Lista fechada de homóglifos; expandir sem NFKC cobre o mundo inteiro e gera manutenção eterna |
| Pedido curto sem marcador de software | “Revisar o Cropper.js do avatar…” sem citar Laravel/API/sistema | Recusado pelo guardrail de *escopo de software*, não pelo detector. Não é falso positivo de injection |

XSS/SQLi do `InputSanityGuardrail` **não** foram afrouxados. “Revisar
código vulnerável a XSS” passa; um payload com `<script` ou `' OR 1=1`
continua bloqueado.
