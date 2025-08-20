-- Script para Garantir Uma Subscrição Ativa por Cliente
-- Execute este script para corrigir dados existentes e prevenir duplicatas futuras

-- 1. LIMPEZA: Identificar e corrigir clientes com múltiplas subscrições ativas
-- Encontrar clientes com mais de uma subscrição ativa
SELECT 
    cliente_id,
    COUNT(*) as subscricoes_ativas,
    GROUP_CONCAT(id) as ids_subscricoes
FROM subscricoes 
WHERE ativa = 1 AND data_fim >= CURDATE()
GROUP BY cliente_id 
HAVING COUNT(*) > 1;

-- 2. CORREÇÃO AUTOMÁTICA: Manter apenas a subscrição mais recente para cada cliente
-- Desativar subscrições duplicadas (manter apenas a mais recente)
UPDATE subscricoes s1
SET ativa = 0
WHERE s1.ativa = 1 
AND s1.data_fim >= CURDATE()
AND EXISTS (
    SELECT 1 FROM (
        SELECT cliente_id, MAX(id) as max_id
        FROM subscricoes 
        WHERE ativa = 1 AND data_fim >= CURDATE()
        GROUP BY cliente_id
        HAVING COUNT(*) > 1
    ) s2 
    WHERE s1.cliente_id = s2.cliente_id 
    AND s1.id < s2.max_id
);

-- 3. VERIFICAÇÃO: Confirmar que cada cliente tem no máximo uma subscrição ativa
SELECT 
    cliente_id,
    COUNT(*) as subscricoes_ativas
FROM subscricoes 
WHERE ativa = 1 AND data_fim >= CURDATE()
GROUP BY cliente_id 
HAVING COUNT(*) > 1;
-- Esta query deve retornar 0 resultados após a correção

-- 4. CONSTRAINT DE BASE DE DADOS (Opcional - MySQL 8.0+)
-- Adicionar índice único para prevenir inserções duplicadas
-- NOTA: Comentado porque pode não ser suportado em todas as versões do MySQL
/*
ALTER TABLE subscricoes 
ADD CONSTRAINT uk_cliente_subscricao_ativa 
UNIQUE KEY (cliente_id, ativa, data_fim)
WHERE ativa = 1 AND data_fim >= CURDATE();
*/

-- 5. RELATÓRIO FINAL: Mostrar estado atual das subscrições
SELECT 
    'Total de clientes' as tipo,
    COUNT(DISTINCT c.id) as quantidade
FROM clientes c
UNION ALL
SELECT 
    'Clientes com subscrição ativa' as tipo,
    COUNT(DISTINCT s.cliente_id) as quantidade
FROM subscricoes s 
WHERE s.ativa = 1 AND s.data_fim >= CURDATE()
UNION ALL
SELECT 
    'Total de subscrições ativas' as tipo,
    COUNT(*) as quantidade
FROM subscricoes s 
WHERE s.ativa = 1 AND s.data_fim >= CURDATE();
