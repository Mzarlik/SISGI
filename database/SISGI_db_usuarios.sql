-- MySQL dump 10.13  Distrib 8.0.45, for macos15 (x86_64)
--
-- Host: localhost    Database: SISGI_db
-- ------------------------------------------------------
-- Server version	5.5.5-10.11.13-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','tecnico','invitado','masterweb','redes') DEFAULT 'tecnico',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `intentos` int(11) DEFAULT 0,
  `bloqueo_hasta` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (3,'redes','$2y$10$t9RgJIBhiEupXwejWOeso.NFvidx.hln0dCYZx7AaOf88Lw6MODIm','redes','2025-11-28 00:39:17',0,NULL),(4,'masterweb','$2y$10$gqyeqnH47j30uy72yQ2BdOmOYsLklS5VNSwsUXNKHNhyYdtVW8CSm','masterweb','2025-11-28 00:39:17',0,NULL),(6,'OLPERGOD','$2y$10$PGe7ACfAKpqFsUlZlVqtZOlQcGmGnO2grKAeJi/ZK7TIDZ64uOyqu','tecnico','2025-11-28 00:39:17',0,NULL),(7,'manuel','$2y$10$KUZd3tdHjfg81kcnhohMgeItMvCpWfFZIG42AoE5ZXq67WZO27JUa','admin','2025-12-01 18:11:18',0,NULL),(15,'Franco','$2y$10$W3vcUa.hCN0J6cAhjc6RF.Pl0HRRNssLmzjRLKDklnOQZlvLv6l2K','tecnico','2025-12-08 17:47:04',0,NULL),(16,'Israel','$2y$10$lRcRsxM7bkpSQfP7UMhgPOhSJr8Y2ErA2dSdcBDfn/24tOZx4PqDG','tecnico','2025-12-08 17:54:39',0,NULL),(17,'Alahin','$2y$10$5tXzjeBPYpUwAMXtaWC1gufS7c.URzOEVY6odkv4xIJXhM6au3qZO','tecnico','2025-12-10 18:24:02',0,NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-27 12:07:47
