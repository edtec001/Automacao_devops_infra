<?php

require_once 'config.php';

$placa = strtoupper(trim($_REQUEST['placa'] ?? ''));
$erro = '';
$sucesso = false;

if ($placa === '') {
    die('Placa não informada.');
}

// Busca veículo ativo e sua O.S. mais recente
$stmt = $pdo->prepare("
    SELECT v.*, os.id AS os_id, os.valor_total, os.valor_servicos, os.valor_pecas, os.desconto,
           c.nome AS cliente_nome
    FROM veiculos v
    LEFT JOIN ordens_servico os ON os.placa = v.placa
    LEFT JOIN clientes c ON c.id = v.cliente_id
    WHERE v.placa = ?
    AND v.saida IS NULL
    ORDER BY os.id DESC
    LIMIT 1
");
$stmt->execute([$placa]);
$veiculo = $stmt->fetch();

if (!$veiculo) {
    die('Veículo não encontrado na oficina ou já teve sua saída registrada.');
}

$valorTotal = (float) ($veiculo['valor_total'] ?? 0);

// Processar confirmação de pagamento e saída
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_saida'])) {
    $formaPagamento = $_POST['forma_pagamento'] ?? 'DINHEIRO';
    $numeroParcelas = (int) ($_POST['numero_parcelas'] ?? 1);
    $valorPago = (float) str_replace(',', '.', $_POST['valor_pago'] ?? $valorTotal);

    if ($formaPagamento !== 'PARCELADO') {
        $numeroParcelas = 1;
    }

    try {
        $pdo->beginTransaction();

        // 1. Registra saída do veículo
        $stmt = $pdo->prepare("
            UPDATE veiculos
            SET saida = NOW(), status = 'LIBERADO'
            WHERE placa = ?
        ");
        $stmt->execute([$placa]);

        // 2. Atualiza a O.S. com dados de pagamento e finaliza
        if ($veiculo['os_id']) {
            $stmt = $pdo->prepare("
                UPDATE ordens_servico
                SET forma_pagamento = ?,
                    numero_parcelas = ?,
                    valor_pago = ?,
                    data_pagamento = NOW(),
                    data_fechamento = NOW(),
                    status = 'FINALIZADA'
                WHERE id = ?
            ");
            $stmt->execute([$formaPagamento, $numeroParcelas, $valorPago, $veiculo['os_id']]);
        }

        // 3. Desliga LED no Arduino
        $stmt = $pdo->prepare("
            INSERT INTO arduino_comandos (placa, status, comando)
            VALUES (?, 'SAIDA', 'DESLIGAR')
        ");
        $stmt->execute([$placa]);

        $pdo->commit();
        $sucesso = true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout & Saída - <?= htmlspecialchars($placa) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .checkout-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .pagamento-opcao {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pagamento-opcao:hover {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .pagamento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .parcelas-box {
            display: none;
            margin-top: 15px;
            background: #e0f2fe;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #7dd3fc;
        }
    </style>
</head>
<body>

<?php
$subtitulo = 'Acerto Financeiro e Saída';
include 'header.php';
?>

<main>
    <div class="form-container" style="max-width: 800px;">

        <?php if ($sucesso): ?>
            <div class="alerta sucesso">
                🎉 Saída do veículo <strong><?= htmlspecialchars($placa) ?></strong> registrada com sucesso!
                <br>
                O pagamento foi processado e o comando para desligar o LED do Arduino foi enviado.
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <a href="os.php?placa=<?= urlencode($placa) ?>" class="botao">📋 Ver Ordem de Serviço & Recibo</a>
                <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>
            </div>
        <?php else: ?>

            <h2>🚗 Checkout de Saída - <?= htmlspecialchars($placa) ?></h2>
            <p><strong>Cliente:</strong> <?= htmlspecialchars($veiculo['cliente_nome'] ?? 'Não informado') ?> | <strong>Veículo:</strong> <?= htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']) ?></p>

            <?php if ($erro): ?>
                <div class="alerta erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <div class="checkout-box">
                <h3 style="margin-top:0;">💰 Resumo Financeiro</h3>
                <p><strong>Serviços:</strong> R$ <?= number_format($veiculo['valor_servicos'] ?? 0, 2, ',', '.') ?></p>
                <p><strong>Peças:</strong> R$ <?= number_format($veiculo['valor_pecas'] ?? 0, 2, ',', '.') ?></p>
                <p><strong>Desconto:</strong> R$ <?= number_format($veiculo['valor_desconto'] ?? 0, 2, ',', '.') ?></p>
                <hr>
                <h2 style="color: #2563eb; margin: 5px 0;">Total a Pagar: R$ <span id="valor-total-exibicao"><?= number_format($valorTotal, 2, ',', '.') ?></span></h2>
            </div>

            <form method="POST">
                <input type="hidden" name="placa" value="<?= htmlspecialchars($placa) ?>">
                <input type="hidden" name="confirmar_saida" value="1">

                <h3>💳 Selecione a Forma de Pagamento</h3>
                
                <div class="pagamento-grid">
                    <label class="pagamento-opcao">
                        <input type="radio" name="forma_pagamento" value="DINHEIRO" checked onclick="atualizarFormaPagamento('DINHEIRO')">
                        <span>💵 Dinheiro</span>
                    </label>

                    <label class="pagamento-opcao">
                        <input type="radio" name="forma_pagamento" value="PIX" onclick="atualizarFormaPagamento('PIX')">
                        <span>📱 PIX</span>
                    </label>

                    <label class="pagamento-opcao">
                        <input type="radio" name="forma_pagamento" value="CARTAO_DEBITO" onclick="atualizarFormaPagamento('CARTAO_DEBITO')">
                        <span>💳 Cartão Débito</span>
                    </label>

                    <label class="pagamento-opcao">
                        <input type="radio" name="forma_pagamento" value="CARTAO_CREDITO" onclick="atualizarFormaPagamento('CARTAO_CREDITO')">
                        <span>💳 Crédito (À Vista)</span>
                    </label>

                    <label class="pagamento-opcao">
                        <input type="radio" name="forma_pagamento" value="PARCELADO" onclick="atualizarFormaPagamento('PARCELADO')">
                        <span>💳 Crédito (Parcelado)</span>
                    </label>
                </div>

                <div id="parcelas-container" class="parcelas-box">
                    <label><strong>Opções de Parcelamento:</strong></label>
                    <select name="numero_parcelas" id="select-parcelas" style="width: 100%; margin-top: 8px; padding: 10px; border-radius: 6px;">
                        <?php for ($i = 2; $i <= 12; $i++): 
                            $valorParcela = $valorTotal / $i;
                        ?>
                            <option value="<?= $i ?>"><?= $i ?>x de R$ <?= number_format($valorParcela, 2, ',', '.') ?> sem juros</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="campo" style="margin-top: 20px;">
                    <label>Valor Pago (R$)</label>
                    <input type="text" name="valor_pago" value="<?= number_format($valorTotal, 2, ',', '.') ?>" required>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="botao" style="background: #16a34a; font-size: 1.1rem;">✅ Confirmar Pagamento e Registrar Saída</button>
                    <a href="status.php?placa=<?= urlencode($placa) ?>" class="botao secundario">Voltar</a>
                </div>
            </form>

        <?php endif; ?>
    </div>
</main>

<script>
function atualizarFormaPagamento(forma) {
    const parcelasBox = document.getElementById('parcelas-container');
    if (forma === 'PARCELADO') {
        parcelasBox.style.display = 'block';
    } else {
        parcelasBox.style.display = 'none';
    }
}
</script>

</body>
</html>