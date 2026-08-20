<?php

require_once 'config.php';

$placa = strtoupper(trim($_GET['placa'] ?? $_POST['placa'] ?? ''));

$erro = '';
$sucesso = '';

if ($placa === '') {
    die('Placa não informada.');
}

/*
 * Processa alteração de status
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $novoStatus = $_POST['status'] ?? '';

    $statusPermitidos = [
        'ENTRADA',
        'ORCAMENTO',
        'SERVICO',
        'LIBERADO'
    ];

    if (!in_array($novoStatus, $statusPermitidos, true)) {

        $erro = 'Status inválido.';

    } else {

        try {

            $pdo->beginTransaction();

            /*
             * Busca veículo
             */

            $stmt = $pdo->prepare("
                SELECT *
                FROM veiculos
                WHERE placa = ?
                AND saida IS NULL
                LIMIT 1
            ");

            $stmt->execute([$placa]);

            $veiculo = $stmt->fetch();

            if (!$veiculo) {

                throw new Exception(
                    'Veículo não encontrado na oficina.'
                );
            }

            /*
             * Atualiza status
             */

            $stmt = $pdo->prepare("
                UPDATE veiculos
                SET status = ?
                WHERE placa = ?
            ");

            $stmt->execute([
                $novoStatus,
                $placa
            ]);

            /*
             * Define comando para Arduino
             */

            $comandos = [
                'ENTRADA'   => 'VERMELHO',
                'ORCAMENTO' => 'AMARELO',
                'SERVICO'   => 'AZUL',
                'LIBERADO'  => 'VERDE'
            ];

            $comando = $comandos[$novoStatus];

            /*
             * Registra comando
             */

            $stmt = $pdo->prepare("
                INSERT INTO arduino_comandos (
                    placa,
                    status,
                    comando
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $placa,
                $novoStatus,
                $comando
            ]);

            $pdo->commit();

            $sucesso =
                'Status alterado para ' .
                $novoStatus .
                '.';

        }

        catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro = $e->getMessage();
        }
    }
}

// Processar atualização do mecânico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_mecanico') {
    $mecanicoId = (int) ($_POST['mecanico_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE veiculos SET mecanico_id = ? WHERE placa = ?");
        $stmt->execute([$mecanicoId ?: null, $placa]);
        $sucesso = 'Mecânico responsável atribuído com sucesso!';
    } catch (Exception $e) {
        $erro = 'Erro ao atribuir mecânico: ' . $e->getMessage();
    }
}

/*
 * Busca veículo atualizado
 */

$stmt = $pdo->prepare("
    SELECT
        v.*,
        c.nome AS cliente_nome,
        c.telefone AS cliente_telefone,
        f.nome AS mecanico_nome,
        f.especialidade AS mecanico_especialidade
    FROM veiculos v
    LEFT JOIN clientes c
        ON c.id = v.cliente_id
    LEFT JOIN funcionarios f
        ON f.id = v.mecanico_id
    WHERE v.placa = ?
    AND v.saida IS NULL
    LIMIT 1
");

$stmt->execute([$placa]);

$veiculo = $stmt->fetch();

if (!$veiculo) {
    die('Veículo não encontrado.');
}

// Busca mecânicos para o select
$stmtMecanicos = $pdo->query("SELECT id, nome, especialidade FROM funcionarios WHERE ativo = 1 AND cargo = 'MECANICO' ORDER BY nome ASC");
$mecanicos = $stmtMecanicos->fetchAll();

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($veiculo['placa']) ?>
        - EDTEC-SOLUTION
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>

<body>

<?php
$subtitulo = 'Controle de Veículo';
include 'header.php';
?>

<main>

    <div class="form-container">

        <h2>
            🚗
            <?= htmlspecialchars($veiculo['placa']) ?>
        </h2>

        <?php if ($erro): ?>

            <div class="alerta erro">

                <?= htmlspecialchars($erro) ?>

            </div>

        <?php endif; ?>


        <?php if ($sucesso): ?>

            <div class="alerta sucesso">

                <?= htmlspecialchars($sucesso) ?>

            </div>

        <?php endif; ?>


        <div class="dados-veiculo">

            <p>
                <strong>Cliente:</strong>
                <?= htmlspecialchars(
                    $veiculo['cliente_nome'] ?? '-'
                ) ?>
            </p>

            <p>
                <strong>Telefone:</strong>
                <?= htmlspecialchars(
                    $veiculo['cliente_telefone'] ?? '-'
                ) ?>
            </p>

            <p>
                <strong>Veículo:</strong>
                <?= htmlspecialchars(
                    $veiculo['marca'] . ' ' .
                    $veiculo['modelo']
                ) ?>
            </p>

            <p>
                <strong>Ano:</strong>
                <?= htmlspecialchars(
                    $veiculo['ano'] ?? '-'
                ) ?>
            </p>

            <p>
                <strong>Cor:</strong>
                <?= htmlspecialchars(
                    $veiculo['cor'] ?? '-'
                ) ?>
            </p>

            <p>
                <strong>KM:</strong>
                <?= htmlspecialchars(
                    $veiculo['km_atual'] ?? '-'
                ) ?>
            </p>

            <p>
                <strong>🔑 Posição da chave:</strong>

                <?= htmlspecialchars(
                    $veiculo['posicao_chave'] ?? '-'
                ) ?>

            </p>

            <p>
                <strong>Entrada:</strong>

                <?= date(
                    'd/m/Y H:i',
                    strtotime($veiculo['entrada'])
                ) ?>

            </p>

            <p>
                <strong>👨‍🔧 Mecânico Responsável:</strong>
                <?= $veiculo['mecanico_nome']
                    ? htmlspecialchars($veiculo['mecanico_nome']) . ' (' . htmlspecialchars($veiculo['mecanico_especialidade']) . ')'
                    : '<em style="color: #e74c3c;">Nenhum mecânico atribuído</em>'
                ?>
            </p>

        </div>

        <form method="POST" style="margin: 15px 0; background: #f8fafc; padding: 10px 15px; border-radius: 6px; border: 1px solid #e2e8f0; display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="acao" value="atualizar_mecanico">
            <label style="white-space: nowrap; font-weight: bold;">Atribuir Mecânico:</label>
            <select name="mecanico_id" style="flex: 1; padding: 6px; border-radius: 4px; border: 1px solid #cbd5e1;">
                <option value="">-- Sem mecânico --</option>
                <?php foreach ($mecanicos as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($veiculo['mecanico_id'] == $m['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nome']) ?> - <?= htmlspecialchars($m['especialidade']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="botao" style="padding: 6px 12px; font-size: 0.9rem;">Salvar Mecânico</button>
        </form>


        <hr>


        <h3>Status atual</h3>

        <div class="status-grande status-<?= strtolower(
            $veiculo['status']
        ) ?>">

            <?php

            $icones = [
                'ENTRADA'   => '🔴',
                'ORCAMENTO' => '🟡',
                'SERVICO'   => '🔵',
                'LIBERADO'  => '🟢'
            ];

            echo $icones[$veiculo['status']] .
                ' ' .
                htmlspecialchars($veiculo['status']);

            ?>

        </div>


        <h3>Alterar status</h3>

        <div class="status-botoes">

<?php

$statusBotoes = [
    'ENTRADA' => [
        'texto' => '🔴 Entrada',
        'classe' => 'btn-vermelho'
    ],

    'ORCAMENTO' => [
        'texto' => '🟡 Orçamento',
        'classe' => 'btn-amarelo'
    ],

    'SERVICO' => [
        'texto' => '🔵 Em serviço',
        'classe' => 'btn-azul'
    ],

    'LIBERADO' => [
        'texto' => '🟢 Liberado',
        'classe' => 'btn-verde'
    ]
];

foreach ($statusBotoes as $status => $botao):

?>

            <form method="POST">

                <input
                    type="hidden"
                    name="placa"
                    value="<?= htmlspecialchars($placa) ?>"
                >

                <input
                    type="hidden"
                    name="status"
                    value="<?= $status ?>"
                >

                <button
                    type="submit"
                    class="status-btn <?= $botao['classe'] ?>"
                >

                    <?= $botao['texto'] ?>

                </button>

            </form>

<?php endforeach; ?>

        </div>


        <hr>


        <form
            method="POST"
            action="saida.php"
            onsubmit="return confirm(
                'Confirmar saída deste veículo?'
            );"
        >

            <input
                type="hidden"
                name="placa"
                value="<?= htmlspecialchars($placa) ?>"
            >

            <button
                type="submit"
                class="botao btn-saida"
            >

                🚗 Registrar saída do veículo

            </button>

        </form>


        <br>


        <a
            href="os.php?placa=<?= urlencode($placa) ?>"
            class="botao"
            style="margin-bottom: 10px;"
        >

            📋 Visualizar Ordem de Serviço (O.S.)

        </a>

        <a
            href="index.php"
            class="botao secundario"
        >

            ← Voltar ao dashboard

        </a>

    </div>

</main>

</body>

</html>