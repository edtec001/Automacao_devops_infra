<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Retorna os dados do usuário logado na sessão ou null se não estiver logado.
 */
function usuarioLogado(): ?array
{
    if (isset($_SESSION['usuario_id'])) {
        return [
            'id' => (int) $_SESSION['usuario_id'],
            'nome' => $_SESSION['usuario_nome'] ?? '',
            'usuario' => $_SESSION['usuario_login'] ?? '',
            'perfil' => $_SESSION['usuario_perfil'] ?? 'ATENDIMENTO',
            'trocar_senha' => (int) ($_SESSION['trocar_senha'] ?? 0)
        ];
    }
    return null;
}

/**
 * Exige que o usuário esteja autenticado. Se não estiver, redireciona para login.php.
 * Se precisar trocar a senha obrigatoriamente, redireciona para trocar_senha.php.
 */
function exigirAutenticacao(): array
{
    $usuario = usuarioLogado();
    $paginaAtual = basename($_SERVER['PHP_SELF']);

    if (!$usuario) {
        header('Location: login.php');
        exit;
    }

    if ((int) $usuario['trocar_senha'] === 1 && $paginaAtual !== 'trocar_senha.php' && $paginaAtual !== 'logout.php') {
        header('Location: trocar_senha.php?obrigatorio=1');
        exit;
    }

    return $usuario;
}

/**
 * Verifica se o usuário logado possui um dos perfis permitidos.
 */
function temPermissao(array $perfilsPermitidos): bool
{
    $usuario = usuarioLogado();
    if (!$usuario) {
        return false;
    }
    // ADMIN e GERENTE possuem acesso total a qualquer recurso do sistema
    if (in_array($usuario['perfil'], ['ADMIN', 'GERENTE'], true)) {
        return true;
    }
    return in_array($usuario['perfil'], $perfilsPermitidos, true);
}

/**
 * Exige que o usuário logado possua um dos perfis permitidos.
 * Se não possuir, renderiza uma mensagem de "Acesso Negado (403)" e encerra a execução.
 */
function exigirPermissao(array $perfilsPermitidos): void
{
    $usuario = exigirAutenticacao();

    if (!temPermissao($perfilsPermitidos)) {
        http_response_code(403);
        $subtitulo = 'Acesso Restrito';
        include 'header.php';
        ?>
        <main style="max-width: 600px; margin: 60px auto; text-align: center;">
            <div style="background: white; padding: 40px 30px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-top: 5px solid #ef4444;">
                <h1 style="font-size: 3rem; margin: 0 0 10px 0; color: #ef4444;">🚫 Acesso Restrito (403)</h1>
                <h2 style="color: #1e293b; font-size: 1.4rem; margin-bottom: 15px;">Permissão Insuficiente</h2>
                <p style="color: #64748b; font-size: 1rem; line-height: 1.5; margin-bottom: 25px;">
                    Desculpe, <strong><?= htmlspecialchars($usuario['nome']) ?></strong>. Seu perfil (<code><?= htmlspecialchars($usuario['perfil']) ?></code>) não possui permissão para acessar este módulo.
                </p>
                <a href="index.php" class="botao" style="display: inline-block; padding: 12px 24px; text-decoration: none; font-weight: bold; background: #2563eb; color: white; border-radius: 8px;">
                    🏠 Voltar ao Dashboard
                </a>
            </div>
        </main>
        </body>
        </html>
        <?php
        exit;
    }
}
