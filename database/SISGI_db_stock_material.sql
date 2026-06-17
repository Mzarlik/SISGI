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
-- Table structure for table `stock_material`
--

DROP TABLE IF EXISTS `stock_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_material` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('disco duro','usb','memoria ram','aire comprimido','limpiador de circuito','liquido limpiador de pantalla','toallas uso rudo','cable vga','cable hdmi','adaptador vga-hdmi','Hub usb','Tarjeta microSD','Unidad de estado solido M.2','Tarjeta de red inalámbrica','Espuma Limpiadora','Unidad de estado solido SATA 2.5"','Extension') NOT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `unidades` int(11) NOT NULL DEFAULT 0,
  `fecha_alta` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_material`
--

LOCK TABLES `stock_material` WRITE;
/*!40000 ALTER TABLE `stock_material` DISABLE KEYS */;
INSERT INTO `stock_material` VALUES (1,'usb','Kingston','DataTraveler Exodia M','Memoria USB versión 3.2 de 64 gb de almacenamiento',20,'2025-12-08'),(2,'Tarjeta microSD','Kingston','Canvas go plus','Tarjeta microSD XC de 64 gb de almacenamiento',2,'2025-12-08'),(3,'toallas uso rudo','','','10 rollos de toallas de uso rudo color azul con un total de 60 hojas cada rollo',0,'2025-12-08'),(4,'memoria ram','Samsung','K0AS00074','Memoria RAM sodimm ddr4 4gb',0,'2025-12-08'),(5,'disco duro','Toshiba','PC L200','Disco duro interno de 2.5\" de 1 TB de almacenamiento de 5400 rpm',4,'2025-12-08'),(6,'memoria ram','Samsung','SO-DIMM','8gb DDR4 3200AA',4,'2025-12-09'),(7,'memoria ram','Kingston','SO-DIMM','8gb DDR4 3200AA',3,'2025-12-09'),(8,'memoria ram','Samsung','SO-DIMM','4gb DDR4 2400MT/s',3,'2025-12-09'),(9,'memoria ram','Samsung','SO-DIMM','4gb DDR4 3200MT/s',2,'2025-12-09'),(10,'memoria ram','Lenovo-Ramaxel','SO-DIMM','4gb DDR3 1600MT/s',2,'2025-12-09'),(11,'memoria ram','Micron','DIMM','4gb DDR3 12800MB/s',2,'2025-12-09'),(12,'memoria ram','Lenovo-Ramaxel','SO-DIMM','8gm DDR4 3200MT/s',1,'2025-12-09'),(13,'memoria ram','Lenovo-Ramaxel','SO-DIMM','4gb DDR4 2666MT/s',1,'2025-12-09'),(14,'memoria ram','Samsung','SO-DIMM','4gb DDR3 12800MB/s',1,'2025-12-09'),(15,'memoria ram','Samsung','SO-DIMM','8gb DDR4 2133MT/s 16 núcleos',1,'2025-12-09'),(16,'memoria ram','Samsung','SO-DIMM','2gb DDR4 2400MT/s',1,'2025-12-09'),(17,'memoria ram','Kingston','SO-DIMM','4gb DDR4 2666MT/s',1,'2025-12-09'),(18,'memoria ram','SK-hynix','SO-DIMM','8gb DDR4 2666MT/s',1,'2025-12-09'),(19,'memoria ram','SK-hynix','SO-DIMM','2gb DDR4 2400MT/s',1,'2025-12-09'),(20,'aire comprimido','','','Marca Vorago 440ml',5,'2025-12-09'),(21,'toallas uso rudo','','','Marca scott toallas',4,'2025-12-09'),(22,'limpiador de circuito','','','Marca slimex 454ml',10,'2025-12-10'),(23,'liquido limpiador de pantalla','','','Marca slimex 250ml',10,'2025-12-10'),(24,'Espuma Limpiadora','','','Marca perfect-choice 432ml',2,'2025-12-10'),(25,'Espuma Limpiadora','','','Marca slimex 454ml',1,'2025-12-10'),(26,'Unidad de estado solido M.2','Lenovo-Union Memory','SSS0L25216','256gb',1,'2025-12-10'),(27,'Tarjeta de red inalámbrica','Realtek','RTL8852BE','Color verde oscuro',2,'2025-12-10'),(28,'Tarjeta de red inalámbrica','Realtek','RTL8852BE','MAC(23S) color verde',1,'2025-12-10');
/*!40000 ALTER TABLE `stock_material` ENABLE KEYS */;
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
