<?php
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
        <a href="estoque.php" class="<?= $currentPage == 'estoque.php' ? 'active' : '' ?>">📦 Estoque</a>
        <a href="funcionarios.php" class="<?= $currentPage == 'funcionarios.php' ? 'active' : '' ?>">👨‍🔧 Equipe</a>
    </nav>
</header>
