# LucasSheet

Mini sistema local em Laravel para controle de horas trabalhadas por cliente, projeto e categoria, com dashboard, CRUDs, filtros e exportacao de relatorios em `.xlsx`.

## Stack

- Laravel 12
- Livewire 4 com componentes Blade
- Tailwind CSS 4 via Vite
- SQLite por padrao
- Exportacao XLSX local via `ZipArchive`

## Requisitos locais

- PHP 8.2+
- Composer
- Node.js e npm
- Extensoes PHP recomendadas:
  - `pdo_sqlite` / `sqlite3` para usar SQLite
  - `mbstring` para o Laravel
  - `zip` para exportar `.xlsx`

Em Ubuntu/Debian com PHP 8.2, normalmente:

```bash
sudo apt install php8.2-sqlite3 php8.2-mbstring php8.2-zip
```

Se a sua versao do PHP for outra, ajuste o numero do pacote.

## Instalacao

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

## Configurar SQLite

O projeto ja usa SQLite por padrao no `.env`:

```env
DB_CONNECTION=sqlite
```

Crie o arquivo do banco se ele ainda nao existir:

```bash
touch database/database.sqlite
```

## Migrations e seeders

```bash
php artisan migrate --seed
```

Os seeders criam apenas categorias base. Clientes, projetos e registros ficam vazios para voce iniciar com seus proprios dados.

Para limpar uma base que ja recebeu dados ficticios antes, use:

```bash
php artisan migrate:fresh --seed
```

## Rodar localmente

Em um terminal:

```bash
npm run dev
```

Em outro terminal:

```bash
php artisan serve
```

Acesse:

```text
http://127.0.0.1:8000
```

Tambem e possivel gerar assets de producao com:

```bash
npm run build
```

## Telas

- `/` Dashboard com totais do dia, semana, mes, ultimas tarefas e resumo por cliente.
- `/clientes` cadastro, edicao e ativar/desativar clientes.
- `/projetos` cadastro, edicao e ativar/desativar projetos por cliente.
- `/categorias` cadastro, edicao, cor e ativar/desativar categorias.
- `/registros` filtros, criacao manual, editar, excluir, iniciar agora e finalizar tarefa em andamento.
- `/relatorios` filtro por periodo/cliente/projeto/categoria, tabela, resumos e exportacao Excel.

## Exportar relatorios

Na tela `/relatorios`, ajuste os filtros e clique em `Exportar Excel`.

A planilha gerada contem:

- Aba `Registros`
- Aba `Resumo por Cliente`
- Aba `Resumo por Projeto`
- Aba `Resumo por Categoria`

Cada resumo traz total em minutos e total formatado em `HH:MM`.

Tambem existe um comando para gerar o relatorio do dia atual:

```bash
php artisan report:today
```

Para uma data especifica:

```bash
php artisan report:today --date=2026-05-08
```

O arquivo e salvo em `storage/app/private/reports`.

## Regras implementadas

- Duracao calculada automaticamente no model `WorkLog`.
- Tarefas sem horario final ficam como `Em andamento`.
- Horario final menor que inicial e recusado pela validacao.
- Horarios fora de 08:00 a 18:00 geram aviso, mas nao bloqueiam o registro.
- Filtros por data, cliente, projeto e categoria.
- Ao iniciar uma nova tarefa, se ja existir uma em andamento, a tela pede confirmacao para finalizar a anterior.
