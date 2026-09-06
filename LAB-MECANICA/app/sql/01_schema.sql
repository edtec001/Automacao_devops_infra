CREATE DATABASE IF NOT EXISTS oficina
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE oficina;

-- =====================================================
-- CLIENTES
-- =====================================================

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cpf_cnpj VARCHAR(20),
    telefone VARCHAR(30),
    email VARCHAR(150),
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cpf_cnpj (cpf_cnpj)
);

-- =====================================================
-- FUNCIONÁRIOS (MECÂNICOS, AUXILIARES, ADMINISTRATIVO)
-- =====================================================

CREATE TABLE IF NOT EXISTS funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(20),
    telefone VARCHAR(30),
    cargo ENUM(
        'MECANICO',
        'AUXILIAR',
        'ADMINISTRATIVO'
    ) NOT NULL DEFAULT 'MECANICO',
    especialidade VARCHAR(100) DEFAULT 'Geral',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- VEÍCULOS
-- =====================================================

CREATE TABLE IF NOT EXISTS veiculos (
    placa VARCHAR(10) PRIMARY KEY,
    cliente_id INT NULL,
    mecanico_id INT NULL,
    modelo VARCHAR(100) NOT NULL,
    marca VARCHAR(100),
    ano INT,
    cor VARCHAR(50),
    km_atual INT,
    telefone VARCHAR(30),
    entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    saida DATETIME NULL,

    status ENUM(
        'ENTRADA',
        'ORCAMENTO',
        'SERVICO',
        'LIBERADO'
    ) NOT NULL DEFAULT 'ENTRADA',

    observacoes TEXT,

    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    posicao_chave INT DEFAULT NULL,

    posicao_chave_ativa INT GENERATED ALWAYS AS (
        CASE WHEN saida IS NULL THEN posicao_chave ELSE NULL END
    ) STORED,

    UNIQUE KEY uk_posicao_chave_ativa (posicao_chave_ativa),

    CONSTRAINT fk_veiculo_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_veiculo_mecanico
        FOREIGN KEY (mecanico_id)
        REFERENCES funcionarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- =====================================================
-- ORDENS DE SERVIÇO
-- =====================================================

CREATE TABLE IF NOT EXISTS ordens_servico (
    id INT AUTO_INCREMENT PRIMARY KEY,

    placa VARCHAR(10) NOT NULL,

    data_abertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    data_fechamento DATETIME NULL,

    diagnostico TEXT,

    observacoes TEXT,

    defeito_relatado TEXT,

    itens_veiculo TEXT,

    estado_lataria TEXT,

    foto_lataria VARCHAR(255),

    nivel_combustivel VARCHAR(20),

    forma_pagamento VARCHAR(50),

    numero_parcelas INT DEFAULT 1,

    valor_pago DECIMAL(10,2) DEFAULT 0.00,

    data_pagamento DATETIME NULL,

    status ENUM(
        'ABERTA',
        'ORCAMENTO',
        'APROVADA',
        'EM_SERVICO',
        'AGUARDANDO_PECA',
        'FINALIZADA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'ABERTA',

    valor_servicos DECIMAL(10,2) NOT NULL DEFAULT 0,

    valor_pecas DECIMAL(10,2) NOT NULL DEFAULT 0,

    desconto DECIMAL(10,2) NOT NULL DEFAULT 0,

    valor_total DECIMAL(10,2)
        GENERATED ALWAYS AS (
            valor_servicos +
            valor_pecas -
            desconto
        ) STORED,

    CONSTRAINT fk_os_veiculo
        FOREIGN KEY (placa)
        REFERENCES veiculos(placa)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- =====================================================
-- SERVIÇOS
-- =====================================================

CREATE TABLE IF NOT EXISTS servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,

    ordem_servico_id INT NOT NULL,

    descricao VARCHAR(255) NOT NULL,

    quantidade DECIMAL(10,2) NOT NULL DEFAULT 1,

    valor_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,

    valor_total DECIMAL(10,2)
        GENERATED ALWAYS AS (
            quantidade * valor_unitario
        ) STORED,

    CONSTRAINT fk_servico_os
        FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico(id)
        ON DELETE CASCADE
);

-- =====================================================
-- PEÇAS
-- =====================================================
-- FORNECEDORES DE AUTOPEÇAS
-- =====================================================

CREATE TABLE IF NOT EXISTS fornecedores_autopecas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_fantasia VARCHAR(150) NOT NULL,
    razao_social VARCHAR(150) NULL,
    cnpj VARCHAR(20) NULL,
    telefone VARCHAR(30) NULL,
    vendedor_contato VARCHAR(100) NULL,
    email VARCHAR(150) NULL,
    cidade_estado VARCHAR(100) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- PEÇAS
-- =====================================================

CREATE TABLE IF NOT EXISTS pecas (
    id INT AUTO_INCREMENT PRIMARY KEY,

    codigo VARCHAR(50) NOT NULL,

    nome VARCHAR(150) NOT NULL,

    categoria VARCHAR(100) DEFAULT 'Geral',

    fabricante VARCHAR(100),

    fornecedor_id INT NULL,

    unidade VARCHAR(20) DEFAULT 'UN',

    quantidade INT NOT NULL DEFAULT 0,

    estoque_minimo INT NOT NULL DEFAULT 0,

    preco_custo DECIMAL(10,2) NOT NULL DEFAULT 0,

    preco_venda DECIMAL(10,2) NOT NULL DEFAULT 0,

    localizacao VARCHAR(100),

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_codigo (codigo),

    CONSTRAINT fk_peca_fornecedor
        FOREIGN KEY (fornecedor_id)
        REFERENCES fornecedores_autopecas(id)
        ON DELETE SET NULL
);

-- =====================================================
-- PEÇAS UTILIZADAS NA ORDEM DE SERVIÇO
-- =====================================================

CREATE TABLE IF NOT EXISTS os_pecas (
    id INT AUTO_INCREMENT PRIMARY KEY,

    ordem_servico_id INT NOT NULL,

    peca_id INT NOT NULL,

    quantidade INT NOT NULL DEFAULT 1,

    preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,

    valor_total DECIMAL(10,2)
        GENERATED ALWAYS AS (
            quantidade * preco_unitario
        ) STORED,

    CONSTRAINT fk_ospeca_os
        FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ospeca_peca
        FOREIGN KEY (peca_id)
        REFERENCES pecas(id)
        ON DELETE RESTRICT
);

-- =====================================================
-- MOVIMENTAÇÕES DE ESTOQUE
-- =====================================================

CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,

    peca_id INT NOT NULL,

    tipo ENUM(
        'ENTRADA',
        'SAIDA',
        'AJUSTE'
    ) NOT NULL,

    quantidade INT NOT NULL,

    estoque_anterior INT NOT NULL,

    estoque_posterior INT NOT NULL,

    ordem_servico_id INT NULL,

    observacao VARCHAR(255),

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mov_peca
        FOREIGN KEY (peca_id)
        REFERENCES pecas(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_mov_os
        FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico(id)
        ON DELETE SET NULL
);

-- =====================================================
-- COMANDOS DO ARDUINO
-- =====================================================

CREATE TABLE IF NOT EXISTS arduino_comandos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    placa VARCHAR(10) NOT NULL,

    status VARCHAR(20) NOT NULL,

    comando VARCHAR(20) NOT NULL,

    processado TINYINT(1) NOT NULL DEFAULT 0,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    processado_em DATETIME NULL,

    INDEX idx_arduino_processado (processado),

    INDEX idx_arduino_placa (placa)
);

-- =====================================================
-- USUÁRIOS
-- =====================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,

    nome VARCHAR(150) NOT NULL,

    usuario VARCHAR(50) NOT NULL,

    senha VARCHAR(255) NOT NULL,

    perfil ENUM(
        'ADMIN',
        'GERENTE',
        'MECANICO',
        'ESTOQUE',
        'ATENDIMENTO'
    ) NOT NULL DEFAULT 'ATENDIMENTO',

    ativo TINYINT(1) NOT NULL DEFAULT 1,

    trocar_senha TINYINT(1) NOT NULL DEFAULT 0,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_usuario (usuario)
);

-- =====================================================
-- NOTAS FISCAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS notas_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT NULL,
    numero_nf VARCHAR(50) NOT NULL,
    chave_acesso VARCHAR(50) NULL,
    tipo ENUM('NFS-E', 'NF-E', 'RECIBO_FISCAL') NOT NULL DEFAULT 'NFS-E',
    cliente_nome VARCHAR(150) NOT NULL,
    cliente_cpf_cnpj VARCHAR(20) NULL,
    valor_servicos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_pecas DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_impostos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    aliquota_imposto DECIMAL(5,2) DEFAULT 5.00,
    status ENUM('EMITIDA', 'CANCELADA') NOT NULL DEFAULT 'EMITIDA',
    observacoes TEXT NULL,
    data_emissao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_nf_os
        FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico(id)
        ON DELETE SET NULL
);

-- =====================================================
-- CHECKLIST DE REVISÃO E PROCEDIMENTOS
-- =====================================================

CREATE TABLE IF NOT EXISTS checklist_revisao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT NULL,
    placa VARCHAR(10) NOT NULL,
    mecanico_id INT NULL,
    data_checklist DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    km_veiculo INT NULL,
    status_geral ENUM('EM_ANDAMENTO', 'APROVADO', 'RECOMENDACOES') NOT NULL DEFAULT 'EM_ANDAMENTO',
    observacoes_gerais TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_checklist_os
        FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_checklist_veiculo
        FOREIGN KEY (placa)
        REFERENCES veiculos(placa)
        ON DELETE CASCADE,

    CONSTRAINT fk_checklist_mecanico
        FOREIGN KEY (mecanico_id)
        REFERENCES funcionarios(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS checklist_revisao_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    item_nome VARCHAR(150) NOT NULL,
    status_item ENUM('CONFORME', 'REPARADO', 'ATENCAO', 'NAO_APLICAVEL') NOT NULL DEFAULT 'CONFORME',
    observacao VARCHAR(255) NULL,

    CONSTRAINT fk_item_checklist
        FOREIGN KEY (checklist_id)
        REFERENCES checklist_revisao(id)
        ON DELETE CASCADE
);

-- =====================================================
-- DESPESAS OPERACIONAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    categoria ENUM(
        'ALUGUEL',
        'ENERGIA_AGUA',
        'SALARIOS',
        'FERRAMENTAS',
        'PECAS_REPOSICAO',
        'IMPOSTOS',
        'OUTROS'
    ) NOT NULL DEFAULT 'OUTROS',
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    status ENUM('PENDENTE', 'PAGO') NOT NULL DEFAULT 'PAGO',
    observacao TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- DADOS DE TESTE
-- =====================================================

INSERT INTO clientes (
    id,
    nome,
    telefone,
    email,
    cidade,
    estado
)
VALUES (
    1,
    'Cliente Teste',
    '(85) 99999-9999',
    'cliente@teste.local',
    'Fortaleza',
    'CE'
)
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO veiculos (
    placa,
    cliente_id,
    modelo,
    marca,
    ano,
    cor,
    km_atual,
    status,
    observacoes
)
VALUES (
    'ABC1D23',
    1,
    'Onix',
    'Chevrolet',
    2022,
    'Prata',
    45000,
    'ENTRADA',
    'Veículo de teste do sistema'
)
ON DUPLICATE KEY UPDATE placa = placa;

INSERT INTO pecas (
    codigo,
    nome,
    fabricante,
    unidade,
    quantidade,
    estoque_minimo,
    preco_custo,
    preco_venda,
    localizacao
)
VALUES
(
    'OLEO-5W30',
    'Óleo 5W30',
    'Lubrax',
    'L',
    20,
    5,
    28.00,
    35.90,
    'A01'
),
(
    'FILTRO-OLEO',
    'Filtro de óleo',
    'Mann',
    'UN',
    10,
    3,
    18.00,
    29.90,
    'A02'
),
(
    'FILTRO-AR',
    'Filtro de ar',
    'Tecfil',
    'UN',
    8,
    2,
    30.00,
    45.00,
    'A03'
),
(
    'VELA-NGK',
    'Vela de ignição',
    'NGK',
    'UN',
    20,
    8,
    18.00,
    29.90,
    'B01'
)
ON DUPLICATE KEY UPDATE codigo = codigo;

INSERT INTO funcionarios (id, nome, cpf, telefone, cargo, especialidade)
VALUES
(1, 'Carlos Silva (Mecânico)', '111.222.333-44', '(85) 98888-1111', 'MECANICO', 'Injeção Eletrônica e Motor'),
(2, 'João Santos (Auxiliar)', '222.333.444-55', '(85) 98888-2222', 'AUXILIAR', 'Suspensão e Troca de Óleo'),
(3, 'Mariana Lima (Admin)', '333.444.555-66', '(85) 98888-3333', 'ADMINISTRATIVO', 'Recepção e Atendimento')
ON DUPLICATE KEY UPDATE id = id;

-- =====================================================
-- ÍNDICES
-- =====================================================

CREATE INDEX idx_veiculos_status
ON veiculos(status);

CREATE INDEX idx_veiculos_cliente
ON veiculos(cliente_id);

CREATE INDEX idx_veiculos_entrada
ON veiculos(entrada);

CREATE INDEX idx_os_placa
ON ordens_servico(placa);

CREATE INDEX idx_os_status
ON ordens_servico(status);

CREATE INDEX idx_pecas_nome
ON pecas(nome);

CREATE INDEX idx_mov_estoque_data
ON movimentacoes_estoque(criado_em);