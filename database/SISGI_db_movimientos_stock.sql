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
-- Table structure for table `movimientos_stock`
--

DROP TABLE IF EXISTS `movimientos_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Clave primaria del movimiento',
  `material_id` int(11) NOT NULL COMMENT 'ID del material afectado (FK a stock_material)',
  `tipo_movimiento` enum('ENTRADA','SALIDA') NOT NULL DEFAULT 'SALIDA' COMMENT 'Define si fue una entrada o una salida',
  `cantidad` int(11) NOT NULL COMMENT 'Cantidad de unidades movidas (positiva)',
  `descripcion_salida` varchar(500) DEFAULT NULL COMMENT 'Motivo o destino del material (opcional)',
  `usuario` varchar(100) NOT NULL COMMENT 'Usuario que registra el movimiento (ej: admin)',
  `fecha_movimiento` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora del registro',
  PRIMARY KEY (`id`),
  KEY `fk_material_id` (`material_id`),
  KEY `idx_material_fecha` (`material_id`,`fecha_movimiento` DESC),
  CONSTRAINT `fk_material_id` FOREIGN KEY (`material_id`) REFERENCES `stock_material` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registro de movimientos de entrada y salida de inventario';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimientos_stock`
--

LOCK TABLES `movimientos_stock` WRITE;
/*!40000 ALTER TABLE `movimientos_stock` DISABLE KEYS */;
INSERT INTO `movimientos_stock` VALUES (1,2,'SALIDA',1,'Se ocupo una tarjeta sd para cámara Canon powershot ELPH-180 a nombre de Manuel Lozano','manuel','2025-12-08 10:52:30'),(2,2,'SALIDA',3,'Se tomaron 3 tarjetas SD para las cámaras del área de soporte técnico','manuel','2025-12-08 11:00:04'),(3,2,'SALIDA',4,'Se tomaron 4 microSD para las camaras que se les fue entregada al area de Redes de la Direccion de Nuevas Tecnologías de la Información y Comunicaciones','manuel','2025-12-08 11:00:48'),(4,4,'SALIDA',1,'Se ocupo para equipo de ingresos','manuel','2025-12-08 17:41:30');
/*!40000 ALTER TABLE `movimientos_stock` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-27 12:07:48
