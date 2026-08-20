<?php

require_once 'config.php';

$erro = '';
$sucesso = '';

// Cadastrar nova peça
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'nova_peca') {
    $codigo = trim($_POST['codigo'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $fabricante = trim($_POST['fabricante'] ?? '');
    $unidade = trim($_POST['unidade'] ?? 'UN');
    $quantidade = (int) ($_POST['quantidade'] ?? 0);
    $estoque_minimo = (int) ($_POST['estoque_minimo'] ?? 0);
    $preco_custo = (float) str_replace(',', '.', $_POST['preco_custo'] ?? '0');
    $preco_venda = (float) str_replace(',', '.', $_POST['preco_venda'] ?? '0');
    $localizacao = trim($_POST['localizacao'] ?? '');

    if ($codigo === '' || $nome === '') {
        $erro = 'Código e Nome da peça são obrigatórios.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pecas (codigo, nome, fabricante, unidade, quantidade, estoque_minimo, preco_custo, preco_venda, localizacao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$codigo, $nome, $fabricante, $unidade, $quantidade, $estoque_minimo, $preco_custo, $preco_venda, $localizacao]);
            $sucesso = 'Peça cadastrada com sucesso!';
        } catch (Exception $e) {
            $erro = 'Erro ao cadastrar peça: ' . $e->getMessage();
        }
    }
}

// Atualizar quantidade no estoque (Movimentação)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'movimentar') {
    $peca_id = (int) ($_POST['peca_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $qtd_movimentada = (int) ($_POST['quantidade'] ?? 0);
    $observacao = trim($_POST['observacao'] ?? '');

    if ($peca_id <= 0 || $qtd_movimentada <= 0 || !in_array($tipo, ['ENTRADA', 'SAIDA', 'AJUSTE'])) {
        $erro = 'Dados de movimentação inválidos.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT quantidade FROM pecas WHERE id = ? FOR UPDATE");
            $stmt->execute([$peca_id]);
            $peca = $stmt->fetch();

            if (!$peca) {
                throw new Exception("Peça não encontrada.");
            }

            $estoque_anterior = (int) $peca['quantidade'];
            if ($tipo === 'ENTRADA') {
                $estoque_posterior = $estoque_anterior + $qtd_movimentada;
            } elseif ($tipo === 'SAIDA') {
                if ($estoque_anterior < $qtd_movimentada) {
                    throw new Exception("Estoque insuficiente para esta saída.");
                }
                $estoque_posterior = $estoque_anterior - $qtd_movimentada;
            } else { // AJUSTE
                $estoque_posterior = $qtd_movimentada;
            }

            $stmt = $pdo->prepare("UPDATE pecas SET quantidade = ? WHERE id = ?");
            $stmt->execute([$estoque_posterior, $peca_id]);

            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes_estoque (peca_id, tipo, quantidade, estoque_anterior, estoque_posterior, observacao)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$peca_id, $tipo, $qtd_movimentada, $estoque_anterior, $estoque_posterior, $observacao]);

            $pdo->commit();
            $sucesso = 'Movimentação de estoque registrada com sucesso!';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = $e->getMessage();
        }
    }
}

// Pesquisa
$busca = trim($_GET['busca'] ?? '');
if ($busca !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM pecas 
        WHERE codigo LIKE ? OR nome LIKE ? OR fabricante LIKE ?
        ORDER BY nome ASC
    ");
    $stmt->execute(['%' . $busca . '%', '%' . $busca . '%', '%' . $busca . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM pecas ORDER BY nome ASC");
}
$pecas = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque de Peças - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
$subtitulo = 'Controle de Estoque de Peças';
include 'header.php';
?>

<main>
    <section class="acoes">
        <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>
        
        <form method="GET">
            <input type="text" name="busca" placeholder="Buscar por código ou nome" value="<?= htmlspecialchars($busca) ?>">
            <button type="submit">Buscar</button>
        </form>
    </section>

    <?php if ($erro): ?>
        <div class="alerta erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alerta sucesso"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <div class="form-container" style="max-width: 100%; margin-bottom: 2rem;">
        <h2>📦 Cadastrar Nova Peça</h2>
        <form method="POST">
            <input type="hidden" name="acao" value="nova_peca">
            <div class="linha">
                <div class="campo">
                    <label>Código *</label>
                    <input type="text" name="codigo" required placeholder="EX: OLEO-5W30">
                </div>
                <div class="campo">
                    <label>Nome da Peça *</label>
                    <input type="text" name="nome" required placeholder="EX: Óleo 5W30 Sintético">
                </div>
                <div class="campo">
                    <label>Fabricante</label>
                    <input type="text" name="fabricante" placeholder="EX: Lubrax">
                </div>
            </div>
            <div class="linha">
                <div class="campo">
                    <label>Unidade</label>
                    <input type="text" name="unidade" value="UN" placeholder="UN, L, KG, Par">
                </div>
                <div class="campo">
                    <label>Quantidade Inicial</label>
                    <input type="number" name="quantidade" min="0" value="0">
                </div>
                <div class="campo">
                    <label>Estoque Mínimo</label>
                    <input type="number" name="estoque_minimo" min="0" value="5">
                </div>
            </div>
            <div class="linha">
                <div class="campo">
                    <label>Preço Custo (R$)</label>
                    <input type="text" name="preco_custo" placeholder="0.00">
                </div>
                <div class="campo">
                    <label>Preço Venda (R$)</label>
                    <input type="text" name="preco_venda" placeholder="0.00">
                </div>
                <div class="campo">
                    <label>Localização</label>
                    <input type="text" name="localizacao" placeholder="EX: Prateleira A01">
                </div>
            </div>
            <button type="submit" class="botao">+ Cadastrar Peça</button>
        </form>
    </div>

    <section class="tabela">
        <h2>Itens em Estoque</h2>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Fabricante</th>
                    <th>Qtd Atual</th>
                    <th>Mínimo</th>
                    <th>Preço Venda</th>
                    <th>Localização</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pecas): ?>
                    <tr>
                        <td colspan="8">Nenhuma peça encontrada.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pecas as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['codigo']) ?></strong></td>
                            <td><?= htmlspecialchars($p['nome']) ?></td>
                            <td><?= htmlspecialchars($p['fabricante'] ?? '-') ?></td>
                            <td>
                                <strong><?= $p['quantidade'] ?></strong> <?= htmlspecialchars($p['unidade']) ?>
                                <?php if ($p['quantidade'] <= $p['estoque_minimo']): ?>
                                    <span style="color: #e74c3c; font-weight: bold;" title="Estoque baixo!">⚠️ Baixo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $p['estoque_minimo'] ?></td>
                            <td>R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($p['localizacao'] ?? '-') ?></td>
                            <td>
                                <form method="POST" style="display: inline-flex; gap: 5px; align-items: center;">
                                    <input type="hidden" name="acao" value="movimentar">
                                    <input type="hidden" name="peca_id" value="<?= $p['id'] ?>">
                                    <select name="tipo" style="padding: 4px; border-radius: 4px;">
                                        <option value="ENTRADA">+ Entrada</option>
                                        <option value="SAIDA">- Saída</option>
                                    </select>
                                    <input type="number" name="quantidade" min="1" value="1" style="width: 50px; padding: 4px;">
                                    <button type="submit" class="botao" style="padding: 4px 8px; font-size: 0.8rem;">OK</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

</body>
</html>
