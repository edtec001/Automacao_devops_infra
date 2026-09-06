<?php

require_once 'config.php';
require_once 'auth.php';

$usuarioLogado = exigirAutenticacao();
exigirPermissao(['ESTOQUE', 'MECANICO']);

// Impedir alterações no cadastro de peças se o usuário não for ESTOQUE/ADMIN/GERENTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !temPermissao(['ESTOQUE'])) {
    die("Acesso Negado: Seu perfil não possui permissão para cadastrar ou modificar itens do estoque.");
}

$erro = '';
$sucesso = '';

$idPecaEditar = (int) ($_GET['editar'] ?? 0);
$pecaEditar = null;

if ($idPecaEditar > 0) {
    $stmtE = $pdo->prepare("SELECT * FROM pecas WHERE id = ?");
    $stmtE->execute([$idPecaEditar]);
    $pecaEditar = $stmtE->fetch();
}

// 1. Cadastrar / Editar peça
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_peca') {
    $id = (int) ($_POST['id'] ?? 0);
    $codigo = trim($_POST['codigo'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $categoria = trim($_POST['categoria'] ?? 'Geral');
    $fabricante = trim($_POST['fabricante'] ?? '');
    $fornecedorId = (int) ($_POST['fornecedor_id'] ?? 0);
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
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE pecas
                    SET codigo = ?, nome = ?, categoria = ?, fabricante = ?, fornecedor_id = ?, unidade = ?, estoque_minimo = ?, preco_custo = ?, preco_venda = ?, localizacao = ?
                    WHERE id = ?
                ");
                $stmt->execute([$codigo, $nome, $categoria, $fabricante, $fornecedorId ?: null, $unidade, $estoque_minimo, $preco_custo, $preco_venda, $localizacao, $id]);
                $sucesso = 'Dados da peça atualizados com sucesso!';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO pecas (codigo, nome, categoria, fabricante, fornecedor_id, unidade, quantidade, estoque_minimo, preco_custo, preco_venda, localizacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$codigo, $nome, $categoria, $fabricante, $fornecedorId ?: null, $unidade, $quantidade, $estoque_minimo, $preco_custo, $preco_venda, $localizacao]);
                $sucesso = 'Peça cadastrada no estoque com sucesso!';
            }
            $pecaEditar = null;
            $idPecaEditar = 0;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar peça: ' . $e->getMessage();
        }
    }
}

// 2. Atualizar quantidade no estoque (Movimentação)
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

// 3. Excluir Peça
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_peca') {
    $pecaIdDel = (int) ($_POST['peca_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM pecas WHERE id = ?");
        $stmt->execute([$pecaIdDel]);
        $sucesso = 'Peça removida do estoque.';
    } catch (Exception $e) {
        $erro = 'Erro ao excluir peça: Não é possível remover peças que já possuem movimentações ou uso em Ordens de Serviço.';
    }
}

// Pesquisa e Filtros
$busca = trim($_GET['busca'] ?? '');
$fornecedorFiltro = (int) ($_GET['fornecedor_id'] ?? 0);
$categoriaFiltro = trim($_GET['categoria'] ?? '');

$sql = "
    SELECT p.*, f.nome_fantasia AS fornecedor_nome
    FROM pecas p
    LEFT JOIN fornecedores_autopecas f ON f.id = p.fornecedor_id
    WHERE 1=1
";
$params = [];

if ($busca !== '') {
    $sql .= " AND (p.codigo LIKE ? OR p.nome LIKE ? OR p.fabricante LIKE ? OR p.categoria LIKE ?)";
    $term = '%' . $busca . '%';
    $params = [$term, $term, $term, $term];
}

if ($fornecedorFiltro > 0) {
    $sql .= " AND p.fornecedor_id = ?";
    $params[] = $fornecedorFiltro;
}

if ($categoriaFiltro !== '') {
    $sql .= " AND p.categoria = ?";
    $params[] = $categoriaFiltro;
}

$sql .= " ORDER BY p.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pecas = $stmt->fetchAll();

// Lista Fornecedores para Dropdown
$fornecedoresList = $pdo->query("SELECT id, nome_fantasia FROM fornecedores_autopecas WHERE ativo = 1 ORDER BY nome_fantasia ASC")->fetchAll();

// Categorias Existentes
$categoriasList = ['Geral', 'Motor', 'Freios', 'Suspensão', 'Elétrica', 'Filtros', 'Lubrificantes', 'Transmissão', 'Outros'];

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
$subtitulo = 'Controle de Estoque de Peças & Catálogo';
include 'header.php';
?>

<main>
    <section class="acoes">
        <div>
            <a href="autopecas.php" class="botao" style="background: #2563eb;">🏬 Gerenciar Lojas de Autopeças</a>
            <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>
        </div>
        
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="busca" placeholder="Buscar por código, nome ou fabricante" value="<?= htmlspecialchars($busca) ?>">
            <select name="fornecedor_id" onchange="this.form.submit()" style="padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <option value="">Todos os Fornecedores</option>
                <?php foreach ($fornecedoresList as $forn): ?>
                    <option value="<?= $forn['id'] ?>" <?= $fornecedorFiltro == $forn['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($forn['nome_fantasia']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Buscar</button>
        </form>
    </section>

    <?php if ($erro): ?>
        <div class="alerta erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alerta sucesso"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <!-- Form de Cadastro / Edição de Peça -->
    <div class="form-container" style="max-width: 100%; margin-bottom: 2rem;">
        <h2>📦 <?= $pecaEditar ? 'Editar Peça #' . htmlspecialchars($pecaEditar['codigo']) : 'Cadastrar Nova Peça no Estoque' ?></h2>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_peca">
            <input type="hidden" name="id" value="<?= $pecaEditar['id'] ?? 0 ?>">

            <div class="linha">
                <div class="campo">
                    <label>Código da Peça / OEM *</label>
                    <input type="text" name="codigo" required value="<?= htmlspecialchars($pecaEditar['codigo'] ?? '') ?>" placeholder="EX: OLEO-5W30, PAST-FREIO-01">
                </div>
                <div class="campo">
                    <label>Nome da Peça / Componente *</label>
                    <input type="text" name="nome" required value="<?= htmlspecialchars($pecaEditar['nome'] ?? '') ?>" placeholder="EX: Óleo 5W30 Sintético, Pastilha de Freio Dianteira">
                </div>
                <div class="campo">
                    <label>Categoria / Grupo</label>
                    <select name="categoria">
                        <?php 
                        $catSel = $pecaEditar['categoria'] ?? 'Geral';
                        foreach ($categoriasList as $c): 
                        ?>
                            <option value="<?= $c ?>" <?= $catSel === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label>Fabricante / Marca</label>
                    <input type="text" name="fabricante" value="<?= htmlspecialchars($pecaEditar['fabricante'] ?? '') ?>" placeholder="EX: Bosch, Tecfil, Lubrax, Fras-le">
                </div>
                <div class="campo">
                    <label>Loja de Autopeças (Fornecedor)</label>
                    <select name="fornecedor_id">
                        <option value="">-- Sem Fornecedor Especificado --</option>
                        <?php 
                        $fornSel = $pecaEditar['fornecedor_id'] ?? 0;
                        foreach ($fornecedoresList as $f): 
                        ?>
                            <option value="<?= $f['id'] ?>" <?= $fornSel == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nome_fantasia']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label>Unidade de Medida</label>
                    <input type="text" name="unidade" value="<?= htmlspecialchars($pecaEditar['unidade'] ?? 'UN') ?>" placeholder="UN, L, KG, Par, Jogo">
                </div>
            </div>

            <div class="linha">
                <?php if (!$pecaEditar): ?>
                    <div class="campo">
                        <label>Quantidade Inicial no Estoque</label>
                        <input type="number" name="quantidade" min="0" value="0">
                    </div>
                <?php endif; ?>
                <div class="campo">
                    <label>Estoque Mínimo (Alerta)</label>
                    <input type="number" name="estoque_minimo" min="0" value="<?= htmlspecialchars($pecaEditar['estoque_minimo'] ?? '5') ?>">
                </div>
                <div class="campo">
                    <label>Preço de Custo R$ (Aquisição)</label>
                    <input type="text" name="preco_custo" value="<?= number_format($pecaEditar['preco_custo'] ?? 0, 2, ',', '.') ?>" placeholder="0,00">
                </div>
                <div class="campo">
                    <label>Preço de Venda R$ (Oficina)</label>
                    <input type="text" name="preco_venda" value="<?= number_format($pecaEditar['preco_venda'] ?? 0, 2, ',', '.') ?>" placeholder="0,00">
                </div>
                <div class="campo">
                    <label>Localização / Prateleira</label>
                    <input type="text" name="localizacao" value="<?= htmlspecialchars($pecaEditar['localizacao'] ?? '') ?>" placeholder="EX: Prateleira A01, Gaveta B3">
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="botao" style="font-weight: bold;">
                    <?= $pecaEditar ? '💾 Salvar Alterações na Peça' : '+ Cadastrar Peça' ?>
                </button>
                <?php if ($pecaEditar): ?>
                    <a href="estoque.php" class="botao secundario">Cancelar Edição</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabela de Itens em Estoque -->
    <section class="tabela">
        <h2>Itens em Estoque</h2>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome da Peça</th>
                    <th>Categoria</th>
                    <th>Fabricante</th>
                    <th>Loja Autopeças</th>
                    <th>Estoque Atual</th>
                    <th>Preço Custo</th>
                    <th>Preço Venda</th>
                    <th>Local</th>
                    <th>Ações & Entrada/Saída</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pecas): ?>
                    <tr>
                        <td colspan="10" style="text-align: center;">Nenhuma peça encontrada.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pecas as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['codigo']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($p['nome']) ?></strong>
                            </td>
                            <td><span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;"><?= htmlspecialchars($p['categoria'] ?? 'Geral') ?></span></td>
                            <td><?= htmlspecialchars($p['fabricante'] ?? '-') ?></td>
                            <td>
                                <?php if ($p['fornecedor_nome']): ?>
                                    <span style="color: #2563eb; font-size: 0.85rem; font-weight: bold;">🏬 <?= htmlspecialchars($p['fornecedor_nome']) ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= $p['quantidade'] ?></strong> <?= htmlspecialchars($p['unidade']) ?>
                                <?php if ($p['quantidade'] <= $p['estoque_minimo']): ?>
                                    <span style="color: #e74c3c; font-weight: bold; display: block; font-size: 0.75rem;" title="Estoque crítico!">⚠️ Mínimo: <?= $p['estoque_minimo'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($p['preco_custo'], 2, ',', '.') ?></td>
                            <td><strong style="color: #16a34a;">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></strong></td>
                            <td><?= htmlspecialchars($p['localizacao'] ?? '-') ?></td>
                            <td>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <!-- Form Rápido Entrada/Saída -->
                                    <form method="POST" style="display: inline-flex; gap: 4px; align-items: center;">
                                        <input type="hidden" name="acao" value="movimentar">
                                        <input type="hidden" name="peca_id" value="<?= $p['id'] ?>">
                                        <select name="tipo" style="padding: 4px; border-radius: 4px; font-size: 0.8rem;">
                                            <option value="ENTRADA">+ Entrada</option>
                                            <option value="SAIDA">- Saída</option>
                                        </select>
                                        <input type="number" name="quantidade" min="1" value="1" style="width: 45px; padding: 4px; font-size: 0.8rem;">
                                        <button type="submit" class="botao" style="padding: 4px 8px; font-size: 0.8rem;">OK</button>
                                    </form>

                                    <a href="estoque.php?editar=<?= $p['id'] ?>" class="link" style="color: #d97706; font-size: 0.85rem;">✏️ Editar</a>

                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remover esta peça do estoque?');">
                                        <input type="hidden" name="acao" value="excluir_peca">
                                        <input type="hidden" name="peca_id" value="<?= $p['id'] ?>">
                                        <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.9rem;" title="Excluir peça">❌</button>
                                    </form>
                                </div>
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
