<?php
require_once 'config.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $placa = strtoupper(trim($_POST['placa'] ?? ''));
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ano = (int) ($_POST['ano'] ?? 0);
    $cor = trim($_POST['cor'] ?? '');
    $km = (int) ($_POST['km_atual'] ?? 0);
    $posicao = (int) ($_POST['posicao_chave'] ?? 0);
    $mecanico_id = (int) ($_POST['mecanico_id'] ?? 0);
    $observacoes = trim($_POST['observacoes'] ?? '');

    $defeito_relatado = trim($_POST['defeito_relatado'] ?? '');
    $nivel_combustivel = trim($_POST['nivel_combustivel'] ?? '1/2');
    $estado_lataria = trim($_POST['estado_lataria'] ?? '');
    $itens_array = $_POST['itens_veiculo'] ?? [];
    $itens_veiculo = is_array($itens_array) ? implode(', ', $itens_array) : '';

    $foto_lataria = null;

    if (isset($_FILES['foto_lataria']) &&
        $_FILES['foto_lataria']['error'] === UPLOAD_ERR_OK) {

        $tmpName = $_FILES['foto_lataria']['tmp_name'];
        $fileName = basename($_FILES['foto_lataria']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed, true)) {
            $newFileName = $placa . '_' . time() . '.' . $ext;
            $targetDir = __DIR__ . '/uploads/';

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                $foto_lataria = 'uploads/' . $newFileName;
            }
        }
    }

    if ($placa === '') {
        $erro = 'Informe a placa do veículo.';
    } elseif ($modelo === '') {
        $erro = 'Informe o modelo do veículo.';
    } elseif ($posicao < 1 || $posicao > 10) {
        $erro = 'Selecione uma posição válida para a chave.';
    } else {

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT placa
                FROM veiculos
                WHERE placa = ?
                  AND saida IS NULL
                LIMIT 1
            ");
            $stmt->execute([$placa]);

            if ($stmt->fetch()) {
                throw new Exception('Este veículo já está registrado na oficina.');
            }

            $stmt = $pdo->prepare("
                SELECT placa
                FROM veiculos
                WHERE posicao_chave = ?
                  AND saida IS NULL
                LIMIT 1
            ");
            $stmt->execute([$posicao]);

            if ($veiculo = $stmt->fetch()) {
                throw new Exception(
                    'A posição ' . $posicao .
                    ' já está ocupada pela placa ' . $veiculo['placa'] . '.'
                );
            }

            $clienteId = null;

            if ($telefone !== '') {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM clientes
                    WHERE telefone = ?
                    LIMIT 1
                ");
                $stmt->execute([$telefone]);

                $cliente = $stmt->fetch();

                if ($cliente) {
                    $clienteId = $cliente['id'];
                }
            }

            if (!$clienteId && $nome !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO clientes (nome, telefone)
                    VALUES (?, ?)
                ");
                $stmt->execute([$nome, $telefone]);
                $clienteId = $pdo->lastInsertId();
            }

            // 1) Cadastra o veículo
            $stmt = $pdo->prepare("
                INSERT INTO veiculos (
                    placa,
                    cliente_id,
                    mecanico_id,
                    modelo,
                    marca,
                    ano,
                    cor,
                    km_atual,
                    status,
                    posicao_chave,
                    observacoes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ENTRADA', ?, ?)
            ");

            $stmt->execute([
                $placa,
                $clienteId,
                $mecanico_id ?: null,
                $modelo,
                $marca,
                $ano ?: null,
                $cor,
                $km ?: null,
                $posicao,
                $observacoes
            ]);

            $veiculoId = $pdo->lastInsertId();

            // 2) Cria UMA única Ordem de Serviço
            $stmtOS = $pdo->prepare("
                INSERT INTO ordens_servico (
                    veiculo_id,
                    placa,
                    defeito_relatado,
                    itens_veiculo,
                    estado_lataria,
                    foto_lataria,
                    nivel_combustivel,
                    observacoes,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtOS->execute([
                $veiculoId,
                $placa,
                $defeito_relatado,
                $itens_veiculo,
                $estado_lataria,
                $foto_lataria,
                $nivel_combustivel,
                $observacoes,
                'ABERTA'
            ]);

            $osId = $pdo->lastInsertId();

            // 3) Registra comando para o Arduino
            $stmtArduino = $pdo->prepare("
                INSERT INTO arduino_comandos (
                    placa,
                    status,
                    comando
                )
                VALUES (?, 'ENTRADA', 'VERMELHO')
            ");

            $stmtArduino->execute([$placa]);

            $pdo->commit();

            $sucesso =
                'Veículo ' . $placa .
                ' registrado com sucesso na posição ' .
                $posicao . '. O.S. #' . $osId . ' criada!';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro = $e->getMessage();
        }
    }
}

$stmtMecanicos = $pdo->query("
    SELECT id, nome, especialidade
    FROM funcionarios
    WHERE ativo = 1
      AND cargo = 'MECANICO'
    ORDER BY nome ASC
");

$mecanicos = $stmtMecanicos->fetchAll();
?>
