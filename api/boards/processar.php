<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();

try {
    $pdo->beginTransaction();

    // Processar filas de boards
    foreach ($boards_config as $nivel => $config) {
        // Buscar fila ativa do nível
        $stmt = $pdo->prepare("
            SELECT bf.*, u.total_indicacoes 
            FROM board_filas bf
            JOIN usuarios u ON u.id = bf.usuario_id
            WHERE bf.nivel = ? AND bf.status = 'ativo'
            ORDER BY bf.data_entrada ASC
        ");
        $stmt->execute([$nivel]);
        $fila = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($fila) >= 5) {
            $ganhador = $fila[0];
            
            // Verificar se precisa de validação de indicações
            $indicacoes_necessarias = $config['indicacoes_necessarias'] ?? 0;
            
            if ($indicacoes_necessarias > 0 && $ganhador['total_indicacoes'] < $indicacoes_necessarias) {
                // Mover para standby
                $stmt = $pdo->prepare("UPDATE board_filas SET status = 'standby' WHERE id = ?");
                $stmt->execute([$ganhador['id']]);
                continue;
            }

            // Processar ganho
            $premio = $config['premio'];
            
            // Atualizar saldo do ganhador
            $stmt = $pdo->prepare("UPDATE usuarios SET saldo_sfcoin = saldo_sfcoin + ? WHERE id = ?");
            $stmt->execute([$premio, $ganhador['usuario_id']]);

            // Registrar no histórico
            $stmt = $pdo->prepare("
                INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
                VALUES (?, 'board', ?, ?, NOW())
            ");
            $stmt->execute([$ganhador['usuario_id'], $premio, $nivel]);

            // Remover da fila atual
            $stmt = $pdo->prepare("DELETE FROM board_filas WHERE id = ?");
            $stmt->execute([$ganhador['id']]);

            // Avançar para próximo nível (se não for o último)
            if ($nivel < 15) {
                $stmt = $pdo->prepare("
                    INSERT INTO board_filas (usuario_id, nivel, data_entrada, status) 
                    VALUES (?, ?, NOW(), 'ativo')
                ");
                $stmt->execute([$ganhador['usuario_id'], $nivel + 1]);

                // Atualizar board atual do usuário
                $stmt = $pdo->prepare("UPDATE usuarios SET board_atual = ? WHERE id = ?");
                $stmt->execute([$nivel + 1, $ganhador['usuario_id']]);
            } else {
                // Nível 15 - reiniciar ciclo
                $stmt = $pdo->prepare("UPDATE usuarios SET saldo_sfcoin = saldo_sfcoin - 10000 WHERE id = ?");
                $stmt->execute([$ganhador['usuario_id']]);

                $stmt = $pdo->prepare("
                    INSERT INTO board_filas (usuario_id, nivel, data_entrada, status) 
                    VALUES (?, 0, NOW(), 'ativo')
                ");
                $stmt->execute([$ganhador['usuario_id']]);

                // Registrar desconto no histórico
                $stmt = $pdo->prepare("
                    INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
                    VALUES (?, 'desconto_ciclo', -10000, 0, NOW())
                ");
                $stmt->execute([$ganhador['usuario_id']]);
            }

            // Verificar desbloqueio do SFG Prime no nível 7
            if ($nivel == 7) {
                $stmt = $pdo->prepare("UPDATE usuarios SET sfg_prime_nivel = 1, saldo_sfcoin = saldo_sfcoin - 10000 WHERE id = ?");
                $stmt->execute([$ganhador['usuario_id']]);

                $stmt = $pdo->prepare("
                    INSERT INTO sfg_prime_filas (usuario_id, nivel, data_entrada, status) 
                    VALUES (?, 1, NOW(), 'ativo')
                ");
                $stmt->execute([$ganhador['usuario_id']]);

                // Registrar desconto SFG Prime
                $stmt = $pdo->prepare("
                    INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
                    VALUES (?, 'sfg_prime_entrada', -10000, 1, NOW())
                ");
                $stmt->execute([$ganhador['usuario_id']]);
            }
        }
    }

    // Processar SFG Prime
    foreach ($sfg_prime_config as $nivel => $config) {
        if ($nivel == 1) continue; // Nível 1 é entrada automática

        $stmt = $pdo->prepare("
            SELECT * FROM sfg_prime_filas 
            WHERE nivel = ? AND status = 'ativo'
            ORDER BY data_entrada ASC
        ");
        $stmt->execute([$nivel]);
        $fila = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($fila) >= 5) {
            $ganhador = $fila[0];
            $premio = $config['premio'];

            // Atualizar saldo
            $stmt = $pdo->prepare("UPDATE usuarios SET saldo_sfcoin = saldo_sfcoin + ? WHERE id = ?");
            $stmt->execute([$premio, $ganhador['usuario_id']]);

            // Registrar no histórico
            $stmt = $pdo->prepare("
                INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
                VALUES (?, 'sfg_prime', ?, ?, NOW())
            ");
            $stmt->execute([$ganhador['usuario_id'], $premio, $nivel]);

            // Remover da fila atual
            $stmt = $pdo->prepare("DELETE FROM sfg_prime_filas WHERE id = ?");
            $stmt->execute([$ganhador['id']]);

            // Avançar para próximo nível (se não for o último)
            if ($nivel < 8) {
                $stmt = $pdo->prepare("
                    INSERT INTO sfg_prime_filas (usuario_id, nivel, data_entrada, status) 
                    VALUES (?, ?, NOW(), 'ativo')
                ");
                $stmt->execute([$ganhador['usuario_id'], $nivel + 1]);

                $stmt = $pdo->prepare("UPDATE usuarios SET sfg_prime_nivel = ? WHERE id = ?");
                $stmt->execute([$nivel + 1, $ganhador['usuario_id']]);
            }
        }
    }

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => 'Boards processados com sucesso']);

} catch (Exception $e) {
    $pdo->rollback();
    jsonResponse(['error' => 'Erro ao processar boards: ' . $e->getMessage()], 500);
}
?>
