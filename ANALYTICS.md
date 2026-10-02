# Atendimentos e análise própria

O painel `/admin/atendimentos` combina análise de navegação e o histórico existente de cliques de WhatsApp, sem depender do Google Analytics. O Vue envia eventos exclusivamente para o Laravel.

## Contratos

- `POST /api/analytics/events`: lote de uma visita e até 25 interações, limitado a 90 requisições/minuto por IP. UUIDs tornam reenvios idempotentes. Tempo e rolagem são acumulados por visita, não geram novas visualizações.
- `GET /api/admin/analytics`: protegido por Sanctum, administrador e troca de senha inicial. Filtros `start_date`, `end_date`, `path`, `device`, `utm_source`. Período máximo: 90 dias. Retorna resumo, comparação anterior, série diária, rankings, dispositivos, alcance de rolagem e amostras de desempenho.
- `GET /api/admin/attendances`: mantém o histórico e sua paginação antiga, adicionando também `clicks.meta` para compatibilidade com a interface.

As consultas usam agregações no servidor e rankings limitados. Não são enviados todos os registros ao navegador. Os dias do relatório seguem `America/Sao_Paulo`, com filtros convertidos para UTC.

## Configuração

`ANALYTICS_ENABLED=true` habilita a coleta. Os padrões funcionam sem adicionar variáveis ao ambiente já existente:

```dotenv
ANALYTICS_EXCLUDED_IPS=179.98.61.162,127.0.0.1,::1
ANALYTICS_TIMEZONE=America/Sao_Paulo
ANALYTICS_TRUSTED_PROXIES=
```

O middleware filtra o IP real recebido pelo Laravel, sem gravá-lo nas tabelas de analytics ou WhatsApp. Apenas configure IPs/CIDRs de proxies reversos efetivamente usados se houver um proxy adicional na frente do Nginx. Não confie indiscriminadamente em `X-Forwarded-For` ou `*`. As origens públicas aceitas ficam em `config/analytics.php`; localhost:5173 é permitido apenas no ambiente local.

As tabelas novas têm RLS habilitado no PostgreSQL e acesso revogado para `anon`/`authenticated`. O acesso ocorre exclusivamente pelo servidor Laravel. Não há credenciais públicas nem acesso direto do Vue ao Supabase.

## Significado e limites

- Sessão: UUID temporário por aba, renovado após 30 minutos de inatividade. O banco guarda um hash, não o identificador original. Não representa uma pessoa única entre dispositivos.
- Visita: uma entrada em uma rota pública. Mudança de hash ou atualização do tempo não conta outra visita.
- Tempo ativo: aba visível, com atividade nos últimos 60 segundos. Não comprova leitura.
- Rolagem: maior percentual do percurso rolável. Página sem área rolável não recebe 100% automaticamente.
- Taxa de contato: sessões com clique de WhatsApp / sessões. Não representa vendas, reuniões ou mensagens efetivamente enviadas.
- LCP: carregamento principal da entrada inicial. Latência de interação: maior evento observado, aproximação, não INP oficial. CLS: maior janela de deslocamentos inesperados. O painel apresenta médias e número de amostras, não percentil 75.
- Administradores conectados, IPs internos, robôs identificados, DNT e GPC são ignorados. Ad blockers, falhas de rede, navegação muito rápida e recursos não suportados podem reduzir a coleta.
- Não são coletados valores de formulários, nomes, e-mails, query strings completas, gravações de tela ou movimentos individuais do mouse.
- Histórico anterior não pode ser reconstruído. Cliques antigos sem IP não podem ser removidos retroativamente por IP. Não foi ativada exclusão automática de dados existentes ou novos.

## Validação

```bash
php artisan test --compact --filter="SiteAnalyticsTest|AdminAttendanceTest|WhatsappClickTest"
```

No frontend: `node --test tests/analytics.test.mjs` e `npm run build`. Os testes geram dados apenas em ambiente isolado, nunca para preencher o painel integrado.
