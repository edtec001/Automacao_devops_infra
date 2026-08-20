<?php

require_once 'config.php';


/*
|--------------------------------------------------------------------------
| Contagem dos status
|--------------------------------------------------------------------------
*/

function totalStatus(PDO $pdo, string $status): int
{
    $sql = "
        SELECT COUNT(*)
        FROM veiculos
        WHERE status = ?
        AND saida IS NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status]);

    return (int) $stmt->fetchColumn();
}


$entrada   = totalStatus($pdo, 'ENTRADA');
$orcamento = totalStatus($pdo, 'ORCAMENTO');
$servico   = totalStatus($pdo, 'SERVICO');
$liberado  = totalStatus($pdo, 'LIBERADO');


/*
|--------------------------------------------------------------------------
| Pesquisa por placa
|--------------------------------------------------------------------------
*/

$placaPesquisa = strtoupper(
    trim($_GET['placa'] ?? '')
);


if ($placaPesquisa !== '') {

    $stmt = $pdo->prepare("
        SELECT
            v.placa,
            v.marca,
            v.modelo,
            v.ano,
            v.cor,
            v.km_atual,
            v.entrada,
            v.status,
            v.posicao_chave,
            f.nome AS mecanico_nome
        FROM veiculos v
        LEFT JOIN funcionarios f ON f.id = v.mecanico_id
        WHERE v.saida IS NULL
        AND v.placa LIKE ?
        ORDER BY v.entrada DESC
    ");

    $stmt->execute([
        '%' . $placaPesquisa . '%'
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            v.placa,
            v.marca,
            v.modelo,
            v.ano,
            v.cor,
            v.km_atual,
            v.entrada,
            v.status,
            v.posicao_chave,
            f.nome AS mecanico_nome
        FROM veiculos v
        LEFT JOIN funcionarios f ON f.id = v.mecanico_id
        WHERE v.saida IS NULL
        ORDER BY v.entrada DESC
    ");

}


$veiculos = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Veículos por posição da chave
|--------------------------------------------------------------------------
*/

$posicoes = [];


$stmtPainel = $pdo->query("
    SELECT
        placa,
        status,
        posicao_chave,
        marca,
        modelo
    FROM veiculos
    WHERE saida IS NULL
    AND posicao_chave IS NOT NULL
");


while ($row = $stmtPainel->fetch()) {

    $posicoes[
        (int) $row['posicao_chave']
    ] = $row;

}


/*
|--------------------------------------------------------------------------
| Configuração visual dos LEDs
|--------------------------------------------------------------------------
*/

$leds = [

    'ENTRADA' => [
        'icone' => '🔴',
        'nome' => 'ENTRADA',
        'classe' => 'led-vermelho'
    ],

    'ORCAMENTO' => [
        'icone' => '🟡',
        'nome' => 'ORÇAMENTO',
        'classe' => 'led-amarelo'
    ],

    'SERVICO' => [
        'icone' => '🔵',
        'nome' => 'EM SERVIÇO',
        'classe' => 'led-azul'
    ],

    'LIBERADO' => [
        'icone' => '🟢',
        'nome' => 'LIBERADO',
        'classe' => 'led-verde'
    ]

];

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>EDTEC-SOLUTION</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body>

<?php
$subtitulo = 'Controle da oficina mecânica';
include 'header.php';
?>

<main>


<!-- =========================================================
     CARDS DE STATUS
========================================================= -->

<section class="cards">


    <div class="card vermelho">

        <span>🔴</span>

        <h2><?= $entrada ?></h2>

        <p>Entrada</p>

    </div>


    <div class="card amarelo">

        <span>🟡</span>

        <h2><?= $orcamento ?></h2>

        <p>Aguardando orçamento</p>

    </div>


    <div class="card azul">

        <span>🔵</span>

        <h2><?= $servico ?></h2>

        <p>Em serviço</p>

    </div>


    <div class="card verde">
        <span>🟢</span>

        <h2><?= $liberado ?></h2>

        <p>Liberados</p>

    </div>


</section>



<!-- =========================================================
     PAINEL DE CHAVES
========================================================= -->

<section class="painel-chaves">

    <div class="painel-titulo">

        <h2>🔑 Painel de Chaves</h2>

        <p>
            Controle das posições dos veículos na oficina
        </p>

    </div>


    <div class="grade-chaves">


<?php for ($i = 1; $i <= 10; $i++): ?>


<?php

    $veiculoPainel =
        $posicoes[$i] ?? null;

?>


        <div class="posicao-chave">


            <div class="numero-posicao">

                POSIÇÃO <?= sprintf('%02d', $i) ?>

            </div>


<?php if ($veiculoPainel): ?>


<?php

    $status =
        $veiculoPainel['status'];

    $led =
        $leds[$status]
        ?? [
            'icone' => '⚪',
            'nome' => $status,
            'classe' => ''
        ];

?>


            <div class="led-painel <?= $led['classe'] ?>">

                <?= $led['icone'] ?>

            </div>


            <div class="placa-painel">

                <?= htmlspecialchars(
                    $veiculoPainel['placa']
                ) ?>

            </div>


            <div class="modelo-painel">

                <?= htmlspecialchars(
                    $veiculoPainel['marca'] .
                    ' ' .
                    $veiculoPainel['modelo']
                ) ?>

            </div>


            <div class="status-painel">

                <?= $led['nome'] ?>

            </div>


            <a
                href="status.php?placa=<?= urlencode(
                    $veiculoPainel['placa']
                ) ?>"
                class="gerenciar-painel"
            >

                Gerenciar

            </a>


<?php else: ?>


            <div class="led-painel led-desligado">

                ⚪

            </div>


            <div class="placa-painel vaga">

                VAGA

            </div>


            <div class="modelo-painel">

                --

            </div>


            <div class="status-painel">

                DISPONÍVEL

            </div>


<?php endif; ?>


        </div>


<?php endfor; ?>


    </div>

</section>



<!-- =========================================================
     AÇÕES
========================================================= -->

<section class="acoes">


    <a
        href="entrada.php"
        class="botao"
    >

        + Entrada de veículo

    </a>


    <a
        href="estoque.php"
        class="botao secundario"
    >

        📦 Controle de estoque

    </a>


    <a
        href="funcionarios.php"
        class="botao secundario"
    >

        👨‍🔧 Equipe

    </a>


    <form method="GET">

        <input
            type="text"
            name="placa"
            placeholder="Pesquisar placa"
            maxlength="10"
            value="<?= htmlspecialchars(
                $placaPesquisa
            ) ?>"
        >

        <button type="submit">

            Pesquisar

        </button>

    </form>


</section>



<!-- =========================================================
     LISTA DE VEÍCULOS
========================================================= -->

<section class="tabela">


    <h2>Veículos na oficina</h2>


    <table>


        <thead>

            <tr>

                <th>Placa</th>

                <th>Veículo</th>

                <th>Ano</th>

                <th>Posição</th>

                <th>Mecânico</th>

                <th>Entrada</th>

                <th>Status</th>

                <th>Ação</th>

            </tr>

        </thead>


        <tbody>


<?php if (!$veiculos): ?>


            <tr>

                <td colspan="8">

                    Nenhum veículo encontrado.

                </td>

            </tr>


<?php else: ?>


<?php foreach ($veiculos as $veiculo): ?>


            <tr>


                <td>

                    <strong>

                        <?= htmlspecialchars(
                            $veiculo['placa']
                        ) ?>

                    </strong>

                </td>


                <td>

                    <?= htmlspecialchars(
                        $veiculo['marca'] .
                        ' ' .
                        $veiculo['modelo']
                    ) ?>

                </td>


                <td>

                    <?= htmlspecialchars(
                        $veiculo['ano'] ?? '-'
                    ) ?>

                </td>


                <td>

<?php if ($veiculo['posicao_chave']): ?>

                    🔑

                    <?= sprintf(
                        '%02d',
                        $veiculo['posicao_chave']
                    ) ?>

<?php else: ?>

                    --

<?php endif; ?>

                </td>


                <td>

                    <?= htmlspecialchars(
                        $veiculo['mecanico_nome'] ?? '-'
                    ) ?>

                </td>


                <td>

                    <?= date(
                        'd/m/Y H:i',
                        strtotime(
                            $veiculo['entrada']
                        )
                    ) ?>

                </td>


                <td>

                    <span
                        class="status status-<?= strtolower(
                            $veiculo['status']
                        ) ?>"
                    >

                        <?= htmlspecialchars(
                            $veiculo['status']
                        ) ?>

                    </span>

                </td>


                <td>

                    <a
                        class="link"
                        href="status.php?placa=<?= urlencode(
                            $veiculo['placa']
                        ) ?>"
                    >

                        Gerenciar

                    </a>

                    |

                    <a
                        class="link"
                        href="os.php?placa=<?= urlencode(
                            $veiculo['placa']
                        ) ?>"
                    >

                        📄 O.S.

                    </a>

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