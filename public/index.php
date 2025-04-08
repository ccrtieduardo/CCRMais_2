<?php
require_once(__DIR__ . '/../config/database.php');

// Configurações do cache
$cache_dir = __DIR__ . '/../cache/';
$cache_time = 60; // 1 minuto em segundos

try {
    $pdo = getDBConnection();
    
    // Nome do arquivo de cache (usando hash para segurança)
    $cache_key = 'ranking_top10_' . md5(date('Y-m-d_H-i'));
    $cache_file = $cache_dir . $cache_key . '.cache';
    
    // Verifica se o cache existe e é válido
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        $ranking = unserialize(file_get_contents($cache_file));
    } else {
        $sql = "SELECT u.nome, COALESCE(a.saldo_moedas, 0) AS total_moedas
                FROM usuarios u
                LEFT JOIN alunos a ON u.id = a.usuario_id
                WHERE u.tipo = 'aluno'
                ORDER BY total_moedas DESC
                LIMIT 10";
        
        $stmt = $pdo->query($sql);
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cria o diretório de cache se não existir
        if (!file_exists($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        // Salva no cache
        file_put_contents($cache_file, serialize($ranking));
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar ranking: " . $e->getMessage());
    $ranking = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCRMais - Início</title>
    <link rel="stylesheet" type="text/css" href="../index.css">
    <link rel="icon" href="./ccr+.ico" sizes="any">
</head>
<body>
    <div class="container">
        <h1>BEM-VINDO AO CCR+</h1>
        <a href="login.php" class="btn">Acessar</a>

        <h2>Ranking de Alunos</h2>
        <?php if (empty($ranking)): ?>
            <p>Nenhum aluno encontrado ou sem pontuação ainda.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Posição</th>
                    <th>Nome</th>
                    <th>Moedas</th>
                </tr>
                <?php 
                $posicao = 1;
                foreach ($ranking as $aluno): ?>
                <tr>
                    <td><?= $posicao++; ?></td>
                    <td><?= htmlspecialchars($aluno["nome"]); ?></td>
                    <td><?= $aluno["total_moedas"]; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>