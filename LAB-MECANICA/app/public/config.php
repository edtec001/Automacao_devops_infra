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

        $count = $pdo->query("SELECT COUNT(*) FROM funcionarios")->fetchColumn();
        if ((int)$count === 0) {
            $pdo->exec("
                INSERT INTO funcionarios (id, nome, cpf, telefone, cargo, especialidade) VALUES
                (1, 'Carlos Silva (Mecânico)', '111.222.333-44', '(85) 98888-1111', 'MECANICO', 'Injeção Eletrônica e Motor'),
                (2, 'João Santos (Auxiliar)', '222.333.444-55', '(85) 98888-2222', 'AUXILIAR', 'Suspensão e Troca de Óleo'),
                (3, 'Mariana Lima (Admin)', '333.444.555-66', '(85) 98888-3333', 'ADMINISTRATIVO', 'Recepção e Atendimento');
            ");
        }
    } catch (Exception $e) {
        // Ignora erros não críticos de migração
    }

} catch (PDOException $e) {

    die(
        "Erro ao conectar ao banco de dados: "
        . htmlspecialchars($e->getMessage())
    );
}