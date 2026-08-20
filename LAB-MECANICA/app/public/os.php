<?php

require_once 'config.php';

$id = (int) ($_GET['id'] ?? 0);
$placa = strtoupper(trim($_GET['placa'] ?? ''));

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT os.*, v.modelo, v.marca, v.ano, v.cor, v.km_atual, v.posicao_chave, v.entrada,
               c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email,
               f.nome AS mecanico_nome, f.especialidade AS mecanico_especialidade
        FROM ordens_servico os
        JOIN veiculos v ON v.placa = os.veiculo_id
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN funcionarios f ON f.id = v.mecanico_id
        WHERE os.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
} elseif ($placa !== '') {
    $stmt = $pdo->prepare("
        SELECT os.*, v.modelo, v.marca, v.ano, v.cor, v.km_atual, v.posicao_chave, v.entrada,
               c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email,
               f.nome AS mecanico_nome, f.especialidade AS mecanico_especialidade
        FROM ordens_servico os
        JOIN veiculos v ON v.id = os.veiculo_id
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN funcionarios f ON f.id = v.mecanico_id
        WHERE os.placa = ?
        ORDER BY os.id DESC
        LIMIT 1
    ");
    $stmt->execute([$placa]);
} else {
    die('Ordem de Serviço não informada.');
}

$os = $stmt->fetch();

if (!$os) {
    die('Ordem de Serviço não encontrada.');
}

$osId = (int) $os['id'];
$erroMsg = '';
$sucessoMsg = '';

// Função auxiliar para recalcular totais da O.S.
function recalcularOS(PDO $pdo, int $osId) {
    $totalServicos = (float) $pdo->query("SELECT COALESCE(SUM(valor_total), 0) FROM servicos WHERE ordem_servico_id = $osId")->fetchColumn();
    $totalPecas = (float) $pdo->query("SELECT COALESCE(SUM(valor_total), 0) FROM os_pecas WHERE ordem_servico_id = $osId")->fetchColumn();
    
    $stmt = $pdo->prepare("UPDATE ordens_servico SET valor_servicos = ?, valor_pecas = ? WHERE id = ?");
    $stmt->execute([$totalServicos, $totalPecas, $osId]);
}

// 1. Processar Adição de Serviço
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'adicionar_servico') {
    $descricao = trim($_POST['descricao_servico'] ?? '');
    $valor = (float) str_replace(',', '.', $_POST['valor_servico'] ?? '0');
    $quantidade = (float) str_replace(',', '.', $_POST['quantidade_servico'] ?? '1');

    if ($descricao === '' || $valor <= 0) {
        $erroMsg = 'Informe a descrição e um valor válido para o serviço.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO servicos (ordem_servico_id, descricao, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");
            $stmt->execute([$osId, $descricao, $quantidade, $valor]);
            recalcularOS($pdo, $osId);
            $sucessoMsg = 'Serviço adicionado à O.S. com sucesso!';
        } catch (Exception $e) {
            $erroMsg = 'Erro ao adicionar serviço: ' . $e->getMessage();
        }
    }
}

// 2. Processar Remoção de Serviço
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'remover_servico') {
    $servicoId = (int) ($_POST['servico_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM servicos WHERE id = ? AND ordem_servico_id = ?");
        $stmt->execute([$servicoId, $osId]);
        recalcularOS($pdo, $osId);
        $sucessoMsg = 'Serviço removido da O.S.';
    } catch (Exception $e) {
        $erroMsg = 'Erro ao remover serviço: ' . $e->getMessage();
    }
}

// 3. Processar Adição de Peça do Estoque
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'adicionar_peca') {
    $pecaId = (int) ($_POST['peca_id'] ?? 0);
    $qtdPeca = (int) ($_POST['quantidade_peca'] ?? 1);

    if ($pecaId <= 0 || $qtdPeca <= 0) {
        $erroMsg = 'Selecione uma peça válida e uma quantidade maior que zero.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtPeca = $pdo->prepare("SELECT * FROM pecas WHERE id = ? FOR UPDATE");
            $stmtPeca->execute([$pecaId]);
            $peca = $stmtPeca->fetch();

            if (!$peca) {
                throw new Exception("Peça não encontrada no estoque.");
            }
            if ($peca['quantidade'] < $qtdPeca) {
                throw new Exception("Estoque insuficiente para esta peça! Disponível: " . $peca['quantidade'] . " " . $peca['unidade']);
            }

            $precoUnitario = (float) $peca['preco_venda'];

            $stmt = $pdo->prepare("INSERT INTO os_pecas (ordem_servico_id, peca_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
            $stmt->execute([$osId, $pecaId, $qtdPeca, $precoUnitario]);

            $novoEstoque = $peca['quantidade'] - $qtdPeca;
            $stmtUpd = $pdo->prepare("UPDATE pecas SET quantidade = ? WHERE id = ?");
            $stmtUpd->execute([$novoEstoque, $pecaId]);

            $stmtMov = $pdo->prepare("INSERT INTO movimentacoes_estoque (peca_id, tipo, quantidade, estoque_anterior, estoque_posterior, ordem_servico_id, observacao) VALUES (?, 'SAIDA', ?, ?, ?, ?, ?)");
            $stmtMov->execute([$pecaId, $qtdPeca, $peca['quantidade'], $novoEstoque, $osId, 'Utilizado na O.S. #' . $osId]);

            recalcularOS($pdo, $osId);
            $pdo->commit();
            $sucessoMsg = 'Peça adicionada à O.S. e dada baixa no estoque!';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erroMsg = $e->getMessage();
        }
    }
}

// 4. Processar Remoção de Peça
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'remover_peca') {
    $osPecaId = (int) ($_POST['os_peca_id'] ?? 0);
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM os_pecas WHERE id = ? AND ordem_servico_id = ?");
        $stmt->execute([$osPecaId, $osId]);
        $osPeca = $stmt->fetch();

        if ($osPeca) {
            $stmtDev = $pdo->prepare("UPDATE pecas SET quantidade = quantidade + ? WHERE id = ?");
            $stmtDev->execute([$osPeca['quantidade'], $osPeca['peca_id']]);

            $stmtDel = $pdo->prepare("DELETE FROM os_pecas WHERE id = ?");
            $stmtDel->execute([$osPecaId]);

            recalcularOS($pdo, $osId);
        }

        $pdo->commit();
        $sucessoMsg = 'Peça removida da O.S. e devolvida ao estoque.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erroMsg = $e->getMessage();
    }
}

// 5. Processar Atualização de Desconto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_desconto') {
    $desconto = (float) str_replace(',', '.', $_POST['desconto'] ?? '0');
    try {
        $stmt = $pdo->prepare("UPDATE ordens_servico SET desconto = ? WHERE id = ?");
        $stmt->execute([$desconto, $osId]);
        recalcularOS($pdo, $osId);
        $sucessoMsg = 'Desconto do orçamento atualizado!';
    } catch (Exception $e) {
        $erroMsg = 'Erro ao atualizar desconto: ' . $e->getMessage();
    }
}

// Re-busca dados atualizados da O.S.
$stmt = $pdo->prepare("
    SELECT os.*, v.modelo, v.marca, v.ano, v.cor, v.km_atual, v.posicao_chave, v.entrada,
           c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email,
           f.nome AS mecanico_nome, f.especialidade AS mecanico_especialidade
    FROM ordens_servico os
    JOIN veiculos v ON v.id = os.veiculo_id
    LEFT JOIN clientes c ON c.id = v.cliente_id
    LEFT JOIN funcionarios f ON f.id = v.mecanico_id
    WHERE os.id = ?
    LIMIT 1
");
$stmt->execute([$osId]);
$os = $stmt->fetch();

// Busca serviços cadastrados
$stmtServicos = $pdo->prepare("SELECT * FROM servicos WHERE ordem_servico_id = ? ORDER BY id ASC");
$stmtServicos->execute([$osId]);
$listaServicos = $stmtServicos->fetchAll();

// Busca peças trocadas na O.S.
$stmtPecasOS = $pdo->prepare("
    SELECT osp.*, p.codigo, p.nome, p.fabricante, p.unidade
    FROM os_pecas osp
    JOIN pecas p ON p.id = osp.peca_id
    WHERE osp.ordem_servico_id = ?
    ORDER BY osp.id ASC
");
$stmtPecasOS->execute([$osId]);
$listaPecasOS = $stmtPecasOS->fetchAll();

// Busca peças em estoque para o dropdown
$stmtPecasEstoque = $pdo->query("SELECT id, codigo, nome, fabricante, quantidade, unidade, preco_venda FROM pecas ORDER BY nome ASC");
$pecasEstoque = $stmtPecasEstoque->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem de Serviço #<?= sprintf('%05d', $os['id']) ?> - <?= htmlspecialchars($os['placa']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        @media print {
            header, .acoes-print, .no-print, .form-item-os {
                display: none !important;
            }
            body {
                background: #fff;
                color: #000;
            }
            .form-container {
                box-shadow: none;
                border: 1px solid #ccc;
                max-width: 100%;
            }
        }
        .os-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .os-box h4 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .item-tag {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin: 2px;
        }
        .foto-preview {
            max-width: 100%;
            max-height: 250px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            margin-top: 10px;
        }
        .tabela-itens {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .tabela-itens th, .tabela-itens td {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            font-size: 0.9rem;
        }
        .tabela-itens th {
            background: #f1f5f9;
        }
    </style>
</head>
<body>

<?php
$subtitulo = 'Ordem de Serviço & Orçamento';
include 'header.php';
?>

<main>
    <div class="form-container" style="max-width: 900px;">
        
        <?php if ($erroMsg): ?>
            <div class="alerta erro no-print"><?= htmlspecialchars($erroMsg) ?></div>
        <?php endif; ?>

        <?php if ($sucessoMsg): ?>
            <div class="alerta sucesso no-print"><?= htmlspecialchars($sucessoMsg) ?></div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px;">
            <div>
                <h2>📋 Ordem de Serviço #<?= sprintf('%05d', $os['id']) ?></h2>
                <p style="margin: 0; color: #64748b;">Abertura: <?= date('d/m/Y H:i', strtotime($os['data_abertura'])) ?></p>
                <?php if ($os['data_fechamento']): ?>
                    <p style="margin: 3px 0 0 0; color: #16a34a; font-weight: bold;">Baixa/Fechamento: <?= date('d/m/Y H:i', strtotime($os['data_fechamento'])) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <span class="status status-<?= strtolower($os['status']) ?>" style="font-size: 1.1rem; padding: 6px 12px;">
                    <?= htmlspecialchars($os['status']) ?>
                </span>
            </div>
        </div>

        <?php if ($os['status'] === 'FINALIZADA'): ?>
            <div class="os-box no-print" style="background: #f0fdf4; border-color: #86efac; text-align: center; padding: 15px;">
                <h3 style="color: #166534; margin: 0;">🎉 Ordem de Serviço Finalizada & Veículo Entregue</h3>
                <p style="margin: 5px 0 0 0; color: #15803d;">Baixa efetuada em <strong><?= date('d/m/Y H:i', strtotime($os['data_fechamento'])) ?></strong></p>
            </div>
        <?php else: ?>
            <div class="no-print" style="background: #dcfce7; border: 1px solid #86efac; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h4 style="margin: 0; color: #166534;">🚗 Pronto para Entregar ao Cliente?</h4>
                    <p style="margin: 4px 0 0 0; color: #15803d; font-size: 0.9rem;">Processa o pagamento, dá baixa na O.S., encerra o atendimento e desliga o LED no Arduino.</p>
                </div>
                <a href="saida.php?placa=<?= urlencode($os['placa']) ?>" class="botao" style="background: #16a34a; text-decoration: none; font-weight: bold;">
                    ✅ Dar Baixa na O.S. e Entregar Veículo
                </a>
            </div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="os-box">
                <h4>👤 Cliente</h4>
                <p><strong>Nome:</strong> <?= htmlspecialchars($os['cliente_nome'] ?? 'Cliente Não Cadastrado') ?></p>
                <p><strong>Telefone:</strong> <?= htmlspecialchars($os['cliente_telefone'] ?? '-') ?></p>
                <p><strong>E-mail:</strong> <?= htmlspecialchars($os['cliente_email'] ?? '-') ?></p>
            </div>

            <div class="os-box">
                <h4>🚗 Veículo</h4>
                <p><strong>Placa:</strong> <span style="font-size: 1.2rem; font-weight: bold; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($os['placa']) ?></span></p>
                <p><strong>Veículo:</strong> <?= htmlspecialchars($os['marca'] . ' ' . $os['modelo']) ?></p>
                <p><strong>Ano / Cor / KM:</strong> <?= htmlspecialchars($os['ano'] ?? '-') ?> | <?= htmlspecialchars($os['cor'] ?? '-') ?> | <?= number_format($os['km_atual'] ?? 0, 0, ',', '.') ?> KM</p>
                <p><strong>🔑 Posição Chave:</strong> <?= $os['posicao_chave'] ? 'Posição ' . sprintf('%02d', $os['posicao_chave']) : 'Não alocado' ?></p>
                <p><strong>👨‍🔧 Mecânico Responsável:</strong> <?= $os['mecanico_nome'] ? htmlspecialchars($os['mecanico_nome']) . ' (' . htmlspecialchars($os['mecanico_especialidade']) . ')' : 'Não atribuído' ?></p>
            </div>
        </div>

        <div class="os-box">
            <h4>🛠️ Vistoria & Defeito Relatado</h4>
            <p><strong>Defeito Apresentado pelo Cliente:</strong></p>
            <div style="background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; margin-bottom: 10px;">
                <?= nl2br(htmlspecialchars($os['defeito_relatado'] ?: 'Nenhum defeito registrado.')) ?>
            </div>

            <div class="grid-2">
                <div>
                    <p><strong>⛽ Nível de Combustível:</strong> <?= htmlspecialchars($os['nivel_combustivel'] ?: 'Não informado') ?></p>
                    <p><strong>📦 Pertences no Veículo:</strong></p>
                    <div>
                        <?php if ($os['itens_veiculo']): ?>
                            <?php foreach (explode(',', $os['itens_veiculo']) as $item): ?>
                                <span class="item-tag">✓ <?= htmlspecialchars(trim($item)) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <em>Nenhum item marcado.</em>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <p><strong>🚗 Estado da Lataria:</strong></p>
                    <div style="background: #fff; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.9rem;">
                        <?= nl2br(htmlspecialchars($os['estado_lataria'] ?: 'Sem avarias prévias informadas.')) ?>
                    </div>
                </div>
            </div>

            <?php if ($os['foto_lataria'] && file_exists(__DIR__ . '/' . $os['foto_lataria'])): ?>
                <div style="margin-top: 15px;">
                    <p><strong>📷 Foto Anexa do Veículo / Lataria:</strong></p>
                    <img src="<?= htmlspecialchars($os['foto_lataria']) ?>" alt="Foto da Lataria" class="foto-preview">
                </div>
            <?php endif; ?>
        </div>

        <!-- =========================================================
             SERVIÇOS PRESTADOS / MÃO DE OBRA
        ========================================================= -->
        <div class="os-box">
            <h4>🛠️ Serviços Realizados (Mão de Obra)</h4>

            <div class="form-item-os no-print" style="margin-bottom: 15px; background: #e0f2fe; padding: 12px; border-radius: 6px;">
                <form method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="acao" value="adicionar_servico">
                    <input type="text" name="descricao_servico" placeholder="Descrição do Serviço (ex: Troca de pastilhas de freio)" required style="flex: 2; min-width: 200px; padding: 8px;">
                    <input type="text" name="valor_servico" placeholder="Valor R$ (ex: 80.00)" required style="flex: 1; min-width: 100px; padding: 8px;">
                    <button type="submit" class="botao" style="padding: 8px 14px;">+ Adicionar Serviço</button>
                </form>
            </div>

            <table class="tabela-itens">
                <thead>
                    <tr>
                        <th>Descrição do Serviço</th>
                        <th style="width: 100px; text-align: center;">Qtd</th>
                        <th style="width: 120px; text-align: right;">Valor Unit.</th>
                        <th style="width: 120px; text-align: right;">Subtotal</th>
                        <th class="no-print" style="width: 50px; text-align: center;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$listaServicos): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8;">Nenhum serviço lançado nesta O.S.</td></tr>
                    <?php else: ?>
                        <?php foreach ($listaServicos as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['descricao']) ?></td>
                                <td style="text-align: center;"><?= number_format($s['quantidade'], 0) ?></td>
                                <td style="text-align: right;">R$ <?= number_format($s['valor_unitario'], 2, ',', '.') ?></td>
                                <td style="text-align: right;"><strong>R$ <?= number_format($s['valor_total'], 2, ',', '.') ?></strong></td>
                                <td class="no-print" style="text-align: center;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remover este serviço?');">
                                        <input type="hidden" name="acao" value="remover_servico">
                                        <input type="hidden" name="servico_id" value="<?= $s['id'] ?>">
                                        <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.1rem;">❌</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- =========================================================
             PEÇAS TROCADAS / USADAS DO ESTOQUE
        ========================================================= -->
        <div class="os-box">
            <h4>📦 Peças Trocadas (Do Estoque)</h4>

            <div class="form-item-os no-print" style="margin-bottom: 15px; background: #fef3c7; padding: 12px; border-radius: 6px;">
                <form method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="acao" value="adicionar_peca">
                    <select name="peca_id" required style="flex: 2; min-width: 200px; padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1;">
                        <option value="">Selecione a peça do estoque</option>
                        <?php foreach ($pecasEstoque as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['codigo']) ?> - <?= htmlspecialchars($p['nome']) ?> (Estoque: <?= $p['quantidade'] ?> <?= $p['unidade'] ?> | R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantidade_peca" value="1" min="1" required style="width: 70px; padding: 8px;">
                    <button type="submit" class="botao" style="padding: 8px 14px; background: #d97706;">+ Adicionar Peça</button>
                </form>
            </div>

            <table class="tabela-itens">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Peça / Componente</th>
                        <th>Fabricante</th>
                        <th style="width: 80px; text-align: center;">Qtd</th>
                        <th style="width: 120px; text-align: right;">Preço Unit.</th>
                        <th style="width: 120px; text-align: right;">Subtotal</th>
                        <th class="no-print" style="width: 50px; text-align: center;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$listaPecasOS): ?>
                        <tr><td colspan="7" style="text-align: center; color: #94a3b8;">Nenhuma peça lançada nesta O.S.</td></tr>
                    <?php else: ?>
                        <?php foreach ($listaPecasOS as $pOS): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($pOS['codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($pOS['nome']) ?></td>
                                <td><?= htmlspecialchars($pOS['fabricante'] ?? '-') ?></td>
                                <td style="text-align: center;"><?= $pOS['quantidade'] ?> <?= htmlspecialchars($pOS['unidade']) ?></td>
                                <td style="text-align: right;">R$ <?= number_format($pOS['preco_unitario'], 2, ',', '.') ?></td>
                                <td style="text-align: right;"><strong>R$ <?= number_format($pOS['valor_total'], 2, ',', '.') ?></strong></td>
                                <td class="no-print" style="text-align: center;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remover esta peça da O.S.? A quantidade será devolvida ao estoque.');">
                                        <input type="hidden" name="acao" value="remover_peca">
                                        <input type="hidden" name="os_peca_id" value="<?= $pOS['id'] ?>">
                                        <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.1rem;">❌</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- =========================================================
             RESUMO FINANCEIRO DO ORÇAMENTO
        ========================================================= -->
        <div class="os-box">
            <h4>💰 Resumo do Orçamento</h4>
            <div style="font-size: 1rem; line-height: 1.8;">
                <p style="display: flex; justify-content: space-between; margin: 5px 0;">
                    <span>Total Mão de Obra / Serviços:</span>
                    <strong>R$ <?= number_format($os['valor_servicos'] ?? 0, 2, ',', '.') ?></strong>
                </p>
                <p style="display: flex; justify-content: space-between; margin: 5px 0;">
                    <span>Total Peças Trocadas:</span>
                    <strong>R$ <?= number_format($os['valor_pecas'] ?? 0, 2, ',', '.') ?></strong>
                </p>

                <div class="no-print" style="margin: 10px 0; background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="acao" value="atualizar_desconto">
                        <label style="white-space: nowrap;">Desconto Concedido (R$):</label>
                        <input type="text" name="desconto" value="<?= number_format($os['desconto'] ?? 0, 2, ',', '.') ?>" style="width: 120px; padding: 6px;">
                        <button type="submit" class="botao secundario" style="padding: 6px 12px; font-size: 0.85rem;">Aplicar Desconto</button>
                    </form>
                </div>

                <?php if ($os['desconto'] > 0): ?>
                    <p style="display: flex; justify-content: space-between; margin: 5px 0; color: #ef4444;">
                        <span>Desconto:</span>
                        <strong>- R$ <?= number_format($os['desconto'], 2, ',', '.') ?></strong>
                    </p>
                <?php endif; ?>

                <hr>

                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 1.3rem; margin-top: 10px;">
                    <span>VALOR TOTAL DO ORÇAMENTO:</span>
                    <strong style="color: #2563eb;">R$ <?= number_format($os['valor_total'] ?? 0, 2, ',', '.') ?></strong>
                </div>
            </div>
        </div>

        <!-- =========================================================
             COMPROVANTE DE PAGAMENTO (SE QUITADO)
        ========================================================= -->
        <?php if ($os['forma_pagamento']): ?>
            <div class="os-box" style="font-size: 1.1rem; background: #f0fdf4; border-color: #bbf7d0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; color: #16a34a; font-weight: bold;">
                            ✅ Pagamento Efetuado: 
                            <?php
                            $nomesPagamento = [
                                'DINHEIRO' => '💵 Dinheiro',
                                'PIX' => '📱 PIX',
                                'CARTAO_DEBITO' => '💳 Cartão de Débito',
                                'CARTAO_CREDITO' => '💳 Cartão de Crédito (À Vista)',
                                'PARCELADO' => '💳 Cartão de Crédito (' . $os['numero_parcelas'] . 'x de R$ ' . number_format(($os['valor_total'] / max(1, $os['numero_parcelas'])), 2, ',', '.') . ')'
                            ];
                            echo $nomesPagamento[$os['forma_pagamento']] ?? $os['forma_pagamento'];
                            ?>
                        </p>
                        <?php if ($os['data_pagamento']): ?>
                            <small style="color: #15803d;">Quitado em: <?= date('d/m/Y H:i', strtotime($os['data_pagamento'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong style="color: #16a34a; font-size: 1.3rem;">QUITADO</strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="acoes-print" style="margin-top: 20px; display: flex; gap: 10px;">
            <button onclick="window.print()" class="botao">🖨️ Imprimir Ordem de Serviço</button>
            <a href="status.php?placa=<?= urlencode($os['placa']) ?>" class="botao secundario">⚙️ Gerenciar Status</a>
            <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>
        </div>

    </div>
</main>

</body>
</html>
