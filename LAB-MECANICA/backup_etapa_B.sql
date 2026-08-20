-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: oficina
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `veiculos`
--

DROP TABLE IF EXISTS `veiculos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `veiculos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `placa` varchar(10) NOT NULL,
  `cliente_id` int DEFAULT NULL,
  `modelo` varchar(100) NOT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `ano` int DEFAULT NULL,
  `cor` varchar(50) DEFAULT NULL,
  `km_atual` int DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `entrada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `saida` datetime DEFAULT NULL,
  `status` enum('ENTRADA','ORCAMENTO','SERVICO','LIBERADO') NOT NULL DEFAULT 'ENTRADA',
  `observacoes` text,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `posicao_chave` int DEFAULT NULL,
  `posicao_chave_ativa` int GENERATED ALWAYS AS ((case when (`saida` is null) then `posicao_chave` else NULL end)) STORED,
  `mecanico_id` int DEFAULT NULL,
  PRIMARY KEY (`placa`),
  UNIQUE KEY `uk_veiculos_id` (`id`),
  UNIQUE KEY `uk_posicao_chave_ativa` (`posicao_chave_ativa`),
  KEY `idx_veiculos_status` (`status`),
  KEY `idx_veiculos_cliente` (`cliente_id`),
  KEY `idx_veiculos_entrada` (`entrada`),
  CONSTRAINT `fk_veiculo_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `veiculos`
--

LOCK TABLES `veiculos` WRITE;
/*!40000 ALTER TABLE `veiculos` DISABLE KEYS */;
INSERT INTO `veiculos` (`id`, `placa`, `cliente_id`, `modelo`, `marca`, `ano`, `cor`, `km_atual`, `telefone`, `entrada`, `saida`, `status`, `observacoes`, `atualizado_em`, `posicao_chave`, `mecanico_id`) VALUES (1,'ABC1D23',1,'Onix','Chevrolet',2022,'Prata',45000,NULL,'2026-08-16 18:04:58','2026-08-16 19:56:21','ENTRADA','VeÃ­culo de teste do sistema','2026-08-16 21:09:34',1,NULL),(4,'ABD1234',3,'Onix','Chevrolet',2023,'Prata',400,NULL,'2026-08-16 21:15:26','2026-08-16 21:15:59','ORCAMENTO','cambio','2026-08-17 16:45:50',1,NULL),(5,'ABD1235',4,'sandero','Renault',2011,'Preto',60,NULL,'2026-08-17 13:14:32',NULL,'LIBERADO','defeitos intermitentes nos farois','2026-08-17 16:46:07',4,1),(2,'NUP6107',2,'sandero','Renault',2011,'protp',169,NULL,'2026-08-16 19:02:29','2026-08-16 19:17:12','SERVICO','suspensao','2026-08-16 21:09:34',1,NULL),(3,'NUP6109',3,'Onix','Chevrolet',2022,'Prata',50,NULL,'2026-08-16 19:06:37','2026-08-16 19:32:44','LIBERADO','farois','2026-08-16 21:09:34',2,NULL),(6,'NUTP7107',5,'sandero','Renault',2024,'',40,NULL,'2026-08-17 13:57:01','2026-08-17 14:30:21','LIBERADO','','2026-08-17 16:46:21',5,1);
/*!40000 ALTER TABLE `veiculos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordens_servico`
--

DROP TABLE IF EXISTS `ordens_servico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordens_servico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `placa` varchar(10) NOT NULL,
  `data_abertura` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fechamento` datetime DEFAULT NULL,
  `diagnostico` text,
  `observacoes` text,
  `status` enum('ABERTA','ORCAMENTO','APROVADA','EM_SERVICO','AGUARDANDO_PECA','FINALIZADA','CANCELADA') NOT NULL DEFAULT 'ABERTA',
  `valor_servicos` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_pecas` decimal(10,2) NOT NULL DEFAULT '0.00',
  `desconto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(10,2) GENERATED ALWAYS AS (((`valor_servicos` + `valor_pecas`) - `desconto`)) STORED,
  `defeito_relatado` text,
  `itens_veiculo` text,
  `estado_lataria` text,
  `foto_lataria` varchar(255) DEFAULT NULL,
  `nivel_combustivel` varchar(20) DEFAULT NULL,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `numero_parcelas` int DEFAULT '1',
  `valor_pago` decimal(10,2) DEFAULT '0.00',
  `data_pagamento` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_os_placa` (`placa`),
  KEY `idx_os_status` (`status`),
  CONSTRAINT `fk_os_veiculo` FOREIGN KEY (`placa`) REFERENCES `veiculos` (`placa`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordens_servico`
--

LOCK TABLES `ordens_servico` WRITE;
/*!40000 ALTER TABLE `ordens_servico` DISABLE KEYS */;
INSERT INTO `ordens_servico` (`id`, `placa`, `data_abertura`, `data_fechamento`, `diagnostico`, `observacoes`, `status`, `valor_servicos`, `valor_pecas`, `desconto`, `defeito_relatado`, `itens_veiculo`, `estado_lataria`, `foto_lataria`, `nivel_combustivel`, `forma_pagamento`, `numero_parcelas`, `valor_pago`, `data_pagamento`) VALUES (1,'NUTP7107','2026-08-17 13:57:01','2026-08-17 14:30:21',NULL,'','FINALIZADA',110.00,35.90,0.00,'manutenção no cambio','Macaco, Triângulo, Chave de Roda, Manual do Proprietário, Documento do Veículo, Chave Reserva, Ferramentas','',NULL,'1/2','DINHEIRO',1,145.90,'2026-08-17 14:30:21');
/*!40000 ALTER TABLE `ordens_servico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_pecas`
--

DROP TABLE IF EXISTS `os_pecas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_pecas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ordem_servico_id` int NOT NULL,
  `peca_id` int NOT NULL,
  `quantidade` int NOT NULL DEFAULT '1',
  `preco_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(10,2) GENERATED ALWAYS AS ((`quantidade` * `preco_unitario`)) STORED,
  PRIMARY KEY (`id`),
  KEY `fk_ospeca_os` (`ordem_servico_id`),
  KEY `fk_ospeca_peca` (`peca_id`),
  CONSTRAINT `fk_ospeca_os` FOREIGN KEY (`ordem_servico_id`) REFERENCES `ordens_servico` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ospeca_peca` FOREIGN KEY (`peca_id`) REFERENCES `pecas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_pecas`
--

LOCK TABLES `os_pecas` WRITE;
/*!40000 ALTER TABLE `os_pecas` DISABLE KEYS */;
INSERT INTO `os_pecas` (`id`, `ordem_servico_id`, `peca_id`, `quantidade`, `preco_unitario`) VALUES (1,1,1,1,35.90);
/*!40000 ALTER TABLE `os_pecas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `arduino_comandos`
--

DROP TABLE IF EXISTS `arduino_comandos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `arduino_comandos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `placa` varchar(10) NOT NULL,
  `status` varchar(20) NOT NULL,
  `comando` varchar(20) NOT NULL,
  `processado` tinyint(1) NOT NULL DEFAULT '0',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_arduino_processado` (`processado`),
  KEY `idx_arduino_placa` (`placa`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `arduino_comandos`
--

LOCK TABLES `arduino_comandos` WRITE;
/*!40000 ALTER TABLE `arduino_comandos` DISABLE KEYS */;
INSERT INTO `arduino_comandos` VALUES (1,'NUP6107','ENTRADA','VERMELHO',1,'2026-08-16 19:02:29','2026-08-17 13:12:10'),(2,'NUP6109','ENTRADA','VERMELHO',1,'2026-08-16 19:06:37','2026-08-17 13:12:10'),(3,'NUP6109','ENTRADA','VERMELHO',1,'2026-08-16 19:16:31','2026-08-17 13:12:10'),(4,'NUP6107','SERVICO','AZUL',1,'2026-08-16 19:17:01','2026-08-17 13:12:10'),(5,'NUP6107','SAIDA','DESLIGAR',1,'2026-08-16 19:17:12','2026-08-17 13:12:10'),(6,'NUP6109','LIBERADO','VERDE',1,'2026-08-16 19:32:36','2026-08-17 13:12:10'),(7,'NUP6109','SAIDA','DESLIGAR',1,'2026-08-16 19:32:44','2026-08-17 13:12:10'),(8,'ABC1D23','ENTRADA','VERMELHO',1,'2026-08-16 19:56:17','2026-08-17 13:12:10'),(9,'ABC1D23','SAIDA','DESLIGAR',1,'2026-08-16 19:56:21','2026-08-17 13:12:10'),(10,'ABD1234','ENTRADA','VERMELHO',1,'2026-08-16 21:15:26','2026-08-17 13:12:10'),(11,'ABD1234','ORCAMENTO','AMARELO',1,'2026-08-16 21:15:45','2026-08-17 13:12:12'),(12,'ABD1234','SAIDA','DESLIGAR',1,'2026-08-16 21:15:59','2026-08-17 13:12:12'),(13,'ABD1235','ENTRADA','VERMELHO',1,'2026-08-17 13:14:32','2026-08-17 13:14:33'),(14,'ABD1235','ORCAMENTO','AMARELO',1,'2026-08-17 13:14:56','2026-08-17 13:14:57'),(15,'ABD1235','SERVICO','AZUL',1,'2026-08-17 13:15:28','2026-08-17 13:15:29'),(16,'ABD1235','LIBERADO','VERDE',1,'2026-08-17 13:15:49','2026-08-17 13:15:51'),(17,'NUTP7107','ENTRADA','VERMELHO',1,'2026-08-17 13:57:01','2026-08-17 13:57:02'),(18,'NUTP7107','ORCAMENTO','AMARELO',1,'2026-08-17 13:59:26','2026-08-17 13:59:27'),(19,'NUTP7107','LIBERADO','VERDE',1,'2026-08-17 14:12:40','2026-08-17 14:12:42'),(20,'NUTP7107','LIBERADO','VERDE',1,'2026-08-17 14:20:12','2026-08-17 14:20:13'),(21,'NUTP7107','SAIDA','DESLIGAR',1,'2026-08-17 14:30:21','2026-08-17 14:30:22');
/*!40000 ALTER TABLE `arduino_comandos` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-17 17:06:38
