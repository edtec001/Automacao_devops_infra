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
  PRIMARY KEY (`placa`),
  UNIQUE KEY `uk_posicao_chave_ativa` (`posicao_chave_ativa`),
  KEY `idx_veiculos_status` (`status`),
  KEY `idx_veiculos_cliente` (`cliente_id`),
  KEY `idx_veiculos_entrada` (`entrada`),
  CONSTRAINT `fk_veiculo_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `veiculos`
--

LOCK TABLES `veiculos` WRITE;
/*!40000 ALTER TABLE `veiculos` DISABLE KEYS */;
INSERT INTO `veiculos` (`placa`, `cliente_id`, `modelo`, `marca`, `ano`, `cor`, `km_atual`, `telefone`, `entrada`, `saida`, `status`, `observacoes`, `atualizado_em`, `posicao_chave`) VALUES ('ABC1D23',1,'Onix','Chevrolet',2022,'Prata',45000,NULL,'2026-08-16 18:04:58','2026-08-16 19:56:21','ENTRADA','VeÃ­culo de teste do sistema','2026-08-16 19:56:21',1),('NUP6107',2,'sandero','Renault',2011,'protp',169,NULL,'2026-08-16 19:02:29','2026-08-16 19:17:12','SERVICO','suspensao','2026-08-16 19:17:12',1),('NUP6109',3,'Onix','Chevrolet',2022,'Prata',50,NULL,'2026-08-16 19:06:37','2026-08-16 19:32:44','LIBERADO','farois','2026-08-16 19:32:44',2);
/*!40000 ALTER TABLE `veiculos` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-16 20:19:13
