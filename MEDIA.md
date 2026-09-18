# Biblioteca de mídia

## Configuração local

No arquivo `EspecializaBack/.env`, configure somente no Laravel:

```dotenv
SUPABASE_URL=https://SEU-PROJETO.supabase.co
SUPABASE_SECRET_KEY=sb_secret_SUA_CHAVE
SUPABASE_MEDIA_BUCKET=media
```

Obtenha a Project URL no botão **Connect** do projeto Supabase. A chave secreta fica em **Settings > API Keys > Secret keys**. Como alternativa legada, use `SUPABASE_SERVICE_ROLE_KEY` no lugar de `SUPABASE_SECRET_KEY`. Nunca use a chave publishable/anon para este serviço; nunca coloque credenciais no Vue ou em variáveis VITE_.

Referência: https://supabase.com/docs/guides/getting-started/api-keys

No diretório EspecializaBack:

```powershell
php artisan config:clear
php artisan media:prepare
```

O comando cria o bucket privado `media` com restrição a WebP e SVG processados, caso não exista. Se já existir como público, ele recusa a configuração; não muda políticas existentes silenciosamente. O Laravel controla uploads, consultas, remoções e entrega dos bytes. O navegador recebe uma URL `/api/media/{uuid}`, nunca um endereço interno do Storage ou uma chave. Configure APP_URL com a URL HTTPS real da API na implantação.

As migrations da biblioteca e do vínculo de capa foram aplicadas no banco configurado em 10/09/2026. Em outros ambientes, execute `php artisan migrate` no processo normal de implantação.

Reinicie o projeto pelo `startproject.bat` da pasta pai. Ele carrega `.tools/php-conf/media.ini`, habilitando GD/EXIF, upload de 10 MB e memória de 512 MB. Em outro servidor, habilite GD com WebP, EXIF, fileinfo e DOM e ajuste os limites equivalentes no PHP. A dependência Intervention Image 3.11.8 está registrada em composer.lock.

## Contratos

Administração: Sanctum, administrador e senha inicial já alterada. Base `/api/admin/media`, também disponível em `/api/admin/v1/media`.

- GET `/`: search, page, per_page (máximo 60), sort (created_at/original_name/size), direction (asc/desc). Retorna data, links, meta e limites em upload.
- POST `/`: multipart file, alt_text opcional, course_name opcional e upload_key UUID opcional para repetição idempotente. Retorna data e HTTP 201.
- GET `/{id}`: detalhes da mídia.
- PATCH `/{id}`: alt_text e is_decorative. Capas exigem descrição e não podem ser decorativas.
- GET `/{id}/usages`: cursos vinculados, paginados.
- DELETE `/{id}`: HTTP 204; retorna 409 e referências de uso se vinculada, inclusive a rascunhos. Falhas de Storage preservam o registro para nova tentativa.
- GET público `/api/media/{uuid}`: entrega apenas mídia pronta com visibility public, com MIME, proteção nosniff e cache. O bucket permanece privado.

Cursos aceitam cover_media_id e cover_alt_text. A resposta pública inclui cover com url, alt_text, width e height. cover_image_path continua como fallback para registros antigos. A descrição específica de um curso não altera a descrição compartilhada de outros cursos.

## Processamento e operação

Padrões configuráveis em config/media.php e .env.example: original até 10 MB, SVG até 512 KB, raster até 24 milhões de pixels, maior lado 2400 px, WebP qualidade 82. Validação verifica conteúdo e formato real; SVG usa uma lista restrita de elementos/atributos estáticos. Caminhos usam courses/ano/mês/UUID-nome.webp (ou svg).

O processamento síncrono está isolado do armazenamento e dos controllers para futura adoção de filas. Os registros guardam dados originais e finais, dimensões e redução. Estados uploading/ready/failed/deleting impedem vínculos durante operações incompletas. Não há binários no PostgreSQL. A tabela media usa RLS e índice trigram para busca no PostgreSQL; relações e locks protegem exclusão concorrente.

## Validação realizada

47 testes de API passaram (206 assertions), incluindo autenticação, conteúdo inválido, SVG, otimização, paginação, vínculo, compatibilidade de capas e exclusão segura. Pint e build Vite passaram. No navegador, foi validado upload PNG, seleção/salvamento da capa, imagem e alt públicos, edição da descrição e bloqueio de exclusão em uso. Painel verificado a 390 px sem overflow horizontal.

O teste integrado usou SQLite e um simulador HTTP de Storage fora dos repositórios. Nenhum curso de teste foi inserido no banco real. Upload no Supabase real depende da configuração das credenciais e execução de media:prepare.