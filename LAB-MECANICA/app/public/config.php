<?php

$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'oficina';
$user = getenv('DB_USER') ?: 'oficina';
$pass = getenv('DB_PASSWORD') ?: 'oficina123';

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Auto-migração resiliente para garantir que tabelas e colunas existam mesmo se o volume do MySQL já foi inicializado anteriormente
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS funcionarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(150) NOT NULL,
                cpf VARCHAR(20),
                telefone VARCHAR(30),
                cargo ENUM('MECANICO', 'AUXILIAR', 'ADMINISTRATIVO') NOT NULL DEFAULT 'MECANICO',
                especialidade VARCHAR(100) DEFAULT 'Geral',
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
        ");

        try { $pdo->exec("ALTER TABLE veiculos ADD COLUMN posicao_chave INT DEFAULT NULL;"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE veiculos ADD COLUMN mecanico_id INT NULL;"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN defeito_relatado TEXT;"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN itens_veiculo TEXT;"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN estado_lataria TEXT;"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN foto_lataria VARCHAR(255);"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN nivel_combustivel VARCHAR(20);"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN forma_pagamento VARCHAR(50);"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN numero_parcelas INT DEFAULT 1;"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN valor_pago DECIMAL(10,2) DEFAULT 0.00;"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE ordens_servico ADD COLUMN data_pagamento DATETIME NULL;"); } catch (Exception $e) {}

        try {
            $pdo->exec("
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
                    CONSTRAINT fk_nf_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE SET NULL
                );
            ");
        } catch (Exception $e) {}

        try {
            $pdo->exec("
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
                    INDEX idx_chk_os (ordem_servico_id),
                    INDEX idx_chk_placa (placa)
                );
            ");
        } catch (Exception $e) {}

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS checklist_revisao_itens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    checklist_id INT NOT NULL,
                    categoria VARCHAR(100) NOT NULL,
                    item_nome VARCHAR(150) NOT NULL,
                    status_item ENUM('CONFORME', 'REPARADO', 'ATENCAO', 'NAO_APLICAVEL') NOT NULL DEFAULT 'CONFORME',
                    observacao VARCHAR(255) NULL,
                    INDEX idx_item_chk (checklist_id)
                );
            ");
        } catch (Exception $e) {}

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS despesas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    descricao VARCHAR(255) NOT NULL,
                    categoria ENUM('ALUGUEL', 'ENERGIA_AGUA', 'SALARIOS', 'FERRAMENTAS', 'PECAS_REPOSICAO', 'IMPOSTOS', 'OUTROS') NOT NULL DEFAULT 'OUTROS',
                    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    data_vencimento DATE NOT NULL,
                    data_pagamento DATE NULL,
                    status ENUM('PENDENTE', 'PAGO') NOT NULL DEFAULT 'PAGO',
                    observacao TEXT NULL,
                    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ");
        } catch (Exception $e) {}

        try {
            $pdo->exec("
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
            ");
        } catch (Exception $e) {}

        try { $pdo->exec("ALTER TABLE pecas ADD COLUMN categoria VARCHAR(100) DEFAULT 'Geral';"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE pecas ADD COLUMN fornecedor_id INT NULL;"); } catch (Exception $e) {}

        try {
            $countF = $pdo->query("SELECT COUNT(*) FROM fornecedores_autopecas")->fetchColumn();
            if ((int)$countF === 0) {
                $pdo->exec("
                    INSERT INTO fornecedores_autopecas (id, nome_fantasia, razao_social, cnpj, telefone, vendedor_contato, cidade_estado) VALUES
                    (1, 'Autopeças Central', 'Autopeças Central Distribuidora Ltda', '12.345.678/0001-90', '(85) 99111-2222', 'Marcos Oliveira', 'Fortaleza/CE'),
                    (2, 'Distribuidora Cearense de Peças', 'Cearense Autopeças S/A', '98.765.432/0001-11', '(85) 99333-4444', 'Renata Souza', 'Fortaleza/CE');
                ");
            }
        } catch (Exception $e) {}

        $count = $pdo->query("SELECT COUNT(*) FROM funcionarios")->fetchColumn();
        if ((int)$count === 0) {
            $pdo->exec("
                INSERT INTO funcionarios (id, nome, cpf, telefone, cargo, especialidade) VALUES
                (1, 'Carlos Silva (Mecânico)', '111.222.333-44', '(85) 98888-1111', 'MECANICO', 'Injeção Eletrônica e Motor'),
                (2, 'João Santos (Auxiliar)', '222.333.444-55', '(85) 98888-2222', 'AUXILIAR', 'Suspensão e Troca de Óleo'),
                (3, 'Mariana Lima (Admin)', '333.444.555-66', '(85) 98888-3333', 'ADMINISTRATIVO', 'Recepção e Atendimento');
            ");
        }

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS usuarios (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome VARCHAR(150) NOT NULL,
                    usuario VARCHAR(50) NOT NULL,
                    senha VARCHAR(255) NOT NULL,
                    perfil ENUM('ADMIN', 'GERENTE', 'MECANICO', 'ESTOQUE', 'ATENDIMENTO') NOT NULL DEFAULT 'ATENDIMENTO',
                    ativo TINYINT(1) NOT NULL DEFAULT 1,
                    trocar_senha TINYINT(1) NOT NULL DEFAULT 0,
                    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_usuario (usuario)
                );
            ");
        } catch (Exception $e) {}

        try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN trocar_senha TINYINT(1) NOT NULL DEFAULT 0;"); } catch (Exception $e) {}

        try {
            $stmtCheckAdmin = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = 'admin'");
            $stmtCheckAdmin->execute();
            if ((int)$stmtCheckAdmin->fetchColumn() === 0) {
                $hashAdmin = password_hash('12345', PASSWORD_BCRYPT);
                $stmtIns = $pdo->prepare("
                    INSERT INTO usuarios (nome, usuario, senha, perfil, ativo, trocar_senha)
                    VALUES ('Administrador', 'admin', ?, 'ADMIN', 1, 1)
                ");
                $stmtIns->execute([$hashAdmin]);
            }
        } catch (Exception $e) {}

        try {
            $usuariosPadrao = [
                ['Carlos Silva', 'carlos', 'carlos123', 'MECANICO'],
                ['João Santos', 'joao', 'joao123', 'MECANICO'],
                ['Mariana Lima', 'mariana', 'mariana123', 'ATENDIMENTO']
            ];
            foreach ($usuariosPadrao as $u) {
                $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
                $stmtChk->execute([$u[1]]);
                if ((int)$stmtChk->fetchColumn() === 0) {
                    $hashUser = password_hash($u[2], PASSWORD_BCRYPT);
                    $stmtIns = $pdo->prepare("
                        INSERT INTO usuarios (nome, usuario, senha, perfil, ativo, trocar_senha)
                        VALUES (?, ?, ?, ?, 1, 0)
                    ");
                    $stmtIns->execute([$u[0], $u[1], $hashUser, $u[3]]);
                }
            }
        } catch (Exception $e) {}
    } catch (Exception $e) {
        // Ignora erros não críticos de migração
    }

} catch (PDOException $e) {

    die(
        "Erro ao conectar ao banco de dados: "
        . htmlspecialchars($e->getMessage())
    );
}