<?php

require_once 'config.php';
require_once 'auth.php';

$usuarioLogado = exigirAutenticacao();
exigirPermissao(['ESTOQUE']);

$erroMsg = '';
$sucessoMsg = '';

$idEditar = (int) ($_GET['editar'] ?? 0);
$fornecedorEditar = null;

if ($idEditar > 0) {
    $stmtE = $pdo->prepare("SELECT * FROM fornecedores_autopecas WHERE id = ?");
    $stmtE->execute([$idEditar]);
    $fornecedorEditar = $stmtE->fetch();
}

// 1. Cadastrar / Editar Fornecedor de Autopeças
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_fornecedor') {
    $id = (int) ($_POST['id'] ?? 0);
    $nomeFantasia = trim($_POST['nome_fantasia'] ?? '');
    $razaoSocial = trim($_POST['razao_social'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $vendedorContato = trim($_POST['vendedor_contato'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cidadeEstado = trim($_POST['cidade_estado'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($nomeFantasia === '') {
        $erroMsg = 'O Nome Fantasia da loja/fornecedor de autopeças é obrigatório.';
    } else {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE fornecedores_autopecas
                    SET nome_fantasia = ?, razao_social = ?, cnpj = ?, telefone = ?, vendedor_contato = ?, email = ?, cidade_estado = ?, observacoes = ?, ativo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nomeFantasia, $razaoSocial, $cnpj, $telefone, $vendedorContato, $email, $cidadeEstado, $observacoes, $ativo, $id]);
                $sucessoMsg = 'Cadastro da loja de autopeças atualizado com sucesso!';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fornecedores_autopecas (nome_fantasia, razao_social, cnpj, telefone, vendedor_contato, email, cidade_estado, observacoes, ativo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nomeFantasia, $razaoSocial, $cnpj, $telefone, $vendedorContato, $email, $cidadeEstado, $observacoes, $ativo]);
                $sucessoMsg = 'Loja de autopeças cadastrada com sucesso!';
            }
            $fornecedorEditar = null;
            $idEditar = 0;
        } catch (Exception $e) {
            $erroMsg = 'Erro ao salvar loja de autopeças: ' . $e->getMessage();
        }
    }
}

// 2. Alternar Status Ativo/Inativo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'toggle_status') {
    $idF = (int) ($_POST['fornecedor_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE fornecedores_autopecas SET ativo = IF(ativo = 1, 0, 1) WHERE id = ?");
        $stmt->execute([$idF]);
        $sucessoMsg = 'Status do fornecedor alterado!';
    } catch (Exception $e) {
        $erroMsg = 'Erro ao alterar status: ' . $e->getMessage();
    }
}

// Busca e Filtros
$busca = trim($_GET['busca'] ?? '');
$fornecedorFiltro = (int) ($_GET['fornecedor_id'] ?? 0);

$sqlF = "
    SELECT f.*, COUNT(p.id) AS total_pecas
    FROM fornecedores_autopecas f
    LEFT JOIN pecas p ON p.fornecedor_id = f.id
    WHERE 1=1
";
$paramsF = [];

if ($busca !== '') {
    $sqlF .= " AND (f.nome_fantasia LIKE ? OR f.razao_social LIKE ? OR f.vendedor_contato LIKE ? OR f.cnpj LIKE ?)";
    $term = '%' . $busca . '%';
    $paramsF = [$term, $term, $term, $term];
}

$sqlF .= " GROUP BY f.id ORDER BY f.nome_fantasia ASC";
$stmtF = $pdo->prepare($sqlF);
$stmtF->execute($paramsF);
$fornecedores = $stmtF->fetchAll();

// Métricas Rápidas
$totalLojas = count($fornecedores);
$totalAtivas = (int) $pdo->query("SELECT COUNT(*) FROM fornecedores_autopecas WHERE ativo = 1")->fetchColumn();
$totalPecasCad = (int) $pdo->query("SELECT COUNT(*) FROM pecas WHERE fornecedor_id IS NOT NULL")->fetchColumn();

// Busca Peças do Fornecedor selecionado
$pecasFornecedor = [];
if ($fornecedorFiltro > 0) {
    $stmtP = $pdo->prepare("
        SELECT p.*, f.nome_fantasia AS fornecedor_nome
        FROM pecas p
        JOIN fornecedores_autopecas f ON f.id = p.fornecedor_id
        WHERE p.fornecedor_id = ?
        ORDER BY p.nome ASC
    ");
    $stmtP->execute([$fornecedorFiltro]);
    $pecasFornecedor = $stmtP->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Autopeças & Fornecedores - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .grid-fornecedores {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card-fornecedor {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,.08);
            border-top: 4px solid #2563eb;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .whatsapp-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #25d366;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<?php
$subtitulo = 'Cadastro e Gestão de Lojas de Autopeças & Fornecedores';
include 'header.php';
?>

<main>
    <section class="acoes">
        <a href="estoque.php" class="botao">📦 Ir ao Estoque de Peças</a>
        <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>

        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="busca" placeholder="Buscar loja, CNPJ ou vendedor" value="<?= htmlspecialchars($busca) ?>">
            <button type="submit">Pesquisar</button>
        </form>
    </section>

    <?php if ($erroMsg): ?>
        <div class="alerta erro"><?= htmlspecialchars($erroMsg) ?></div>
    <?php endif; ?>

    <?php if ($sucessoMsg): ?>
        <div class="alerta sucesso"><?= htmlspecialchars($sucessoMsg) ?></div>
    <?php endif; ?>

    <!-- CARDS DE METRICAS -->
    <div class="cards" style="margin-bottom: 25px;">
        <div class="card azul">
            <span>🏬</span>
            <h2><?= $totalLojas ?></h2>
            <p>Lojas de Autopeças</p>
        </div>
        <div class="card verde">
            <span>✅</span>
            <h2><?= $totalAtivas ?></h2>
            <p>Fornecedores Ativos</p>
        </div>
        <div class="card amarelo">
            <span>📦</span>
            <h2><?= $totalPecasCad ?></h2>
            <p>Peças com Fornecedor</p>
        </div>
    </div>

    <!-- FORMULÁRIO DE CADASTRO DE LOJA DE AUTOPEÇAS -->
    <div class="form-container" style="max-width: 100%; margin-bottom: 30px;">
        <h2>🏬 <?= $fornecedorEditar ? 'Editar Loja de Autopeças #' . $fornecedorEditar['id'] : 'Cadastrar Nova Loja / Fornecedor de Autopeças' ?></h2>
        
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_fornecedor">
            <input type="hidden" name="id" value="<?= $fornecedorEditar['id'] ?? 0 ?>">

            <div class="linha">
                <div class="campo">
                    <label>Nome Fantasia (Loja) *</label>
                    <input type="text" name="nome_fantasia" required value="<?= htmlspecialchars($fornecedorEditar['nome_fantasia'] ?? '') ?>" placeholder="Ex: Autopeças Central Distribuidora">
                </div>
                <div class="campo">
                    <label>Razão Social</label>
                    <input type="text" name="razao_social" value="<?= htmlspecialchars($fornecedorEditar['razao_social'] ?? '') ?>" placeholder="Ex: Autopeças Central Ltda ME">
                </div>
                <div class="campo">
                    <label>CNPJ</label>
                    <input type="text" name="cnpj" value="<?= htmlspecialchars($fornecedorEditar['cnpj'] ?? '') ?>" placeholder="00.000.000/0001-00">
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label>Vendedor / Pessoa de Contato</label>
                    <input type="text" name="vendedor_contato" value="<?= htmlspecialchars($fornecedorEditar['vendedor_contato'] ?? '') ?>" placeholder="Ex: Marcos Oliveira (Atendimento Oficina)">
                </div>
                <div class="campo">
                    <label>Telefone / WhatsApp</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($fornecedorEditar['telefone'] ?? '') ?>" placeholder="(85) 99999-8888">
                </div>
                <div class="campo">
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($fornecedorEditar['email'] ?? '') ?>" placeholder="vendas@autopecas.com.br">
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label>Cidade / UF</label>
                    <input type="text" name="cidade_estado" value="<?= htmlspecialchars($fornecedorEditar['cidade_estado'] ?? 'Fortaleza/CE') ?>" placeholder="Fortaleza / CE">
                </div>
                <div class="campo">
                    <label>Observações / Condições de Pagamento</label>
                    <input type="text" name="observacoes" value="<?= htmlspecialchars($fornecedorEditar['observacoes'] ?? '') ?>" placeholder="Ex: Entrega via motoboy em 30 min. Faturamento em 28 dias.">
                </div>
                <div class="campo" style="justify-content: center;">
                    <label class="checklist-item" style="margin-top: 25px;">
                        <input type="checkbox" name="ativo" value="1" <?= (!isset($fornecedorEditar) || $fornecedorEditar['ativo'] == 1) ? 'checked' : '' ?>>
                        <span>Loja Ativa para Compras</span>
                    </label>
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button type="submit" class="botao" style="font-weight: bold;">
                    <?= $fornecedorEditar ? '💾 Salvar Alterações' : '+ Cadastrar Fornecedor de Autopeças' ?>
                </button>
                <?php if ($fornecedorEditar): ?>
                    <a href="autopecas.php" class="botao secundario">Cancelar Edição</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- LISTA DE LOJAS DE AUTOPEÇAS CADASTRADAS -->
    <h2 style="margin-bottom: 15px;">🏬 Lojas de Autopeças Cadastradas</h2>
    
    <?php if (!$fornecedores): ?>
        <div class="alerta erro">Nenhuma loja de autopeças cadastrada.</div>
    <?php else: ?>
        <div class="grid-fornecedores">
            <?php foreach ($fornecedores as $f): 
                $numWhatsApp = preg_replace('/[^0-9]/', '', $f['telefone'] ?? '');
            ?>
                <div class="card-fornecedor" style="<?= $f['ativo'] ? '' : 'opacity: 0.6; border-top-color: #64748b;' ?>">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <h3 style="margin: 0; color: #0f172a; font-size: 1.2rem;"><?= htmlspecialchars($f['nome_fantasia']) ?></h3>
                            <?php if ($f['ativo']): ?>
                                <span class="badge-status badge-conforme">ATIVO</span>
                            <?php else: ?>
                                <span class="badge-status badge-nao-aplicavel">INATIVO</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($f['razao_social']): ?>
                            <p style="margin: 3px 0; color: #64748b; font-size: 0.85rem;"><?= htmlspecialchars($f['razao_social']) ?></p>
                        <?php endif; ?>

                        <div style="margin: 12px 0; font-size: 0.9rem; line-height: 1.6;">
                            <p style="margin: 2px 0;"><strong>👤 Vendedor:</strong> <?= htmlspecialchars($f['vendedor_contato'] ?: 'Não informado') ?></p>
                            <p style="margin: 2px 0;"><strong>📞 Telefone:</strong> <?= htmlspecialchars($f['telefone'] ?: '-') ?></p>
                            <p style="margin: 2px 0;"><strong>🏛️ CNPJ:</strong> <?= htmlspecialchars($f['cnpj'] ?: '-') ?></p>
                            <p style="margin: 2px 0;"><strong>📍 Cidade:</strong> <?= htmlspecialchars($f['cidade_estado'] ?: '-') ?></p>
                            <p style="margin: 2px 0;"><strong>📦 Peças em Catálogo:</strong> <strong style="color: #2563eb;"><?= $f['total_pecas'] ?> peça(s)</strong></p>
                            <?php if ($f['observacoes']): ?>
                                <p style="margin: 6px 0 0 0; background: #f8fafc; padding: 6px; border-radius: 4px; font-size: 0.85rem; color: #475569;">💬 <?= htmlspecialchars($f['observacoes']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="border-top: 1px solid #e2e8f0; padding-top: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <?php if ($numWhatsApp): ?>
                                <a href="https://wa.me/55<?= $numWhatsApp ?>" target="_blank" class="whatsapp-link">
                                    💬 WhatsApp Vendedor
                                </a>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 8px; align-items: center;">
                            <a href="autopecas.php?fornecedor_id=<?= $f['id'] ?>" class="link" style="font-weight: bold;">👁️ Ver Peças</a> |
                            <a href="autopecas.php?editar=<?= $f['id'] ?>" class="link" style="color: #d97706;">✏️ Editar</a> |
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="acao" value="toggle_status">
                                <input type="hidden" name="fornecedor_id" value="<?= $f['id'] ?>">
                                <button type="submit" style="background: none; border: none; color: #64748b; cursor: pointer; text-decoration: underline; font-size: 0.85rem;">
                                    <?= $f['ativo'] ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- TABELA DE CATÁLOGO DE PEÇAS DA LOJA SELECIONADA -->
    <?php if ($fornecedorFiltro > 0): ?>
        <section class="tabela" style="margin-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2>📦 Catálogo de Peças Fornecidas pela Loja Selecionada</h2>
                <a href="autopecas.php" class="link">Limpar Filtro</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Peça / Componente</th>
                        <th>Categoria</th>
                        <th>Fabricante</th>
                        <th>Qtd Estoque</th>
                        <th>Preço Custo</th>
                        <th>Preço Venda</th>
                        <th>Margem Bruta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pecasFornecedor): ?>
                        <tr><td colspan="8" style="text-align: center;">Nenhuma peça vinculada a esta loja de autopeças.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pecasFornecedor as $pf): 
                            $lucroUnit = $pf['preco_venda'] - $pf['preco_custo'];
                            $margemPct = ($pf['preco_venda'] > 0) ? (($lucroUnit / $pf['preco_venda']) * 100) : 0;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($pf['codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($pf['nome']) ?></td>
                                <td><span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;"><?= htmlspecialchars($pf['categoria'] ?? 'Geral') ?></span></td>
                                <td><?= htmlspecialchars($pf['fabricante'] ?? '-') ?></td>
                                <td><strong><?= $pf['quantidade'] ?></strong> <?= htmlspecialchars($pf['unidade']) ?></td>
                                <td>R$ <?= number_format($pf['preco_custo'], 2, ',', '.') ?></td>
                                <td><strong style="color: #16a34a;">R$ <?= number_format($pf['preco_venda'], 2, ',', '.') ?></strong></td>
                                <td><span class="badge-status badge-conforme">+<?= number_format($margemPct, 1) ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

</main>

</body>
</html>
