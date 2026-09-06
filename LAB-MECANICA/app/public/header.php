<?php
require_once 'auth.php';
$usuarioHeader = usuarioLogado();
$currentPage = basename($_SERVER['PHP_SELF']);
$subtituloPage = $subtitulo ?? 'Controle da oficina mecânica';
?>
<header>
    <div>
        <h1>🔧 EDTEC-SOLUTION</h1>
        <p><?= htmlspecialchars($subtituloPage) ?></p>
    </div>
    <nav class="nav-links">
        <a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">🏠 Início</a>
        <a href="entrada.php" class="<?= $currentPage == 'entrada.php' ? 'active' : '' ?>">🚗 Entrada</a>
        <a href="checklist.php" class="<?= $currentPage == 'checklist.php' ? 'active' : '' ?>">📋 Checklist</a>

        <?php if (temPermissao(['ESTOQUE', 'MECANICO'])): ?>
            <a href="estoque.php" class="<?= $currentPage == 'estoque.php' ? 'active' : '' ?>">📦 Estoque</a>
        <?php endif; ?>

        <?php if (temPermissao(['ESTOQUE'])): ?>
            <a href="autopecas.php" class="<?= $currentPage == 'autopecas.php' ? 'active' : '' ?>">🏬 Autopeças</a>
        <?php endif; ?>

        <?php if (temPermissao(['ATENDIMENTO'])): ?>
            <a href="notas.php" class="<?= $currentPage == 'notas.php' ? 'active' : '' ?>">📄 Notas Fiscais</a>
        <?php endif; ?>

        <?php if (temPermissao(['ADMIN', 'GERENTE'])): ?>
            <a href="financeiro.php" class="<?= $currentPage == 'financeiro.php' ? 'active' : '' ?>">💰 Financeiro</a>
            <a href="funcionarios.php" class="<?= $currentPage == 'funcionarios.php' ? 'active' : '' ?>">👨‍🔧 Equipe</a>
            <a href="usuarios.php" class="<?= $currentPage == 'usuarios.php' ? 'active' : '' ?>">👤 Usuários</a>
        <?php endif; ?>

        <?php if ($usuarioHeader): ?>
            <a href="trocar_senha.php" class="<?= $currentPage == 'trocar_senha.php' ? 'active' : '' ?>" title="Alterar Senha">🔑 Senha</a>
            <a href="logout.php" style="background: #dc2626; color: white;" title="Sair do sistema">🚪 Sair (<?= htmlspecialchars($usuarioHeader['usuario']) ?>)</a>
        <?php endif; ?>
    </nav>
</header>
