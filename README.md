# Sistema de Distribuição de EPIs - Colaboradores

Sistema web para consulta e visualização de EPIs (Equipamentos de Proteção Individual) distribuídos aos colaboradores.

## 📋 Funcionalidades

- **Lista de Colaboradores**: Dropdown com busca para selecionar colaboradores
- **Dados do Colaborador**: Exibição de informações como nome, matrícula, função e coligada
- **Histórico de EPIs**: Tabela com todos os EPIs distribuídos ao colaborador selecionado
- **Visualização de Assinatura**: Modal para visualizar a assinatura do colaborador no recebimento

## 🚀 Tecnologias Utilizadas

- **Frontend**:
  - jQuery 3.6.0
  - DataTables 1.11.5
  - Select2 4.1.0
  - Font Awesome 6.4.0
  - Toastify JS

- **Backend**:
  - PHP 7.4+
  - SQL Server (via sqlsrv)

## 📦 Estrutura de Arquivos

```
SSO/
├── index.html              # Página principal
├── script.js               # Lógica JavaScript
├── server.php              # API Backend
├── styles.css              # Estilos gerais
├── datatable-styles.css    # Estilos específicos do DataTables
└── data/
    └── tasks.json         # (arquivo legado, pode ser removido)
```

## ⚙️ Configuração

### 1. Configurar Conexão com o Banco de Dados

Edite o arquivo `server.php` e configure as credenciais do SQL Server:

```php
$serverName = "seu_servidor"; // Ex: "localhost" ou "192.168.1.100"
$connectionOptions = array(
    "Database" => "ssojob",
    "Uid" => "seu_usuario",
    "PWD" => "sua_senha",
    "CharacterSet" => "UTF-8"
);
```

### 2. Requisitos do Servidor

- PHP 7.4 ou superior
- Extensão `sqlsrv` habilitada
- SQL Server com acesso aos bancos:
  - `ssojob`
  - `CorporeRM_JOB`

### 3. Estrutura do Banco de Dados

O sistema espera as seguintes tabelas:

**ssojob:**
- `tmpDistribuicaoEPI2`
- `tmpTask`
- `tmpAgent`
- `tmpPIVOT_Item`
- `fichageradas`

**CorporeRM_JOB:**
- `PFUNC`
- `PFUNCAO`

## 🔧 Como Usar

1. Acesse o sistema pelo navegador
2. Aguarde o carregamento da lista de colaboradores
3. Use o dropdown com busca para selecionar um colaborador
4. Visualize os dados do colaborador e o histórico de EPIs distribuídos
5. Clique em "Ver" na coluna de assinatura para visualizar a assinatura (se disponível)

## 📊 Colunas da Tabela de EPIs

- **Item**: Descrição do EPI
- **CA**: Certificado de Aprovação
- **Quantidade**: Quantidade distribuída
- **Preço**: Valor unitário do item
- **Entregue Por**: Nome do agente que realizou a entrega
- **Data Entrega**: Data em que o EPI foi entregue
- **Assinatura**: Link para visualizar a assinatura do colaborador

## 🎨 Personalização

### Cores Principais

As cores podem ser alteradas no arquivo `styles.css`:

- Cor primária: `#036e35` (azul)
- Cor de sucesso: `#27ae60` (verde)
- Cor de erro: `#e53935` (vermelho)

### Quantidade de Registros por Página

Altere no arquivo `script.js` na função `inicializarTabelaEPIs`:

```javascript
pageLength: 10,
lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]]
```

## 🔍 Troubleshooting

### Erro ao carregar colaboradores

- Verifique a conexão com o banco de dados
- Confirme que a extensão `sqlsrv` está instalada
- Verifique as permissões do usuário no SQL Server

### Select2 não funciona

- Verifique se o jQuery está carregado antes do Select2
- Confirme que os arquivos CSS e JS do Select2 estão sendo carregados

### Dados não aparecem

- Verifique se existem registros nas tabelas
- Confirme que o colaborador possui registros na tabela `tmpDistribuicaoEPI2`
- Verifique o console do navegador para erros JavaScript

## 📝 Notas

- O sistema utiliza queries otimizadas com RANK() para trazer apenas os registros mais recentes
- As assinaturas devem estar em formato de URL/base64 no campo `cp_assinatura`
- O sistema suporta filtros e ordenação em todas as colunas da tabela

## 🤝 Suporte

Para dúvidas ou problemas, verifique:
1. Console do navegador (F12) para erros JavaScript
2. Logs do servidor PHP
3. Logs do SQL Server

## 📄 Licença

Sistema interno - Todos os direitos reservados.

