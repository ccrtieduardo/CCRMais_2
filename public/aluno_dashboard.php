<?php
session_start();
require_once(__DIR__ . '/../config/database.php');

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "aluno") {
    header("Location: login.php");
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Buscar saldo do aluno
    $sql = "SELECT saldo_moedas FROM alunos WHERE usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION["usuario_id"]]);
    $saldo = $stmt->fetchColumn();
    
    // Buscar transações com paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT SQL_CALC_FOUND_ROWS t.quantidade, t.tipo, t.motivo, u.nome AS professor_nome, t.data_hora 
            FROM transacoes t
            JOIN usuarios u ON t.professor_id = u.id
            WHERE t.aluno_id = ?
            ORDER BY t.data_hora DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION["usuario_id"], $limit, $offset]);
    $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total de registros para paginação
    $total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $totalPages = ceil($total / $limit);
    
} catch (PDOException $e) {
    error_log("Erro no dashboard do aluno: " . $e->getMessage());
    $saldo = 0;
    $transacoes = [];
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard do Aluno</title>
    <link rel="stylesheet" href="../aluno_dashboard.css">
</head>
<body>
    <div class="container">
        <h1>Olá, <?= htmlspecialchars($_SESSION["usuario_nome"]) ?>!</h1>
        <p>Saldo de Moedas: <strong><?= $saldo ?></strong></p>
        <div class="conteiner-coins">
            <div class="coin"><img src="./coin.png" alt="" width="80"></div>
            <div class="coin"><img src="./coin.png" alt="" width="80"></div>
            <div class="coin"><img src="./coin.png" alt="" width="80"></div>
        </div>
        <a href="logout.php" class="btn">Sair</a>

        <h2>Histórico de Transações</h2>
        <?php if (empty($transacoes)): ?>
            <p>Nenhuma transação encontrada.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Quantidade</th>
                    <th>Tipo</th>
                    <th>Motivo</th>
                    <th>Professor</th>
                    <th>Data/Hora</th>
                </tr>
                <?php foreach ($transacoes as $t): ?>
                <tr>
                    <td><?= $t['quantidade'] ?></td>
                    <td><?= $t['tipo'] == 'adicao' ? 'Adição' : 'Remoção' ?></td>
                    <td><?= ucfirst($t['motivo']) ?></td>
                    <td><?= htmlspecialchars($t['professor_nome']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($t['data_hora'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <!-- Paginação -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>">Anterior</a>
                <?php endif; ?>
                
                <span>Página <?= $page ?> de <?= $totalPages ?></span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>">Próxima</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>